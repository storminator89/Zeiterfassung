<?php

/**
 * Explicit developer-only NEW disposable installation journey; no HTTP listener.
 * Never accepts an existing instance, database, key, account or operator config.
 */
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\RuntimeAdmission;
use App\LegacyDatabase\RuntimeDatabase;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

require dirname(__DIR__).'/vendor/autoload.php';

function fresh_rehearsal_require(bool $condition): void
{
    if (! $condition) { throw new RuntimeException('Disposable installation assertion failed.'); }
}

function fresh_rehearsal_release(?Application &$app): void
{
    if ($app === null) { return; }
    if ($app->resolved('db')) {
        foreach (array_keys($app->make('db')->getConnections()) as $name) { $app->make('db')->purge($name); }
    }
    $app->forgetScopedInstances();
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
    \Illuminate\Foundation\Bootstrap\HandleExceptions::flushState();
    $app->flush();
    $app = null;
    gc_collect_cycles();
}

function fresh_rehearsal_remove(string $directory): void
{
    $entries = @scandir($directory);
    fresh_rehearsal_require(is_array($entries));
    foreach (array_diff($entries, ['.', '..']) as $name) {
        $path = $directory.'/'.$name;
        if (is_dir($path) && ! is_link($path)) { fresh_rehearsal_remove($path); }
        else { fresh_rehearsal_require(@unlink($path)); }
    }
    fresh_rehearsal_require(@rmdir($directory));
}

$root = dirname(__DIR__, 2);
$parent = null;
$app = null;
$pdo = $connection = null;
$phase = 'explicit disposable-only selection';
$exit = 1;
$report = ['scope' => 'new_disposable_kernel_installation', 'server_started' => false,
    'docker_tls_browser_production_acceptance' => false];
$mask = umask(0077);
try {
    fresh_rehearsal_require(PHP_SAPI === 'cli' && $argc === 2 && $argv[1] === '--disposable-new'
        && function_exists('posix_geteuid') && posix_geteuid() !== 0);
    foreach (['APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE',
        'APP_EVENTS_CACHE', 'APP_KEY', 'FRAMEWORK_APP_KEY', 'FRAMEWORK_PREVIOUS_KEYS'] as $name) {
        fresh_rehearsal_require(getenv($name) === false && ! isset($_ENV[$name]) && ! isset($_SERVER[$name]));
    }
    $parent = sys_get_temp_dir().'/chrono-installation-rehearsal-'.bin2hex(random_bytes(12));
    fresh_rehearsal_require(mkdir($parent, 0700));
    $parentIdentity = lstat($parent);
    foreach (['bootstrap', 'bootstrap/cache', 'storage', 'storage/framework', 'storage/framework/views',
        'storage/framework/cache', 'storage/framework/cache/data', 'storage/framework/cache/locks',
        'storage/framework/sessions', 'storage/logs'] as $directory) {
        fresh_rehearsal_require(mkdir($parent.'/'.$directory, 0700));
    }

    // Original application builder/providers/config/routes. Package/service
    // manifests, scratch storage and .env lookup use only this NEW parent.
    $boot = static function (?string $instance = null) use ($root, $parent): Application {
        $application = require $root.'/framework/bootstrap/app.php';
        $application->useEnvironmentPath($parent);
        $application->useBootstrapPath($parent.'/bootstrap');
        $application->useStoragePath($parent.'/storage');
        $application->beforeBootstrapping(RegisterProviders::class, static function ($application) use ($instance): void {
            fresh_rehearsal_require(! $application->configurationIsCached() && ! is_file($application->getCachedRoutesPath()));
            $config = $application->make('config');
            fresh_rehearsal_require($config->get('chrono_fresh_web.enabled') === false
                && $config->get('chrono_runtime.read_enabled') === false
                && $config->get('chrono_runtime.write_enabled') === false
                && $config->get('chrono_api.enabled') === false
                && $config->get('chrono_runtime.admission.path') === null
                && $config->get('chrono_ldap.enabled') === false
                && $config->get('chrono_ldap_sync.fresh.enabled') === false
                && $config->get('chrono_terminal.http_enabled') === false
                && $config->get('chrono_terminal.existing_keys_enabled') === false
                && $config->get('chrono_api_management.enabled') === false
                && $config->get('chrono_login_budget.enabled') === false
                && $config->get('app.env') === 'production' && $config->get('app.debug') === false
                && $config->get('hashing.driver') === 'bcrypt'
                && $config->get('app.key') === null && $config->get('app.previous_keys') === []);
            if ($instance === null) { return; }
            $line = file_get_contents($instance.'/framework-app-key.env');
            fresh_rehearsal_require(preg_match('/\AFRAMEWORK_APP_KEY=(base64:[A-Za-z0-9+\/]{43}=)\n\z/', $line, $match) === 1);
            $config->set([
                'app.env' => 'production', 'app.debug' => false, 'app.url' => 'https://127.0.0.1:18443',
                'app.key' => $match[1], 'app.locale' => 'de',
                'chrono_fresh_web.enabled' => true, 'chrono_fresh_web.instance_directory' => $instance,
                'chrono_fresh_web.setup_enabled' => false, 'chrono_fresh_web.session_lock_seconds' => 0,
                'chrono_fresh_web.session_wait_seconds' => 1,
                'chrono_runtime.read_enabled' => true, 'chrono_runtime.write_enabled' => true,
                'chrono_login_budget.enabled' => true, 'chrono_api_management.enabled' => true,
            ]);
            unset($line, $match);
        });
        return $application;
    };

    $phase = 'actual NEW V5 Artisan preparation and private application key';
    $app = $boot();
    $console = $app->make(ConsoleKernel::class);
    $console->bootstrap();
    fresh_rehearsal_require($app->make('db')->getConnections() === [] && ! $app->runningUnitTests());
    fresh_rehearsal_require($console->call('chrono:prepare-fresh', [
        '--private-parent' => $parent, '--new-only' => true, '--local-browser-first-admin' => true,
    ]) === 0);
    $prepared = json_decode($console->output(), true, 512, JSON_THROW_ON_ERROR);
    $instance = $prepared['directory'];
    fresh_rehearsal_require(dirname($instance) === $parent && $prepared['credentials_created'] === false
        && $prepared['server_started'] === false);
    fresh_rehearsal_require($console->call('chrono:application-key', [
        '--instance' => $instance, '--new-only' => true, '--stopped' => true,
    ]) === 0);
    $keyResult = json_decode($console->output(), true, 512, JSON_THROW_ON_ERROR);
    fresh_rehearsal_require($keyResult['file_created'] === true && $keyResult['account_created'] === false);
    unset($console);
    fresh_rehearsal_release($app);

    $phase = 'unchanged asset integrity refusal before routes or database';
    $databaseHash = hash_file('sha256', $instance.'/application.sqlite');
    $keyHash = hash_file('sha256', $instance.'/framework-app-key.env');
    $manifestHash = hash_file('sha256', $instance.'/runtime-admission.json');
    foreach (['altered-public', 'altered-public/assets', 'altered-public/assets/css', 'altered-public/assets/js'] as $directory) {
        fresh_rehearsal_require(mkdir($parent.'/'.$directory, 0700));
    }
    foreach (['assets/kolibri_icon.png', 'assets/css/main.css', 'assets/css/app_ui.css',
        'assets/js/main.js', 'assets/js/attendance.js', 'assets/js/chrono-local-web.js'] as $asset) {
        fresh_rehearsal_require(copy($root.'/framework/public/'.$asset, $parent.'/altered-public/'.$asset));
    }
    fresh_rehearsal_require(file_put_contents($parent.'/altered-public/assets/js/main.js', "\n// disposable altered bytes\n", FILE_APPEND) !== false);
    $app = $boot($instance);
    $app->usePublicPath($parent.'/altered-public');
    $refused = false;
    try { $app->make(ConsoleKernel::class)->bootstrap(); }
    catch (LogicException) { $refused = true; }
    fresh_rehearsal_require($refused && ! $app->bound(\App\FreshWeb\FreshWebConfiguration::class)
        && $app->make('db')->getConnections() === []);
    fresh_rehearsal_release($app);
    fresh_rehearsal_require(hash_file('sha256', $instance.'/application.sqlite') === $databaseHash);

    $requests = 0;
    $jar = [];
    $request = static function (string $method, string $path, array $fields = [], bool $json = false) use ($boot, $instance, &$jar, &$requests, &$app): array {
        $app = $boot($instance);
        $kernel = $app->make(HttpKernel::class);
        $input = Request::create('https://127.0.0.1:18443/'.$path, $method, $fields, $jar, [], [
            'REMOTE_ADDR' => '127.0.0.1', 'HTTPS' => 'on', 'SERVER_PORT' => '18443',
            'HTTP_ACCEPT' => $json ? 'application/json' : 'text/html',
        ]);
        $response = $kernel->handle($input);
        $kernel->terminate($input, $response);
        ++$requests;
        foreach ($response->headers->getCookies() as $cookie) {
            fresh_rehearsal_require($cookie->isSecure() && $cookie->isHttpOnly() && $cookie->getSameSite() === 'lax');
            if ($cookie->getExpiresTime() !== 0 && $cookie->getExpiresTime() < time()) { unset($jar[$cookie->getName()]); }
            else { $jar[$cookie->getName()] = $cookie->getValue(); }
        }
        $result = ['status' => $response->getStatusCode(), 'location' => $response->headers->get('Location'),
            'body' => $response->getContent(), 'type' => $response->headers->get('Content-Type')];
        fresh_rehearsal_require(! $app->runningUnitTests());
        unset($kernel, $input, $response);
        fresh_rehearsal_release($app);
        return $result;
    };
    $token = static function (array $response): string {
        fresh_rehearsal_require($response['status'] === 200
            && preg_match('/name="_token" value="([A-Za-z0-9]+)"/', $response['body'], $match) === 1);
        return $match[1];
    };

    $phase = 'real encrypted-session CSRF and first-user setup';
    fresh_rehearsal_require($request('GET', 'login.php')['location'] === 'setup.php');
    $setup = $request('GET', 'setup.php');
    $csrf = $token($setup);
    $password = bin2hex(random_bytes(18)); // This tool's disposable account only; never printed.
    $fields = ['username' => 'rehearsal.admin', 'email' => 'rehearsal@example.invalid',
        'password' => $password, 'password_confirmation' => $password];
    fresh_rehearsal_require($request('POST', 'setup.php', $fields)['status'] === 403);
    $result = $request('POST', 'setup.php', $fields + ['_token' => $csrf]);
    fresh_rehearsal_require($result['status'] === 303 && $result['location'] === 'login.php?setup=complete'
        && is_file($instance.'/setup-closed') && ! is_file($instance.'/operator-setup-token.txt'));
    fresh_rehearsal_require($request('GET', 'setup.php')['status'] === 404);

    $phase = 'normal login and guarded persisted attendance';
    $csrf = $token($request('GET', 'login.php?setup=complete'));
    $result = $request('POST', 'login.php', ['username' => 'rehearsal.admin', 'password' => $password, '_token' => $csrf]);
    fresh_rehearsal_require($result['status'] === 302 && $result['location'] === 'index.php');
    $home = $request('GET', 'index.php');
    $csrf = $token($home);
    $event = 'rehearsal-'.bin2hex(random_bytes(12));
    $start = ['action' => 'start', 'event_id' => $event, 'location' => 'home_office', 'note' => 'Disposable installation rehearsal', 'csrf_token' => $csrf];
    $result = $request('POST', 'attendance.php', $start, true);
    fresh_rehearsal_require($result['status'] === 200);
    $receipt = json_decode($result['body'], true, 512, JSON_THROW_ON_ERROR);
    fresh_rehearsal_require($receipt['success'] === true && $receipt['state'] === 'working'
        && is_int($receipt['record_id']) && $receipt['record_id'] > 0);
    $active = json_decode($request('GET', 'attendance.php?format=status', [], true)['body'], true, 512, JSON_THROW_ON_ERROR);
    fresh_rehearsal_require($active['expected_record'] === (string) $receipt['record_id']);
    fresh_rehearsal_require($request('POST', 'attendance.php', $start, true)['body'] === $result['body']);
    usleep(1100000); // Real clock only: ensure a completed report interval, no clock injection.
    foreach (['pause_start' => 'paused', 'pause_end' => 'working', 'end' => 'absent'] as $action => $status) {
        $result = $request('POST', 'attendance.php', ['action' => $action, 'event_id' => 'rehearsal-'.bin2hex(random_bytes(12)),
            'expected_record' => $active['expected_record'], 'csrf_token' => $csrf], true);
        fresh_rehearsal_require($result['status'] === 200);
        $body = json_decode($result['body'], true, 512, JSON_THROW_ON_ERROR);
        fresh_rehearsal_require($body['success'] === true && $body['state'] === $status);
    }
    fresh_rehearsal_require($request('GET', 'history.php')['status'] === 200);
    fresh_rehearsal_require($request('GET', 'export_csv.php')['status'] === 200);
    fresh_rehearsal_require($request('POST', 'logout.php', ['csrf_token' => $csrf])['location'] === 'login.php');
    fresh_rehearsal_require($request('GET', 'attendance.php?format=status', [], true)['status'] === 401);
    $jar = [];
    $csrf = $token($request('GET', 'login.php'));
    fresh_rehearsal_require($request('POST', 'login.php', ['username' => 'rehearsal.admin', 'password' => $password, '_token' => $csrf])['location'] === 'index.php');
    fresh_rehearsal_require($request('GET', 'index.php')['status'] === 200);
    unset($password, $fields, $jar, $receipt, $active, $body, $start, $csrf, $setup, $home, $result);

    $phase = 'closed admitted read and persisted integrity';
    $manifest = json_decode(file_get_contents($instance.'/runtime-admission.json'), true, 512, JSON_THROW_ON_ERROR);
    $admission = RuntimeAdmission::fromConfiguration($manifest);
    $runtime = new RuntimeDatabase($admission, new OfflineFiles([$root, $root.'/framework/public']));
    $connection = $runtime->readConnection($admission->configuration(), 'chrono');
    $pdo = $connection->getPdo();
    fresh_rehearsal_require($pdo->query('PRAGMA integrity_check')->fetchColumn() === 'ok'
        && $pdo->query('PRAGMA foreign_key_check')->fetchAll() === []
        && (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1
        && $pdo->query('SELECT role FROM users')->fetchColumn() === 'admin'
        && (int) $pdo->query('SELECT COUNT(*) FROM attendance_events')->fetchColumn() === 4
        && (int) $pdo->query('SELECT COUNT(*) FROM work_sessions WHERE ended_at IS NOT NULL')->fetchColumn() === 1
        && (int) $pdo->query('SELECT COUNT(*) FROM work_sessions WHERE ended_at IS NULL')->fetchColumn() === 0
        && (int) $pdo->query('SELECT COUNT(*) FROM break_intervals WHERE ended_at IS NOT NULL')->fetchColumn() === 1);
    fresh_rehearsal_require(hash_file('sha256', $instance.'/framework-app-key.env') === $keyHash
        && hash_file('sha256', $instance.'/runtime-admission.json') === $manifestHash);
    $report += ['status' => 'PASS', 'requests' => $requests, 'accounts' => 1, 'attendance_events' => 4,
        'php' => PHP_VERSION, 'laravel' => Application::VERSION,
        'sqlite' => $pdo->query('SELECT sqlite_version()')->fetchColumn(),
        'confirmed_retry_added_events' => 0, 'closed_sessions' => 1, 'closed_pauses' => 1,
        'new_app_per_request' => true, 'encrypted_file_sessions' => true, 'csrf_refusal_observed' => true,
        'login_after_fresh_application_boot' => true, 'altered_asset_refused_before_database' => true,
        'key_and_admission_unchanged' => true, 'integrity' => 'ok'];
    $pdo = null;
    $connection->disconnect();
    $connection = null;
    unset($runtime);
    $exit = 0;
} catch (Throwable) {
    $report += ['status' => 'FAIL', 'phase' => $phase]; // Never exception text, secret, cookie, body or private path.
} finally {
    $released = false;
    try {
        $pdo = null;
        if ($connection !== null) { $connection->disconnect(); $connection = null; }
        fresh_rehearsal_release($app);
        $released = true;
    } catch (Throwable) { $report['disposable_files_removed'] = false; $exit = 1; }
    if ($parent !== null && $released) {
        clearstatcache(true, $parent);
        $now = @lstat($parent);
        if (isset($parentIdentity) && $now !== false && $now['dev'] === $parentIdentity['dev'] && $now['ino'] === $parentIdentity['ino']) {
            try { fresh_rehearsal_remove($parent); $report['disposable_files_removed'] = true; }
            catch (Throwable) { $report['disposable_files_removed'] = false; $exit = 1; }
        } else { $report['disposable_files_removed'] = false; $exit = 1; }
    }
    umask($mask);
}
if ($exit !== 0) { $report['status'] = 'FAIL'; }
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
exit($exit);
