<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// One coarse admitted synthetic correction/audit flow; no HTTP or path arguments.
use App\Auth\LegacyUser;
use App\Chrono\LocalWebCorrections;
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
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = $app->make('config');
$database = $app->make('db');
$phase = 'inactive authenticated service boundary';
$ensure = static function (bool $condition): void {
    if (! $condition) { throw new RuntimeException('Coarse web correction invariant failed.'); }
};
$refuses = static function (Closure $operation, string $type) use ($ensure): Throwable {
    try { $operation(); }
    catch (Throwable $error) { $ensure($error instanceof $type); return $error; }
    throw new RuntimeException('Expected correction refusal did not occur.');
};
$previous = ['app.key' => $config->get('app.key'), 'session.driver' => $config->get('session.driver')];
$globals = array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION']));

try {
    $ensure($argc === 1 && ! $config->has('database.connections.chrono')
        && $database->getConnections() === [] && ! $app->bound(AttendanceWriter::class));
    $service = $app->make(LocalWebCorrections::class);
    $ensure($database->getConnections() === [] && ! $app->bound(AttendanceWriter::class));
    $config->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    $config->set('session.driver', 'array');
    $guard = $app->make('auth')->guard('web');
    $refuses(fn () => $service->apply([]), AuthenticationException::class);
    $guard->setUser((new LegacyUser)->newFromBuilder(['id' => 1, 'role' => 'user', 'auth_source' => 'local'], 'chrono'));
    $refuses(fn () => $service->apply([]), LogicException::class);

    $phase = 'synthetic offline admission and fresh operation writers';
    $directory = sys_get_temp_dir().'/chrono-web-corrections-'.bin2hex(random_bytes(8));
    $ensure(mkdir($directory, 0700));
    $mapping = json_decode(file_get_contents($root.'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
    $source = (new SQLiteConnector)->connect(['database' => ':memory:', 'busy_timeout' => 5000]);
    $build = require dirname(__DIR__).'/database/fixtures/legacy_v2.php';
    $build($source, $mapping, true);
    $GLOBALS['audit_actor'] = 'fixture:corrections';
    $GLOBALS['audit_reason'] = 'Synthetic direct-team setup';
    $source->exec("UPDATE users SET supervisor_id=2 WHERE id=1; UPDATE users SET role='supervisor' WHERE id=2");
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
    $opened = 0;
    $failDecision = false;
    $lastPdo = null;
    // Only this synthetic composition binds the already-admitted #75 writer.
    $app->bind(AttendanceWriter::class, function ($app) use ($open, &$opened, &$failDecision, &$lastPdo): AttendanceWriter {
        [$pdo, $configuration] = $open();
        $writer = new AttendanceWriter($pdo, $configuration, $app->make(AuditContext::class), $app->make(AuditFunctions::class));
        $opened++;
        $lastPdo = $pdo;
        if ($failDecision) {
            // Connection-local fault, installed after admission; no schema change.
            $pdo->exec("CREATE TEMP TRIGGER synthetic_decision_failure BEFORE UPDATE OF status ON time_adjustments
                WHEN NEW.status='approved' BEGIN SELECT RAISE(ABORT,'Synthetic final decision failure'); END");
        }
        return $writer;
    });
    foreach ([['auth_source' => 'ldap', 'role' => 'user'], ['auth_source' => 'local', 'role' => 'unknown']] as $invalid) {
        $guard->setUser((new LegacyUser)->newFromBuilder(['id' => 1] + $invalid, 'chrono'));
        $refuses(fn () => $service->apply([]), AuthenticationException::class);
    }
    $ensure($opened === 0);
    [$observer] = $open();
    $as = static function (int $user) use ($observer, $guard): void {
        $guard->setUser((new LegacyUser)->newFromBuilder(att_one($observer, 'SELECT id,role,auth_source FROM users WHERE id=?', [$user]), 'chrono'));
    };
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
    $request = ['action' => 'request', 'record_id' => '42', 'start' => '2026-10-23T08:00+02:00',
        'end' => '2026-10-23T14:00+02:00', 'pause' => '30', 'reason' => 'Synthetic correction reason',
        'user_id' => 2, 'role' => 'admin', 'source' => 'untrusted', 'audit_actor' => 'untrusted'];
    $review = ['action' => 'review', 'id' => 1, 'decision' => 'approved', 'review_reason' => 'Synthetic independent review'];

    $phase = 'own request, other authorized review and exact scoped audit';
    $original = att_one($observer, 'SELECT * FROM zeiterfassung WHERE id=42');
    $auditCursor = (int) $observer->query('SELECT MAX(id) FROM audit_log')->fetchColumn();
    $as(1); $invoke($request);
    $ensure(att_one($observer, 'SELECT user_id,original_json,status FROM time_adjustments WHERE id=1')
        === ['user_id' => 1, 'original_json' => json_encode($original), 'status' => 'pending']);
    $before = SourceSnapshot::capture($observer, $mapping);
    $self = $refuses(fn () => $invoke($review), AttendanceError::class);
    $ensure($self->status === 403 && SourceSnapshot::capture($observer, $mapping) === $before);
    $as(2); $invoke($review);
    $ensure(att_one($observer, 'SELECT status,reviewer_id FROM time_adjustments WHERE id=1') === ['status' => 'approved', 'reviewer_id' => 2]
        && att_one($observer, 'SELECT endzeit FROM zeiterfassung WHERE id=42')['endzeit'] === '2026-10-23T14:00:00+02:00');
    $audit = $observer->query('SELECT DISTINCT actor,reason FROM audit_log WHERE id>'.$auditCursor.' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $ensure($audit === [['actor' => 'web:1', 'reason' => 'Korrekturantrag: Synthetic correction reason'],
        ['actor' => 'web:2', 'reason' => 'Korrektur #1: Synthetic independent review']]);

    $phase = 'failed decision rolls back projection and audit, restoring contexts';
    $request['end'] = '2026-10-23T15:00+02:00';
    $as(1); $invoke($request);
    $before = SourceSnapshot::capture($observer, $mapping);
    $review['id'] = 2;
    $as(2); $failDecision = true;
    $refuses(fn () => $invoke($review), PDOException::class);
    $ensure(! $lastPdo->inTransaction() && SourceSnapshot::capture($observer, $mapping) === $before);
    $refuses(fn () => $context->actor(), LogicException::class);
    $ensure($GLOBALS['audit_actor'] === null && $GLOBALS['audit_reason'] === 'synthetic previous native reason'
        && $GLOBALS['_SESSION'] === ['synthetic_marker' => 'unchanged'] && $database->getConnections() === []
        && ! $config->has('database.connections.chrono') && hash_file('sha256', $directory.'/original.sqlite') === $originalHash
        && hash_file('sha256', $directory.'/backup.sqlite') === $originalHash);
    echo json_encode(['passed' => true, 'groups' => ['inactive trusted-guard/admission boundary',
        'request and independent review with exact scoped audit', 'projection/decision/audit rollback and context restoration'],
        'synthetic_private_files_only' => true, 'runtime_auth_http_ui_acceptance' => false], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
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
