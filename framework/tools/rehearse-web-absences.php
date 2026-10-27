<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// One coarse native/framework absence flow; isolated synthetic copies only.
use App\Auth\LegacyUser;
use App\Chrono\LocalWebAbsences;
use App\Chrono\LocalWebWorkModels;
use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\AuditContext;
use App\LegacyDatabase\AuditFunctions;
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\SourceSnapshot;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connectors\SQLiteConnector;

umask(0077);
$root = dirname(__DIR__, 2);
require dirname(__DIR__).'/vendor/autoload.php';
require $root.'/lib/attendance.php';
require $root.'/lib/practice.php';
require $root.'/lib/reporting.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = $app->make('config');
$database = $app->make('db');
$phase = 'inactive own-user/authentication/admission boundary';
$ensure = static function (bool $condition): void {
    if (! $condition) { throw new RuntimeException('Coarse absence lifecycle invariant failed at line '.debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'].'.'); }
};
$refuses = static function (Closure $operation, string $type) use ($ensure): Throwable {
    try { $operation(); }
    catch (Throwable $error) { $ensure($error instanceof $type); return $error; }
    throw new RuntimeException('Expected absence refusal did not occur.');
};
$previous = ['app.key' => $config->get('app.key'), 'session.driver' => $config->get('session.driver')];
$globals = array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION']));

try {
    $ensure($argc === 1 && ! $config->has('database.connections.chrono')
        && $database->getConnections() === [] && ! $app->bound(AttendanceWriter::class));
    $service = $app->make(LocalWebAbsences::class);
    $ensure($database->getConnections() === [] && ! $app->bound(AttendanceWriter::class));
    $config->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    $config->set('session.driver', 'array');
    $guard = $app->make('auth')->guard('web');
    $refuses(fn () => $service->create('2026-11-02', '2026-11-08', 'Urlaub'), AuthenticationException::class);
    foreach ([['id' => 0, 'role' => 'user', 'auth_source' => 'local'],
        ['id' => 1, 'role' => 'user', 'auth_source' => 'ldap'],
        ['id' => 1, 'role' => 'unknown', 'auth_source' => 'local']] as $invalid) {
        $guard->setUser((new LegacyUser)->newFromBuilder($invalid, 'chrono'));
        $refuses(fn () => $service->create('2026-11-02', '2026-11-08', 'Urlaub'), AuthenticationException::class);
    }
    $guard->setUser((new LegacyUser)->newFromBuilder(['id' => 1, 'role' => 'user', 'auth_source' => 'local'], 'chrono'));
    $refuses(fn () => $service->create('2026-11-02', '2026-11-08', 'Urlaub'), LogicException::class);

    $phase = 'native fallback and isolated synthetic offline admission';
    $directory = sys_get_temp_dir().'/chrono-web-absences-'.bin2hex(random_bytes(8));
    $ensure(mkdir($directory, 0700));
    $mapping = json_decode(file_get_contents($root.'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
    $source = (new SQLiteConnector)->connect(['database' => ':memory:', 'busy_timeout' => 5000]);
    $build = require dirname(__DIR__).'/database/fixtures/legacy_v2.php';
    $build($source, $mapping, true);
    $refuses(fn () => practice_absence_apply_locked($source, 1, '2026-10-29', '2026-10-29', 'Krankheit'), LogicException::class);
    $GLOBALS['audit_actor'] = 'web:1';
    $GLOBALS['audit_reason'] = 'Synthetic preceding reason';
    $error = $refuses(fn () => practice_absence($source, 1, '2026-02-30', '2026-10-29', 'Krankheit'), AttendanceError::class);
    $ensure($error->status === 400 && ! $source->inTransaction() && $GLOBALS['audit_reason'] === 'Synthetic preceding reason');
    $ensure(practice_absence($source, 1, '2026-10-29', '2026-10-29', 'Krankheit')
        === ['success' => true, 'message' => '1 Abwesenheitstage nach Arbeitszeitmodell erfasst.']);
    $ensure($GLOBALS['audit_reason'] === 'Abwesenheit: Krankheit'
        && att_one($source, "SELECT actor,reason FROM audit_log WHERE entity='zeiterfassung' AND record_id=44")
            === ['actor' => 'web:1', 'reason' => 'Abwesenheit: Krankheit']);
    $source->exec('BEGIN IMMEDIATE');
    practice_work_model_apply_locked($source, 2, 'admin', ['user_id' => '1', 'valid_from' => '2026-11-02',
        'reason' => 'Synthetic absence model', 'day1' => '300', 'day2' => '0', 'day3' => '360',
        'day4' => '180', 'day5' => '240', 'day6' => '120', 'day7' => '0']);
    att_exec($source, 'INSERT INTO Feiertage(datum,name) VALUES(?,?)', ['2026-11-04', 'Synthetic holiday']);
    $source->exec('COMMIT');
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
    // Only this synthetic composition supplies fresh already-admitted writers.
    $app->bind(AttendanceWriter::class, function ($app) use ($open, &$failAudit, &$lastPdo): AttendanceWriter {
        [$pdo, $configuration] = $open();
        $writer = new AttendanceWriter($pdo, $configuration, $app->make(AuditContext::class), $app->make(AuditFunctions::class));
        $lastPdo = $pdo;
        if ($failAudit) {
            // Connection-local failure after the second credit's audit insert.
            $pdo->exec("CREATE TEMP TRIGGER synthetic_absence_audit_failure AFTER INSERT ON audit_log
                WHEN NEW.entity='zeiterfassung' AND json_extract(NEW.after_json,'$.startzeit') LIKE '2026-11-20%'
                BEGIN SELECT RAISE(ABORT,'Synthetic absence audit failure'); END");
        }
        return $writer;
    });
    [$observer] = $open();
    $owner = att_one($observer, 'SELECT id,role,auth_source FROM users WHERE id=1');
    $guard->setUser((new LegacyUser)->newFromBuilder($owner, 'chrono'));
    $context = $app->make(AuditContext::class);
    $invoke = static function (string $start, string $end, string $kind) use ($context, $service, $ensure): array {
        return $context->run('fixture:outer', 'Synthetic surrounding context', function () use ($context, $service, $ensure, $start, $end, $kind): array {
            try { return $service->create($start, $end, $kind); }
            finally { $ensure($context->actor() === 'fixture:outer' && $context->reason() === 'Synthetic surrounding context'); }
        });
    };
    $GLOBALS['audit_actor'] = null;
    $GLOBALS['audit_reason'] = 'Synthetic previous native reason';
    $GLOBALS['_SESSION'] = ['user_id' => 9999, 'synthetic_marker' => 'unchanged'];

    $phase = 'create, zero/holiday days, own-scope credit snapshot and exact audit';
    $before = SourceSnapshot::capture($observer, $mapping);
    $error = $refuses(fn () => $invoke('2026-11-02', '2026-11-08', 'Other'), AttendanceError::class);
    $ensure($error->status === 400 && SourceSnapshot::capture($observer, $mapping) === $before);
    $cursor = (int) $observer->query('SELECT MAX(id) FROM audit_log')->fetchColumn();
    $ensure($invoke('2026-11-02', '2026-11-08', 'Urlaub')
        === ['success' => true, 'message' => '4 Abwesenheitstage nach Arbeitszeitmodell erfasst.']);
    $credits = $observer->query('SELECT * FROM zeiterfassung WHERE id>44 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $ensure(array_column($credits, 'user_id') === [1, 1, 1, 1]
        && array_column($credits, 'startzeit') === ['2026-11-02T00:00:00+01:00', '2026-11-05T00:00:00+01:00', '2026-11-06T00:00:00+01:00', '2026-11-07T00:00:00+01:00']
        && array_column($credits, 'endzeit') === ['2026-11-02T05:00:00+01:00', '2026-11-05T03:00:00+01:00', '2026-11-06T04:00:00+01:00', '2026-11-07T02:00:00+01:00']
        && array_column($credits, 'planned_minutes') === [300, 180, 240, 120]
        && array_column($credits, 'absence_kind') === ['Urlaub', 'Urlaub', 'Urlaub', 'Urlaub']
        && array_column($credits, 'pause') === [0, 0, 0, 0]
        && (int) $observer->query('SELECT COUNT(*) FROM work_sessions')->fetchColumn() === 0
        && (int) $observer->query('SELECT COUNT(*) FROM attendance_events')->fetchColumn() === 0
        && $observer->query('SELECT actor,reason FROM audit_log WHERE id>'.$cursor.' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)
            === array_fill(0, 4, ['actor' => 'web:1', 'reason' => 'Abwesenheit: Urlaub']));
    $before = SourceSnapshot::capture($observer, $mapping);
    $ensure($invoke('2026-11-15', '2026-11-15', 'Krankheit')
        === ['success' => true, 'message' => '0 Abwesenheitstage nach Arbeitszeitmodell erfasst.']
        && SourceSnapshot::capture($observer, $mapping) === $before);

    $phase = 'duplicate and partially inserted overlapping batch are atomic';
    foreach ([['2026-11-02', '2026-11-02'], ['2026-10-30', '2026-11-02']] as [$start, $end]) {
        $error = $refuses(fn () => $invoke($start, $end, 'Krankheit'), AttendanceError::class);
        $ensure($error->status === 409 && ! $lastPdo->inTransaction() && SourceSnapshot::capture($observer, $mapping) === $before);
    }

    $phase = 'model history changes target, never stored absence credit';
    $guard->setUser((new LegacyUser)->newFromBuilder(att_one($observer, 'SELECT id,role,auth_source FROM users WHERE id=2'), 'chrono'));
    $app->make(LocalWebWorkModels::class)->apply(['user_id' => '1', 'valid_from' => '2026-11-05',
        'reason' => 'Synthetic later historical model', 'day1' => '300', 'day2' => '0', 'day3' => '360',
        'day4' => '90', 'day5' => '60', 'day6' => '60', 'day7' => '0']);
    $guard->setUser((new LegacyUser)->newFromBuilder($owner, 'chrono'));
    $ensure(planned_minutes($observer, 1, '2026-11-02') === 300 && planned_minutes($observer, 1, '2026-11-05') === 90
        && $observer->query('SELECT * FROM zeiterfassung WHERE id>44 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $credits
        && report_period_minutes(report_days($credits), '2026-11-02', '2026-11-08') === 840);

    $phase = 'last audit failure rolls back both credits, journal and sequences';
    $before = SourceSnapshot::capture($observer, $mapping);
    $failAudit = true;
    $refuses(fn () => $invoke('2026-11-19', '2026-11-20', 'Krankheit'), PDOException::class);
    $ensure(! $lastPdo->inTransaction() && SourceSnapshot::capture($observer, $mapping) === $before);
    $refuses(fn () => $context->actor(), LogicException::class);
    $ensure($GLOBALS['audit_actor'] === null && $GLOBALS['audit_reason'] === 'Synthetic previous native reason'
        && $GLOBALS['_SESSION'] === ['user_id' => 9999, 'synthetic_marker' => 'unchanged']
        && $database->getConnections() === [] && ! $config->has('database.connections.chrono')
        && hash_file('sha256', $directory.'/original.sqlite') === $originalHash
        && hash_file('sha256', $directory.'/backup.sqlite') === $originalHash);
    echo json_encode(['passed' => true, 'groups' => ['inactive own-user/admission boundary and native fallback',
        'credit/zero/holiday/model snapshot and exact audit', 'duplicate/partial overlap rollback and historical snapshot',
        'last audit failure, sequence rollback and context restoration'], 'synthetic_private_files_only' => true,
        'runtime_auth_http_ui_acceptance' => false], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$phase.' ('.$error::class.'): '.$error->getMessage()."\n");
    exit(1);
} finally {
    $app->forgetInstance(AttendanceWriter::class);
    $app->make('auth')->forgetGuards();
    foreach ($previous as $name => $value) { $config->set($name, $value); }
    $app->forgetInstance('encrypter');
    unset($GLOBALS['audit_actor'], $GLOBALS['audit_reason'], $GLOBALS['_SESSION']);
    foreach ($globals as $name => $value) { $GLOBALS[$name] = $value; }
}
