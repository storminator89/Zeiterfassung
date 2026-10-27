<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// One coarse lifecycle on new synthetic private files; no path/account arguments.
use App\Auth\LegacyUser;
use App\Chrono\LocalWebAttendance;
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
$phase = 'inactive resolution and authenticated operation boundary';
$ensure = static function (bool $condition): void {
    if (! $condition) {
        throw new RuntimeException('Coarse web attendance invariant failed.');
    }
};
$refuses = static function (Closure $operation, string $type) use ($ensure): Throwable {
    try {
        $operation();
    } catch (Throwable $error) {
        $ensure($error instanceof $type);
        return $error;
    }
    throw new RuntimeException('Expected refusal did not occur.');
};
$previous = ['app.key' => $config->get('app.key'), 'session.driver' => $config->get('session.driver')];
$globals = array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason']));

try {
    $ensure($argc === 1 && ! $config->has('database.connections.chrono')
        && $database->getConnections() === [] && ! $app->bound(AttendanceWriter::class));
    $service = $app->make(LocalWebAttendance::class);
    $ensure($database->getConnections() === [] && ! $app->bound(AttendanceWriter::class));
    $config->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    $config->set('session.driver', 'array');
    $guard = $app->make('auth')->guard('web');
    $refuses(fn () => $service->transition('synthetic-web-start-0001', 'start'), AuthenticationException::class);
    $actor = ['id' => 1, 'role' => 'user', 'auth_source' => 'local'];
    $guard->setUser((new LegacyUser)->newFromBuilder($actor, 'chrono'));
    $refuses(fn () => $service->transition('synthetic-web-start-0001', 'start'), LogicException::class);
    $ensure($database->getConnections() === []);

    $phase = 'known-v2 synthetic offline prerequisite';
    $directory = sys_get_temp_dir().'/chrono-web-attendance-'.bin2hex(random_bytes(8));
    $ensure(mkdir($directory, 0700));
    $mapping = json_decode(file_get_contents($root.'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
    $source = (new SQLiteConnector)->connect(['database' => ':memory:', 'busy_timeout' => 5000]);
    $build = require dirname(__DIR__).'/database/fixtures/legacy_v2.php';
    $build($source, $mapping, true);
    $source->exec('VACUUM INTO '.$source->quote($directory.'/original.sqlite'));
    $source = null;
    $ensure(copy($directory.'/original.sqlite', $directory.'/target.sqlite')
        && copy($directory.'/original.sqlite', $directory.'/backup.sqlite'));
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
    // Trusted composition root ONLY. No runtime provider/path factory is added.
    // Each call gets a newly admitted dedicated PDO and the unchanged #75 writer.
    $app->bind(AttendanceWriter::class, function ($app) use ($open, &$opened): AttendanceWriter {
        [$pdo, $configuration] = $open();
        $opened++;
        return new AttendanceWriter($pdo, $configuration, $app->make(AuditContext::class), $app->make(AuditFunctions::class));
    });
    foreach ([['auth_source' => 'ldap'], ['role' => 'unknown']] as $invalid) {
        $guard->setUser((new LegacyUser)->newFromBuilder(array_replace($actor, $invalid), 'chrono'));
        $refuses(fn () => $service->transition('synthetic-web-start-0001', 'start'), AuthenticationException::class);
    }
    $ensure($opened === 0);
    [$observer] = $open();
    $current = att_one($observer, 'SELECT id,role,auth_source FROM users WHERE id=1');
    $guard->setUser((new LegacyUser)->newFromBuilder($current, 'chrono'));
    $GLOBALS['audit_actor'] = null;
    $GLOBALS['audit_reason'] = 'synthetic previous context';

    $phase = 'own start/replay/payload and expected-record conflict';
    $start = $service->transition('synthetic-web-start-0001', 'start');
    $ensure($start['success'] && $start['state'] === 'working');
    $before = SourceSnapshot::capture($observer, $mapping);
    $ensure($service->transition('synthetic-web-start-0001', 'start') === $start);
    $mismatch = $refuses(fn () => $service->transition('synthetic-web-start-0001', 'pause_start'), AttendanceError::class);
    $wrongRecord = $refuses(fn () => $service->transition('synthetic-web-wrong-0001', 'pause_start', false, $start['record_id'] + 1), AttendanceError::class);
    $ensure($mismatch->status === 409 && $wrongRecord->status === 409
        && SourceSnapshot::capture($observer, $mapping) === $before);

    $phase = 'pause/resume/end and lost-response replay';
    $ensure($service->transition('synthetic-web-pause-0001', 'pause_start', false, $start['record_id'])['state'] === 'paused');
    $before = SourceSnapshot::capture($observer, $mapping);
    $unconfirmed = $refuses(fn () => $service->transition('synthetic-web-unconfirmed-0001', 'end', false, $start['record_id']), AttendanceError::class);
    $ensure($unconfirmed->status === 409 && SourceSnapshot::capture($observer, $mapping) === $before);
    $ensure($service->transition('synthetic-web-resume-0001', 'pause_end', false, $start['record_id'])['state'] === 'working');
    $end = $service->transition('synthetic-web-end-000001', 'end', false, $start['record_id']);
    $ensure($end['state'] === 'absent');
    $before = SourceSnapshot::capture($observer, $mapping);
    $ensure($service->transition('synthetic-web-end-000001', 'end', false, $start['record_id']) === $end
        && $service->transition('synthetic-web-start-0001', 'start') === $start
        && SourceSnapshot::capture($observer, $mapping) === $before);
    $ensure($observer->query("SELECT count(*) FROM attendance_events WHERE source='web:1' AND user_id=1")->fetchColumn() === 4
        && $observer->query('SELECT count(*) FROM attendance_events')->fetchColumn() === 4
        && $observer->query('SELECT count(*) FROM work_sessions WHERE user_id=1 AND ended_at IS NOT NULL')->fetchColumn() === 1
        && $observer->query('SELECT count(*) FROM break_intervals WHERE ended_at IS NOT NULL')->fetchColumn() === 1
        && $observer->query("SELECT count(*) FROM audit_log WHERE actor='web:1:user:1'")->fetchColumn() > 0
        && $observer->query('PRAGMA quick_check')->fetchColumn() === 'ok');
    $ensure($GLOBALS['audit_actor'] === null && $GLOBALS['audit_reason'] === 'synthetic previous context');
    $ensure($database->getConnections() === [] && ! $config->has('database.connections.chrono')
        && hash_file('sha256', $directory.'/original.sqlite') === $originalHash
        && hash_file('sha256', $directory.'/backup.sqlite') === $originalHash);
    $observer = null;
    foreach (['-journal', '-wal', '-shm'] as $suffix) {
        $ensure(! file_exists($path.$suffix));
    }
    echo json_encode([
        'passed' => true,
        'groups' => ['lazy/authenticated writer boundary', 'own web source and unchanged replay/conflict', 'pause/resume/end persisted once', 'no default connection or leaked audit context'],
        'synthetic_private_files_only' => true,
        'runtime_auth_http_ui_acceptance' => false,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$phase.' ('.$error::class.").\n");
    exit(1);
} finally {
    $app->forgetInstance(AttendanceWriter::class);
    $app->make('auth')->forgetGuards();
    foreach ($previous as $name => $value) {
        $config->set($name, $value);
    }
    $app->forgetInstance('encrypter');
    unset($GLOBALS['audit_actor'], $GLOBALS['audit_reason']);
    foreach ($globals as $name => $value) {
        $GLOBALS[$name] = $value;
    }
}
