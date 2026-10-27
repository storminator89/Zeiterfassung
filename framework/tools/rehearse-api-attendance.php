<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// One coarse synthetic login -> token -> own reads -> booking/replay/end flow.
use App\Api\LaravelApiLoginBudget;
use App\Api\LegacyApiLogin;
use App\Api\LegacyApiLoginBudget;
use App\Api\LegacyApiRoutes;
use App\Api\LegacyApiTokenKey;
use App\Chrono\ApiAttendance;
use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\AuditContext;
use App\LegacyDatabase\AuditFunctions;
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\SourceSnapshot;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository as Cache;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Http\Request;

umask(0077);
$root = dirname(__DIR__, 2);
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$config = $app->make('config');
$database = $app->make('db');
$phase = 'inactive boot and explicit admissions';
$ensure = static function (bool $condition): void {
    if (! $condition) { throw new RuntimeException('Coarse API lifecycle invariant failed.'); }
};
$globals = array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION']));
$previousConnections = $config->get('database.connections');

try {
    $ensure($argc === 1 && ! $config->has('database.connections.chrono') && $database->getConnections() === []
        && ! $app->bound(AttendanceWriter::class) && ! $app->bound(LegacyApiTokenKey::class)
        && ! $app->bound(LegacyApiLoginBudget::class) && count($app->make('router')->getRoutes()) === 1);
    $app->make(ApiAttendance::class); // Lazy service resolution opens nothing.
    try {
        $app->make(LegacyApiLogin::class)->issue('synthetic', 'synthetic', '192.0.2.1');
        throw new RuntimeException('Unadmitted login budget was accepted.');
    } catch (LogicException) {
        $ensure($database->getConnections() === []);
    }
    $app->make(LegacyApiRoutes::class)->registerCandidate($app->make('router'));
    $request = static function (string $method, string $uri, ?array $body = null, ?string $token = null) use ($app) {
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '192.0.2.1'];
        if ($token !== null) { $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token; }
        // Same flow also proves a raw POST remains POST, including response
        // bytes, rather than honoring Laravel's unrelated method override.
        if ($method === 'POST' && $uri === '/api.php/login') { $server['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'HEAD'; }
        return $app->make(Kernel::class)->handle(Request::create($uri, $method, [], [], [], $server, $body === null ? '' : json_encode($body)));
    };
    $ensure($request('OPTIONS', '/api.php/attendance')->getStatusCode() === 200
        && $request('POST', '/api.php/attendance', ['event_id' => 'synthetic-api-start-0001', 'action' => 'start'])->getStatusCode() === 401
        && $database->getConnections() === []);

    $phase = 'fresh synthetic admitted known-v2 private copy';
    $directory = sys_get_temp_dir().'/chrono-api-attendance-'.bin2hex(random_bytes(8));
    $ensure(mkdir($directory, 0700));
    $mapping = json_decode(file_get_contents($root.'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
    $source = (new SQLiteConnector)->connect(['database' => ':memory:', 'busy_timeout' => 5000]);
    $build = require dirname(__DIR__).'/database/fixtures/legacy_v2.php';
    $build($source, $mapping, true);
    $source->exec('VACUUM INTO '.$source->quote($directory.'/original.sqlite'));
    $source = null;
    $ensure(copy($directory.'/original.sqlite', $directory.'/target.sqlite') && copy($directory.'/original.sqlite', $directory.'/backup.sqlite'));
    $originalHash = hash_file('sha256', $directory.'/original.sqlite');
    $path = $directory.'/target.sqlite';
    $ensure($app->make(ConsoleKernel::class)->call('chrono:legacy-v2', [
        'action' => 'adopt', '--target' => $path, '--backup' => $directory.'/backup.sqlite', '--offline' => true,
    ]) === 0);
    $open = static function () use ($root, $path): array {
        $files = new OfflineFiles([$root, $root.'/framework/public']);
        $configuration = $files->readWriteConfiguration($path, $files->snapshot($path));
        return [(new SQLiteConnector)->connect($configuration), $configuration];
    };
    [$observer, $configuration] = $open();
    $app->make(AuditFunctions::class)->register($observer);
    $username = ' Synthetic member ';
    $password = ' Synthetic-only passphrase ';
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
    $app->make(AuditContext::class)->run('fixture:api-flow', 'Synthetic credentials only', function () use ($observer, $username, $hash): void {
        att_exec($observer, 'UPDATE users SET username=?,password=? WHERE id=1', [$username, $hash]);
        att_exec($observer, "UPDATE users SET password=?,auth_source='ldap' WHERE id=2", [$hash]);
    });
    $configuration['transaction_mode'] = 'DEFERRED';
    $config->set('database.connections.chrono', $configuration);
    $secret = bin2hex(random_bytes(32)); // Never read or persist signing keys.
    $app->instance(LegacyApiTokenKey::class, new class($secret) implements LegacyApiTokenKey {
        public function __construct(private string $value) {}
        public function secret(): string { return $this->value; }
    });
    $limiter = new RateLimiter(new Cache(new ArrayStore));
    $app->instance(LegacyApiLoginBudget::class, new LaravelApiLoginBudget($limiter));
    $opened = 0;
    $app->bind(AttendanceWriter::class, function ($app) use ($open, &$opened): AttendanceWriter {
        [$pdo, $configuration] = $open();
        $opened++;
        return new AttendanceWriter($pdo, $configuration, $app->make(AuditContext::class), $app->make(AuditFunctions::class));
    });

    $phase = 'raw local login, unchanged password, issued token and own reads';
    $before = SourceSnapshot::capture($observer, $mapping);
    $ensure($request('POST', '/api.php/login', ['username' => $username, 'password' => 'wrong'])->getStatusCode() === 401
        && $request('POST', '/api.php/login', ['username' => 'Synthetic reviewer', 'password' => $password])->getStatusCode() === 401);
    $login = $request('POST', '/api.php/login', ['username' => $username, 'password' => $password]);
    $loginData = json_decode($login->getContent(), true, 512, JSON_THROW_ON_ERROR);
    $ensure($login->getStatusCode() === 200 && $loginData['success'] === true && ! $login->headers->has('Set-Cookie'));
    $token = $loginData['token'];
    require_once $root.'/lib/api_tokens.php';
    $claims = validateJWT($token, $secret);
    $ensure($claims !== false && $claims['user_id'] === 1 && $claims['exp'] - $claims['iat'] === 3600
        && $claims['ver'] === hash('sha256', $hash) && SourceSnapshot::capture($observer, $mapping) === $before && $opened === 0);
    $identity = json_decode($request('GET', '/api.php/users?user_id=2', null, $token)->getContent(), true, 512, JSON_THROW_ON_ERROR);
    $entries = json_decode($request('GET', '/api.php/timeentries?user_id=2', null, $token)->getContent(), true, 512, JSON_THROW_ON_ERROR);
    $ensure(array_column($identity['data'], 'id') === [1] && array_unique(array_column($entries['data'], 'user_id')) === [1]);

    $phase = 'one API-owned booking, exact replay and strict pause/end';
    $startInput = ['event_id' => 'synthetic-api-start-0001', 'action' => 'start', 'user_id' => 2, 'source' => 'web:2', 'expected_record' => 999];
    $start = $request('POST', '/api.php/attendance', $startInput, $token);
    $started = json_decode($start->getContent(), true, 512, JSON_THROW_ON_ERROR);
    $ensure($start->getStatusCode() === 200 && $started['state'] === 'working');
    $before = SourceSnapshot::capture($observer, $mapping);
    $ensure($request('POST', '/api.php/attendance', $startInput, $token)->getContent() === $start->getContent()
        && SourceSnapshot::capture($observer, $mapping) === $before);
    $pause = $request('POST', '/api.php/attendance', ['event_id' => 'synthetic-api-pause-0001', 'action' => 'pause_start'], $token);
    $ensure($pause->getStatusCode() === 200 && json_decode($pause->getContent(), true)['state'] === 'paused');
    $endInput = ['event_id' => 'synthetic-api-end-00001', 'action' => 'end', 'confirm_end_pause' => '1'];
    $ensure($request('POST', '/api.php/attendance', $endInput, $token)->getStatusCode() === 409);
    $endInput['confirm_end_pause'] = true;
    $end = $request('POST', '/api.php/attendance', $endInput, $token);
    $ensure($end->getStatusCode() === 200 && json_decode($end->getContent(), true)['state'] === 'absent'
        && $request('POST', '/api.php/attendance', $endInput, $token)->getContent() === $end->getContent());
    $events = $observer->query('SELECT source,user_id FROM attendance_events ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $ensure(count($events) === 3 && array_unique(array_column($events, 'source')) === ['api:1']
        && array_unique(array_column($events, 'user_id')) === [1]);

    $phase = 'unauthorized and revoked retries never resolve writer; shared budget refuses';
    $before = SourceSnapshot::capture($observer, $mapping);
    $writerCount = $opened;
    $ensure($request('POST', '/api.php/attendance', $endInput)->getStatusCode() === 401
        && $opened === $writerCount && SourceSnapshot::capture($observer, $mapping) === $before);
    $app->make(AuditContext::class)->run('fixture:api-flow', 'Synthetic revocation', fn () => att_exec($observer, "UPDATE users SET password='synthetic-revoked' WHERE id=1"));
    $ensure($request('POST', '/api.php/attendance', $endInput, $token)->getStatusCode() === 401 && $opened === $writerCount);
    $bucket = hash('sha256', 'user:'.strtolower($username));
    while ($limiter->attempts($bucket) < 15) { $limiter->hit($bucket, 600); }
    $limited = $request('POST', '/api.php/login', ['username' => $username, 'password' => $password]);
    $ensure($limited->getStatusCode() === 429 && $limited->headers->get('Retry-After') === '600'
        && $limited->getContent() === 'Too many login attempts. Try again later.');
    $ensure(array_keys($database->getConnections()) === ['chrono'] && ! $observer->inTransaction()
        && $database->connection('chrono')->transactionLevel() === 0 && $app->make('auth')->getDefaultDriver() === 'web'
        && hash_file('sha256', $directory.'/original.sqlite') === $originalHash
        && hash_file('sha256', $directory.'/backup.sqlite') === $originalHash
        && ! defined('CHRONO_SECURITY_LOADED') && ! defined('JWT_SECRET')
        && array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION'])) === $globals);
    echo json_encode([
        'passed' => true,
        'groups' => ['inactive admissions and private synthetic adoption', 'local login issued token and own reads', 'API source booking replay pause end', 'unauthorized revocation and admitted budget refusal'],
        'synthetic_private_files_only' => true, 'laravel_http_kernel_requests' => true,
        'persistent_shared_budget_proven' => false, 'live_route_key_or_database_activation' => false,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$phase.' ('.$error::class.").\n");
    exit(1);
} finally {
    $app->make('auth')->forgetGuards();
    $database->purge('chrono');
    $config->set('database.connections', $previousConnections);
    foreach ([LegacyApiTokenKey::class, LegacyApiLoginBudget::class, AttendanceWriter::class] as $binding) { $app->offsetUnset($binding); }
}
