<?php

// One coarse Laravel HttpKernel request rehearsal, not a server/UI/full suite.
use App\Api\LegacyApiIdentity;
use App\Api\LegacyApiRoutes;
use App\Api\LegacyApiTokenKey;
use App\LegacyDatabase\AuditContext;
use App\LegacyDatabase\AuditFunctions;
use App\LegacyDatabase\MemoryOnly;
use App\LegacyDatabase\SourceSnapshot;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$config = $app->make('config');
$database = $app->make('db');
$phase = 'inactive boot, explicit admissions and preflight';
$ensure = static function (bool $condition): void {
    if (! $condition) {
        throw new RuntimeException('Coarse API rehearsal invariant failed.');
    }
};
$globals = array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION']));
$previousConnections = $config->get('database.connections');

try {
    $ensure($argc === 1 && ! $config->has('database.connections.chrono') && $database->getConnections() === []
        && ! $app->bound(LegacyApiTokenKey::class) && ! defined('CHRONO_SECURITY_LOADED'));
    $ensure(count($app->make('router')->getRoutes()) === 1); // Only /up ships.
    $app->make(LegacyApiRoutes::class)->registerCandidate($app->make('router'));
    $request = static function (string $method, string $uri, ?string $token = null, array $server = []) use ($app) {
        $server += ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        return $app->make(Kernel::class)->handle(Request::create($uri, $method, [], [], [], $server));
    };
    $preflight = $request('OPTIONS', '/api.php/unknown');
    $ensure($preflight->getStatusCode() === 200 && $preflight->getContent() === ''
        && $preflight->headers->get('Access-Control-Allow-Origin') === '*'
        && $preflight->headers->get('Access-Control-Allow-Methods') === 'GET, POST, OPTIONS, DELETE'
        && $preflight->headers->get('Access-Control-Allow-Headers') === 'Content-Type, Authorization'
        && ! $preflight->headers->has('Set-Cookie') && $database->getConnections() === []);
    $missing = $request('GET', '/api.php/users');
    $ensure($missing->getStatusCode() === 401 && $missing->getContent() === 'Invalid bearer token.'
        && $missing->headers->get('Content-Type') === 'text/plain; charset=UTF-8'
        && $missing->headers->get('Access-Control-Allow-Origin') === '*' && $database->getConnections() === []);
    try {
        $app->make(LegacyApiIdentity::class)->authenticate(Request::create('/api.php/users', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer a.b.c']));
        throw new RuntimeException('Unadmitted signing key was accepted.');
    } catch (LogicException) {
        $ensure($database->getConnections() === []);
    }

    // The only signing secret is a process-local throwaway, never read/saved.
    $secret = bin2hex(random_bytes(32));
    $app->instance(LegacyApiTokenKey::class, new class($secret) implements LegacyApiTokenKey {
        public function __construct(private string $value) {}
        public function secret(): string { return $this->value; }
    });
    $config->set('database.connections.chrono', [
        'driver' => 'chrono_fixture', 'database' => ':memory:', 'prefix' => '',
        'foreign_key_constraints' => false, 'busy_timeout' => 5000,
        'journal_mode' => null, 'synchronous' => null, 'transaction_mode' => 'DEFERRED',
    ]);
    $pdo = $database->connection('chrono')->getPdo();
    MemoryOnly::connection($pdo);
    $mapping = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
    $build = require dirname(__DIR__).'/database/fixtures/legacy_v2.php';
    $build($pdo, $mapping, true);
    $app->make(AuditFunctions::class)->register($pdo);
    $app->make(AuditContext::class)->run('fixture:api-rehearsal', 'Synthetic API fixture only', function () use ($pdo): void {
        $pdo->exec("UPDATE users SET password='synthetic-version-a';
            UPDATE users SET auth_source='ldap' WHERE id=2;
            INSERT INTO zeiterfassung(id,startzeit,endzeit,pause,beschreibung,standort,user_id,absence_kind,planned_minutes) VALUES
            (44,'2026-10-25T00:30:00+02:00','2026-10-25T04:30:00+01:00',30,'<Synthetic> / Unicode π','office',1,NULL,NULL),
            (45,'2026-10-24T08:00:00+02:00','2026-10-24T09:00:00+02:00',0,'Synthetic other account','office',2,NULL,NULL);");
    });
    require_once dirname(__DIR__, 2).'/lib/api_tokens.php';
    // Fixture signing only, matching the existing issuer's HS256 bytes. This is
    // not a new runtime issuer or an alternative token verification engine.
    $token = static function (int $user, array $override = []) use ($secret): string {
        $head = base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = base64UrlEncode(json_encode($override + [
            'iss' => 'localhost', 'aud' => 'localhost', 'iat' => time(), 'exp' => time() + 3600,
            'user_id' => $user, 'ver' => hash('sha256', 'synthetic-version-a'),
        ]));
        return $head.'.'.$body.'.'.base64UrlEncode(hash_hmac('SHA256', $head.'.'.$body, $secret, true));
    };
    $member = $token(1);
    $directoryAdmin = $token(2);
    $before = SourceSnapshot::capture($pdo, $mapping);

    $phase = 'representative extension own identity and Berlin start-day paging';
    $identity = $request('GET', '/api.php/users?user_id=2', $member);
    $expected = ['success' => true, 'data' => $pdo->query('SELECT id,username,email,role FROM users WHERE id=1')->fetchAll(PDO::FETCH_ASSOC)];
    $ensure($identity->getStatusCode() === 200 && $identity->getContent() === json_encode($expected)
        && ! $identity->headers->has('Set-Cookie'));
    $entries = $request('GET', '/api.php/timeentries?start_date=2026-10-25&end_date=2026-10-25&limit=1&offset=0&user_id=2', $member);
    $rows = $pdo->query("SELECT * FROM zeiterfassung WHERE user_id=1 AND id=44")->fetchAll(PDO::FETCH_ASSOC);
    $ensure($entries->getStatusCode() === 200 && array_column($rows, 'id') === [44]
        && $entries->getContent() === json_encode(['success' => true, 'data' => $rows, 'meta' => ['total' => 1, 'filtered' => 1, 'limit' => 1, 'offset' => 0]]));
    $previousDay = json_decode($request('GET', '/api.php/timeentries?start_date=2026-10-24&end_date=2026-10-24', $member)->getContent(), true, 512, JSON_THROW_ON_ERROR);
    $ensure($previousDay['data'] === [] && $previousDay['meta']['total'] === 0);
    $adminRows = json_decode($request('GET', '/api.php/timeentries?user_id=1', $directoryAdmin)->getContent(), true, 512, JSON_THROW_ON_ERROR);
    $ensure(array_column($adminRows['data'], 'user_id') === [2]); // API accepts LDAP identity, never broad admin scope.
    $ensure(SourceSnapshot::capture($pdo, $mapping) === $before);

    $phase = 'token rejection, current account revocation and no web fallback';
    foreach ([$member.'x', $token(1, ['exp' => time() - 1]), $token(1, ['aud' => 'other'])] as $invalid) {
        $response = $request('GET', '/api.php/users', $invalid);
        $ensure($response->getStatusCode() === 401 && $response->getContent() === 'Invalid or expired token.');
    }
    $ensure($request('GET', '/api.php/users')->getStatusCode() === 401); // No cached prior identity.
    $app->make(AuditContext::class)->run('fixture:api-rehearsal', 'Synthetic password revocation', fn () => $pdo->exec("UPDATE users SET password='synthetic-version-b' WHERE id=1"));
    $revoked = $request('GET', '/api.php/users', $member);
    $ensure($revoked->getStatusCode() === 401 && $revoked->getContent() === 'Account deleted or credentials changed.');
    $before = SourceSnapshot::capture($pdo, $mapping);

    $phase = 'legacy errors, no historical CRUD and unchanged snapshot';
    $invalidDate = $request('GET', '/api.php/timeentries?start_date=', $directoryAdmin);
    $ensure($invalidDate->getStatusCode() === 400 && $invalidDate->getContent() === json_encode(['success' => false, 'message' => 'Invalid start_date format. Use YYYY-MM-DD']));
    $offsetOnly = $request('GET', '/api.php/timeentries?offset=1', $directoryAdmin);
    $ensure($offsetOnly->getStatusCode() === 200
        && $offsetOnly->getContent() === json_encode(['success' => true, 'data' => [], 'meta' => ['total' => 1, 'filtered' => 0, 'limit' => 100, 'offset' => 1]]));
    $ensure($request('GET', '/api.php/unknown')->getStatusCode() === 400
        && $request('GET', '/api.php/users/')->getStatusCode() === 400
        && $request('GET', '/api.php/%75sers')->getStatusCode() === 400
        && $request('HEAD', '/api.php/users')->getStatusCode() === 405
        && $request('PUT', '/api.php/users')->getStatusCode() === 405
        && $request('POST', '/api.php/users', null, ['HTTP_X_HTTP_METHOD_OVERRIDE' => 'GET'])->getStatusCode() === 400);
    $denied = $request('DELETE', '/api.php/timeentry/45', $directoryAdmin);
    $ensure($denied->getStatusCode() === 409 && $denied->getContent() === 'Use an approved correction request to cancel a record.'
        && $request('DELETE', '/api.php/timeentry/45/', $directoryAdmin)->getStatusCode() === 400);
    $ensure($request('POST', '/api.php/login')->getStatusCode() === 400
        && $request('POST', '/api.php/workentry')->getStatusCode() === 401); // Alias still requires current bearer identity.
    $ensure(SourceSnapshot::capture($pdo, $mapping) === $before && ! $pdo->inTransaction()
        && $database->connection('chrono')->transactionLevel() === 0 && array_keys($database->getConnections()) === ['chrono']
        && $app->make('auth')->getDefaultDriver() === 'web' && ! defined('CHRONO_SECURITY_LOADED')
        && array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION'])) === $globals);
    echo json_encode([
        'passed' => true,
        'groups' => ['inactive admissions and no-IO preflight', 'extension-style own reads and Berlin start-day paging', 'token and current account rejection', 'legacy wire errors and read-only snapshot'],
        'synthetic_memory_only' => true, 'laravel_http_kernel_requests' => true,
        'live_endpoint_switch' => false, 'login_writes_ui_real_distribution_acceptance' => false,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$phase.' ('.$error::class.").\n");
    exit(1);
} finally {
    $app->make('auth')->forgetGuards();
    $database->purge('chrono');
    $config->set('database.connections', $previousConnections);
    $app->forgetInstance(LegacyApiTokenKey::class);
}
