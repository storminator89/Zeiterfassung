<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// One coarse native/framework model-history flow; isolated synthetic copies only.
use App\Auth\LegacyUser;
use App\Chrono\LocalWebWorkModels;
use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\AuditContext;
use App\LegacyDatabase\AuditFunctions;
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\SourceSnapshot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connectors\SQLiteConnector;

umask(0077);
$root = dirname(__DIR__, 2);
require dirname(__DIR__).'/vendor/autoload.php';
require $root.'/lib/attendance.php';
require $root.'/lib/practice.php';
require_once $root.'/lib/reporting.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = $app->make('config');
$database = $app->make('db');
$phase = 'inactive authenticated admin/admission boundary';
$ensure = static function (bool $condition): void {
    if (! $condition) { throw new RuntimeException('Coarse work-model lifecycle invariant failed.'); }
};
$refuses = static function (Closure $operation, string $type) use ($ensure): Throwable {
    try { $operation(); }
    catch (Throwable $error) { $ensure($error instanceof $type); return $error; }
    throw new RuntimeException('Expected work-model refusal did not occur.');
};
$previous = ['app.key' => $config->get('app.key'), 'session.driver' => $config->get('session.driver')];
$globals = array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION']));

try {
    $ensure($argc === 1 && ! $config->has('database.connections.chrono')
        && $database->getConnections() === [] && ! $app->bound(AttendanceWriter::class));
    $service = $app->make(LocalWebWorkModels::class);
    $ensure($database->getConnections() === [] && ! $app->bound(AttendanceWriter::class));
    $config->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    $config->set('session.driver', 'array');
    $guard = $app->make('auth')->guard('web');
    $refuses(fn () => $service->apply([]), AuthenticationException::class);
    foreach (['user', 'supervisor'] as $role) {
        $guard->setUser((new LegacyUser)->newFromBuilder(['id' => 1, 'role' => $role, 'auth_source' => 'local'], 'chrono'));
        $refuses(fn () => $service->apply(['role' => 'admin']), AuthorizationException::class);
    }
    foreach ([['id' => 0, 'role' => 'admin', 'auth_source' => 'local'],
        ['id' => 2, 'role' => 'admin', 'auth_source' => 'ldap'],
        ['id' => 2, 'role' => 'unknown', 'auth_source' => 'local']] as $invalid) {
        $guard->setUser((new LegacyUser)->newFromBuilder($invalid, 'chrono'));
        $refuses(fn () => $service->apply([]), AuthenticationException::class);
    }
    $guard->setUser((new LegacyUser)->newFromBuilder(['id' => 2, 'role' => 'admin', 'auth_source' => 'local'], 'chrono'));
    $refuses(fn () => $service->apply([]), LogicException::class);

    $phase = 'native fallback and synthetic offline admission';
    $directory = sys_get_temp_dir().'/chrono-web-work-models-'.bin2hex(random_bytes(8));
    $ensure(mkdir($directory, 0700));
    $mapping = json_decode(file_get_contents($root.'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
    $source = (new SQLiteConnector)->connect(['database' => ':memory:', 'busy_timeout' => 5000]);
    $build = require dirname(__DIR__).'/database/fixtures/legacy_v2.php';
    $build($source, $mapping, true);
    $command = ['user_id' => '1', 'valid_from' => '2026-10-26', 'reason' => 'Synthetic native model',
        'day1' => '240', 'day2' => '360', 'day3' => '0', 'day4' => '300', 'day5' => '180', 'day6' => '120', 'day7' => '1440',
        'role' => 'user', 'actor_id' => 1, 'source' => 'untrusted', 'audit_actor' => 'untrusted', 'audit_reason' => 'untrusted'];
    $refuses(fn () => practice_work_model_apply_locked($source, 2, 'admin', $command), LogicException::class);
    $source->exec('BEGIN IMMEDIATE');
    $error = $refuses(fn () => practice_work_model_apply_locked($source, 1, 'user', $command), AttendanceError::class);
    $ensure($error->status === 403);
    $source->exec('ROLLBACK');
    $GLOBALS['audit_actor'] = 'web:2';
    $source->exec('BEGIN IMMEDIATE');
    practice_work_model_apply_locked($source, 2, 'admin', $command);
    $source->exec('COMMIT');
    $ensure($GLOBALS['audit_reason'] === 'Arbeitszeitmodell: Synthetic native model'
        && att_one($source, "SELECT actor,reason FROM audit_log WHERE entity='work_models' AND record_id=3")
            === ['actor' => 'web:2', 'reason' => 'Arbeitszeitmodell: Synthetic native model']);
    practice_absence($source, 1, '2026-10-29', '2026-10-29', 'Urlaub');
    $projections = $source->query('SELECT * FROM zeiterfassung ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $nativeModel = att_one($source, 'SELECT * FROM work_models WHERE id=3');
    $ensure(planned_minutes($source, 1, '2026-10-29') === 300
        && report_period_minutes(report_days($projections), '2026-10-29', '2026-10-29') === 300);
    $source->exec('VACUUM INTO '.$source->quote($directory.'/original.sqlite'));
    $source = null;
    $ensure(copy($directory.'/original.sqlite', $directory.'/target.sqlite') && copy($directory.'/original.sqlite', $directory.'/backup.sqlite'));
    $originalHash = hash_file('sha256', $directory.'/original.sqlite');
    $path = $directory.'/target.sqlite';
    $ensure($app->make(Kernel::class)->call('chrono:legacy-v2', [
        'action' => 'adopt', '--target' => $path, '--backup' => $directory.'/backup.sqlite', '--offline' => true,
    ]) === 0);
    $open = static function () use ($root, $path): array {
        $files = new OfflineFiles([$root, $root.'/framework/public']);
        $configuration = $files->readWriteConfiguration($path, $files->snapshot($path));
        return [(new SQLiteConnector)->connect($configuration), $configuration];
    };
    $failAudit = false;
    $lastPdo = null;
    // Only this synthetic composition binds fresh already-admitted #75 writers.
    $app->bind(AttendanceWriter::class, function ($app) use ($open, &$failAudit, &$lastPdo): AttendanceWriter {
        [$pdo, $configuration] = $open();
        $writer = new AttendanceWriter($pdo, $configuration, $app->make(AuditContext::class), $app->make(AuditFunctions::class));
        $lastPdo = $pdo;
        if ($failAudit) {
            // Connection-local fault after the model's audit row; no schema change.
            $pdo->exec("CREATE TEMP TRIGGER synthetic_model_audit_failure AFTER INSERT ON audit_log
                WHEN NEW.entity='work_models' BEGIN SELECT RAISE(ABORT,'Synthetic model audit failure'); END");
        }
        return $writer;
    });
    [$observer] = $open();
    $guard->setUser((new LegacyUser)->newFromBuilder(att_one($observer, 'SELECT id,role,auth_source FROM users WHERE id=2'), 'chrono'));
    $context = $app->make(AuditContext::class);
    $invoke = static function (array $command) use ($context, $service, $ensure): void {
        $context->run('fixture:outer', 'Synthetic surrounding context', function () use ($context, $service, $ensure, $command): void {
            try { $service->apply($command); }
            finally { $ensure($context->actor() === 'fixture:outer' && $context->reason() === 'Synthetic surrounding context'); }
        });
    };
    $GLOBALS['audit_actor'] = null;
    $GLOBALS['audit_reason'] = 'synthetic previous native reason';
    $GLOBALS['_SESSION'] = ['synthetic_marker' => 'unchanged'];

    $phase = 'critical native validation and append-only effective-date history';
    $before = SourceSnapshot::capture($observer, $mapping);
    // Representative malformed form batch, not a new fine-grained unit suite.
    foreach ([['valid_from' => '2026-02-30'], ['day4' => '-1'], ['day7' => '1441'],
        ['user_id' => '9999'], ['reason' => 'no']] as $invalid) {
        $error = $refuses(fn () => $invoke(array_replace($command, $invalid)), AttendanceError::class);
        $ensure($error->status === 400 && ! $lastPdo->inTransaction() && SourceSnapshot::capture($observer, $mapping) === $before);
    }
    $cursor = (int) $observer->query('SELECT MAX(id) FROM audit_log')->fetchColumn();
    $command['reason'] = '  Synthetic backdated revision  ';
    $command['day4'] = '60';
    $invoke($command);
    $ensure(planned_minutes($observer, 1, '2026-10-29') === 60);
    $command['reason'] = 'Synthetic same-date latest revision';
    $command['day4'] = '90';
    $invoke($command);
    $ensure(att_one($observer, 'SELECT * FROM work_models WHERE id=3') === $nativeModel
        && $observer->query('SELECT id,user_id,valid_from,created_by FROM work_models WHERE id>3 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)
            === [['id' => 4, 'user_id' => 1, 'valid_from' => '2026-10-26', 'created_by' => 2],
                ['id' => 5, 'user_id' => 1, 'valid_from' => '2026-10-26', 'created_by' => 2]]
        && planned_minutes($observer, 1, '2026-10-23') === 270
        && planned_minutes($observer, 1, '2026-10-29') === 90
        && planned_hours($observer, 1, '2026-10-29', '2026-10-29') === 1.5
        && $observer->query('SELECT * FROM zeiterfassung ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $projections
        && report_period_minutes(report_days($projections), '2026-10-29', '2026-10-29') === 300
        && $observer->query('SELECT actor,reason FROM audit_log WHERE id>'.$cursor.' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)
            === [['actor' => 'web:2', 'reason' => 'Arbeitszeitmodell: Synthetic backdated revision'],
                ['actor' => 'web:2', 'reason' => 'Arbeitszeitmodell: Synthetic same-date latest revision']]);
    $before = SourceSnapshot::capture($observer, $mapping);
    $refuses(fn () => $observer->exec("UPDATE work_models SET reason='rewrite' WHERE id=3"), PDOException::class);
    $refuses(fn () => $observer->exec('DELETE FROM work_models WHERE id=3'), PDOException::class);
    $ensure(SourceSnapshot::capture($observer, $mapping) === $before);

    $phase = 'audit failure rolls back model/audit/sequences and restores contexts';
    $failAudit = true;
    $refuses(fn () => $invoke($command), PDOException::class);
    $ensure(! $lastPdo->inTransaction() && SourceSnapshot::capture($observer, $mapping) === $before);
    $refuses(fn () => $context->actor(), LogicException::class);
    $ensure($GLOBALS['audit_actor'] === null && $GLOBALS['audit_reason'] === 'synthetic previous native reason'
        && $GLOBALS['_SESSION'] === ['synthetic_marker' => 'unchanged'] && $database->getConnections() === []
        && ! $config->has('database.connections.chrono') && hash_file('sha256', $directory.'/original.sqlite') === $originalHash
        && hash_file('sha256', $directory.'/backup.sqlite') === $originalHash);
    echo json_encode(['passed' => true, 'groups' => ['inactive trusted-admin/admission boundary and native fallback',
        'model validation/history and exact audit identity', 'backdated report target without repricing stored absence',
        'model/audit/sequence rollback and context restoration'], 'synthetic_private_files_only' => true,
        'runtime_auth_http_ui_acceptance' => false], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$phase.' ('.$error::class.").\n");
    exit(1);
} finally {
    $app->forgetInstance(AttendanceWriter::class);
    $app->make('auth')->forgetGuards();
    foreach ($previous as $name => $value) { $config->set($name, $value); }
    $app->forgetInstance('encrypter');
    unset($GLOBALS['audit_actor'], $GLOBALS['audit_reason'], $GLOBALS['_SESSION']);
    foreach ($globals as $name => $value) { $GLOBALS[$name] = $value; }
}
