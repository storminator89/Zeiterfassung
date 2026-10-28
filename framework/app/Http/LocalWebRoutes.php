<?php

namespace App\Http;

use App\Http\Controllers\LocalWebAdminPauseSettingsController;
use App\Http\Controllers\LocalWebAttendanceController;
use App\Http\Controllers\LocalWebCorrectionsController;
use App\Http\Controllers\LocalWebCsvExportController;
use App\Http\Controllers\LocalWebDatabaseSnapshotController;
use App\Http\Controllers\LocalWebDashboardController;
use App\Http\Controllers\LocalWebHomeController;
use App\Http\Controllers\LocalWebLatestRecordController;
use App\Http\Controllers\LocalWebLegacyMutationDeniedController;
use App\Http\Controllers\LocalWebHistoryController;
use App\Http\Controllers\LocalWebSessionController;
use App\Http\Controllers\LocalWebRegistrationController;
use App\Http\Controllers\LocalWebFirstAdminSetupController;
use App\Http\Controllers\LocalWebSettingsController;
use App\Http\Controllers\LocalWebSupervisorController;
use App\Http\Controllers\LocalWebTerminalDevicesController;
use App\Http\Controllers\LocalWebWorkModelsController;
use App\Http\Middleware\LegacyFormCsrf;
use App\Http\Middleware\LdapSyncThrottle;
use App\LegacyDatabase\AttendanceWriter;
use App\Http\Middleware\RefreshLocalAccount;
use App\LegacyDatabase\RuntimeDatabase;
use App\Providers\SharedLoginBudgetServiceProvider;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\FileStore;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/** Explicit opt-in registrar. Never called by bootstrap/providers/default routes. */
final class LocalWebRoutes
{
    public static function register(Application $app, bool $includeHistory = false, bool $includeSupervisor = false, bool $includeDashboard = false, bool $includeCsv = false, bool $includeSettings = false, bool $includeAdminPauseSettings = false, bool $includeTerminalDevices = false, bool $includeDatabaseSnapshot = false, bool $includeRegistration = false, bool $includeSetup = false, bool $includeLdapSync = false, bool $useSharedLoginBudget = false, bool $includeTerminalSites = false, bool $includeTerminalCredentialRevocations = false, bool $includeCorrectionWithdrawals = false, bool $includeTerminalDeviceRevocations = false, bool $includeLdapSessionRevocations = false): void
    {
        if ($useSharedLoginBudget !== SharedLoginBudgetServiceProvider::selected($app)) {
            throw new LogicException('Login route and shared budget selection must agree.');
        }
        if ($includeTerminalSites && ! $includeTerminalDevices) {
            throw new LogicException('Terminal site creation requires its existing device page.');
        }
        if ($includeTerminalCredentialRevocations && (! $includeTerminalDevices
            || $app->make('config')->get('app.debug') !== false || ! $app->bound(AttendanceWriter::class))) {
            throw new LogicException('Terminal credential revocation requires its existing page, non-debug mode and an admitted writer.');
        }
        if ($includeTerminalDeviceRevocations && (! $includeTerminalDevices
            || $app->make('config')->get('app.debug') !== false || ! $app->bound(AttendanceWriter::class))) {
            throw new LogicException('Terminal device revocation requires its existing page, non-debug mode and an admitted writer.');
        }
        if ($includeCorrectionWithdrawals && ($app->make('config')->get('app.debug') !== false
            || ! $app->bound(RuntimeDatabase::class) || ! $app->make(RuntimeDatabase::class)->isIntakePolicyV4()
            || ! $app->bound(AttendanceWriter::class))) {
            throw new LogicException('Correction withdrawal requires explicit non-debug writable V4 composition.');
        }
        if ($includeLdapSessionRevocations && (! $includeAdminPauseSettings
            || $app->make('config')->get('app.debug') !== false
            || ! $app->bound(RuntimeDatabase::class) || ! $app->make(RuntimeDatabase::class)->isIntakePolicyV4()
            || ! $app->bound(AttendanceWriter::class)
            || $app->bound(\App\Chrono\LocalWebLdapSessionRevocations::class))) {
            throw new LogicException('LDAP session revocation requires its explicit non-debug writable V4 Admin composition.');
        }
        $app->make('config')->set('chrono_ldap_sessions.http_enabled', $includeLdapSessionRevocations);
        if ($includeLdapSessionRevocations) {
            // Pure/lazy factory. No directory, account, writer or key file at registration.
            $app->scoped(\App\Chrono\LocalWebLdapSessionRevocations::class,
                fn ($app) => new \App\Chrono\LegacyLocalWebLdapSessionRevocations(
                    $app->make('auth'), $app->make('db'), $app->make(\Illuminate\Contracts\Encryption\Encrypter::class),
                    fn (): AttendanceWriter => $app->make(AttendanceWriter::class),
                ));
        }
        $app->make('config')->set('chrono_corrections.withdrawals_enabled', $includeCorrectionWithdrawals);
        // A server route capability, never inferred from display flags or input.
        $app->make('config')->set('chrono_terminal_admin.credential_revocations_enabled', $includeTerminalCredentialRevocations);
        $app->make('config')->set('chrono_terminal_admin.site_creation_enabled', $includeTerminalSites);
        $app->make('config')->set('chrono_terminal_admin.device_revocations_enabled', $includeTerminalDeviceRevocations);
        // No opt-in page may be registered against missing or replaced chart bytes.
        if ($includeSupervisor) {
            self::requireSupervisorChartAssets();
        }
        if ($includeDashboard) {
            self::requireDashboardCalendarAssets();
        }
        if ($includeDatabaseSnapshot && ! $app->bound(RuntimeDatabase::class)) {
            throw new LogicException('Database snapshots require a separately admitted runtime source.');
        }
        if ($includeRegistration && $app->make('config')->get('chrono_registration.enabled', false) !== true) {
            throw new LogicException('Registration requires an explicit trusted profile.');
        }
        if ($includeSetup) {
            $app->make(\App\Auth\FirstAdminSetup::class)->assertConfigured();
            if (! $app->bound(\App\LegacyDatabase\AttendanceWriter::class)) {
                throw new LogicException('First-admin setup requires an explicitly admitted writer.');
            }
        }
        if ($includeLdapSync && (! $includeAdminPauseSettings
            || $app->make('config')->get('app.debug') !== false
            || ! $app->bound(RuntimeDatabase::class) || ! $app->make(RuntimeDatabase::class)->isIntakePolicyV4()
            || ! $app->bound(AttendanceWriter::class)
            || ! $app->make(CacheFactory::class)->store('file')->getStore() instanceof FileStore)) {
            throw new LogicException('LDAP HTTP sync requires explicit writable V4, non-debug Admin composition and persistent file cache.');
        }
        $app->make('config')->set('chrono_ldap_sync.http_enabled', $includeLdapSync);
        self::requireSessionBlocking($app);
        $router = $app->make(Router::class);
        // Keep Laravel's middleware sorter from moving identity ahead of CSRF.
        $kernel = $app->make(Kernel::class);
        $kernel->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, LegacyFormCsrf::class);
        $kernel->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, RefreshLocalAccount::class);

        // Native attendance/correction/model fields were never globally trimmed/coerced.
        // " 1 " must not become either a confirmation or a cancellation flag.
        $nativeFormPath = fn (Request $request): bool => in_array($request->path(), ['/', 'index.php', 'attendance.php', 'adjustments.php', 'get_time_records.php', 'save.php', 'work_models.php', 'get_latest_record.php', 'delete.php', 'import.php'], true)
            || ($includeSettings && $request->path() === 'settings.php')
            || ($includeAdminPauseSettings && $request->path() === 'admin.php')
            || ($includeRegistration && $request->path() === 'register.php')
            || ($includeSetup && $request->path() === 'setup.php')
            || (($includeTerminalSites || $includeTerminalCredentialRevocations || $includeTerminalDeviceRevocations) && $request->path() === 'terminals.php');
        TrimStrings::skipWhen($nativeFormPath);
        ConvertEmptyStringsToNull::skipWhen($nativeFormPath);

        $app->make(ExceptionHandler::class)->renderable(function (AuthenticationException $error, Request $request) use ($includeHistory, $includeSupervisor, $includeDashboard, $includeCsv, $includeSettings, $includeAdminPauseSettings, $includeTerminalDevices, $includeDatabaseSnapshot, $includeTerminalSites, $includeTerminalCredentialRevocations, $includeTerminalDeviceRevocations) {
            if ($request->path() === 'get_latest_record.php') {
                // Explicit unauthorized transport, not the native accidental200.
                return new Response('Nicht angemeldet.', 401,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
            }
            // Native compatibility denials are text endpoints, not login pages.
            // Preserve distinct anonymous delete401 and import403 contracts.
            if (in_array($request->path(), ['delete.php', 'import.php'], true)) {
                return new Response($request->path() === 'delete.php' ? 'Nicht angemeldet.' : 'Administrator access required.',
                    $request->path() === 'delete.php' ? 401 : 403,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
            }
            if ($includeDatabaseSnapshot && $request->path() === 'download.php') {
                return new Response('Please sign in again.', 401,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
            }
            if ($includeAdminPauseSettings && $request->path() === 'get_user.php') {
                return new Response('Please sign in again.', 401,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
            }
            if (($includeSettings && $request->path() === 'settings.php')
                || ($includeAdminPauseSettings && $request->path() === 'admin.php')) {
                return $request->isMethod('POST')
                    ? new Response('Please sign in again.', 401, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store'])
                    : new RedirectResponse('login.php');
            }

            if (($includeTerminalSites || $includeTerminalCredentialRevocations || $includeTerminalDeviceRevocations) && $request->path() === 'terminals.php' && $request->isMethod('POST')) {
                return new Response('Please sign in again.', 401,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
            }

            // These opt-in reports are full pages, never status/form transports.
            if (($includeHistory && $request->path() === 'history.php')
                || ($includeSupervisor && $request->path() === 'supervisor.php')
                || ($includeDashboard && $request->path() === 'dashboard.php')
                || ($includeCsv && $request->path() === 'export_csv.php')
                || ($includeTerminalDevices && $request->path() === 'terminals.php')) {
                return new RedirectResponse('login.php');
            }

            if (in_array($request->path(), ['/', 'index.php', 'attendance.php', 'adjustments.php', 'get_time_records.php', 'save.php', 'work_models.php', 'logout.php'], true)) {
                if ($request->path() !== 'logout.php'
                    && ($request->path() === 'get_time_records.php' || $request->isMethod('POST') || $request->query('format') === 'status')) {
                    return new Response('Please sign in again.', 401, ['Content-Type' => 'text/plain; charset=UTF-8']);
                }

                return new RedirectResponse('login.php');
            }
        });

        // Stock web stack, with LegacyFormCsrf IN PLACE OF PreventRequestForgery.
        $router->group(['middleware' => [
            EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class,
            ShareErrorsFromSession::class, LegacyFormCsrf::class, SubstituteBindings::class,
        ]], function () use ($router, $includeHistory, $includeSupervisor, $includeDashboard, $includeCsv, $includeSettings, $includeAdminPauseSettings, $includeTerminalDevices, $includeDatabaseSnapshot, $includeRegistration, $includeSetup, $includeLdapSync, $useSharedLoginBudget, $includeTerminalSites, $includeTerminalCredentialRevocations, $includeTerminalDeviceRevocations): void {
            $router->get('login.php', [LocalWebSessionController::class, 'create'])->name('login');
            $login = $router->post('login.php', [LocalWebSessionController::class, 'store']);
            if (! $useSharedLoginBudget) {
                $login->middleware('throttle:chrono-web-login');
            }
            if ($includeSetup) {
                $router->get('setup.php', [LocalWebFirstAdminSetupController::class, 'create'])->name('chrono.first-admin-setup');
                $router->post('setup.php', [LocalWebFirstAdminSetupController::class, 'store'])->middleware('throttle:chrono-web-setup');
            }
            if ($includeRegistration) {
                $router->get('register.php', [LocalWebRegistrationController::class, 'create']);
                $router->post('register.php', [LocalWebRegistrationController::class, 'store'])->middleware('throttle:chrono-web-register');
            }
            $router->group(['middleware' => [RefreshLocalAccount::class, 'auth:web', 'auth.session']], function () use ($router, $includeHistory, $includeSupervisor, $includeDashboard, $includeCsv, $includeSettings, $includeAdminPauseSettings, $includeTerminalDevices, $includeDatabaseSnapshot, $includeLdapSync, $includeTerminalSites, $includeTerminalCredentialRevocations, $includeTerminalDeviceRevocations): void {
                foreach (['/', 'index.php', 'attendance.php'] as $path) {
                    $router->get($path, [LocalWebAttendanceController::class, 'page']);
                    $router->post($path, [LocalWebAttendanceController::class, 'transition']);
                }
                $router->get('adjustments.php', [LocalWebCorrectionsController::class, 'index']);
                $router->post('adjustments.php', [LocalWebCorrectionsController::class, 'store']);
                $router->get('get_time_records.php', [LocalWebHomeController::class, 'records']);
                // Native fragment has no method guard; unsafe methods retain CSRF.
                $router->match(array_values(array_unique([...Router::$verbs, 'TRACE', 'CONNECT'])),
                    'get_latest_record.php', [LocalWebLatestRecordController::class, 'latest']);
                $router->post('save.php', [LocalWebHomeController::class, 'absence']);
                // Native stubs have no method guard. Include standard TRACE/CONNECT
                // as well as router verbs; unsafe methods still require CSRF.
                $denialMethods = array_values(array_unique([...Router::$verbs, 'TRACE', 'CONNECT']));
                $router->match($denialMethods, 'delete.php', [LocalWebLegacyMutationDeniedController::class, 'delete']);
                $router->match($denialMethods, 'import.php', [LocalWebLegacyMutationDeniedController::class, 'import']);
                $router->get('work_models.php', [LocalWebWorkModelsController::class, 'index']);
                $router->post('work_models.php', [LocalWebWorkModelsController::class, 'store']);
                if ($includeHistory) {
                    $router->get('history.php', [LocalWebHistoryController::class, 'index']);
                }
                if ($includeSupervisor) {
                    $router->get('supervisor.php', [LocalWebSupervisorController::class, 'index']);
                }
                if ($includeDashboard) {
                    $router->get('dashboard.php', [LocalWebDashboardController::class, 'index']);
                }
                if ($includeCsv) {
                    $router->get('export_csv.php', [LocalWebCsvExportController::class, 'index'])->name('chrono.export-csv');
                }
                if ($includeSettings) {
                    $router->get('settings.php', [LocalWebSettingsController::class, 'index']);
                    $router->post('settings.php', [LocalWebSettingsController::class, 'store']);
                }
                if ($includeAdminPauseSettings) {
                    $router->get('admin.php', [LocalWebAdminPauseSettingsController::class, 'index']);
                    $adminPost = $router->post('admin.php', [LocalWebAdminPauseSettingsController::class, 'store']);
                    if ($includeLdapSync) { $adminPost->middleware(LdapSyncThrottle::class); }
                    $router->get('get_user.php', [LocalWebAdminPauseSettingsController::class, 'user']);
                }
                if ($includeTerminalDevices) {
                    $router->get('terminals.php', [LocalWebTerminalDevicesController::class, 'index']);
                }
                if ($includeTerminalSites || $includeTerminalCredentialRevocations || $includeTerminalDeviceRevocations) {
                    $router->post('terminals.php', [LocalWebTerminalDevicesController::class, 'store']);
                }
                if ($includeDatabaseSnapshot) {
                    $router->get('download.php', [LocalWebDatabaseSnapshotController::class, 'index'])->name('chrono.database-snapshot');
                }
                $router->post('logout.php', [LocalWebSessionController::class, 'destroy']);
            });
        });

        // This registrar can run AFTER the normal provider boot callbacks.
        // Rebuild Laravel's standard late-route indexes so auth.session can
        // resolve its named login redirect before our exception renderer runs.
        $router->getRoutes()->refreshNameLookups();
        $router->getRoutes()->refreshActionLookups();
    }

    /** Exact reviewed public bytes only; never a request path, URL or config override. */
    private static function requireSupervisorChartAssets(): void
    {
        $directory = dirname(__DIR__, 2).'/public/assets/vendor/chartjs/4.6.1/';
        $assets = [
            'chart.umd.min.js' => '87364cc2b2a76a6684929e7d6b80b18a897794db38400caff1fcf1791c6925d2',
            'LICENSE.md' => '41a84aa2caba645f966a18d9c2056b73e6d3a81d80bc0046bc0011a2634d4cce',
            'LICENSE-kurkle-color.md' => '2859c50313bad2ba77b081410c477beaf92b60ca13a77a248d89859a6dd6ac81',
        ];
        foreach ($assets as $name => $hash) {
            $path = $directory.$name;
            if (is_link($path) || ! is_file($path) || ! is_readable($path)
                || ! hash_equals($hash, hash_file('sha256', $path) ?: '')) {
                throw new LogicException('Pinned local Supervisor chart assets are not admitted.');
            }
        }
    }

    /** Exact reviewed Calendar bytes only; no mutable manifest/path/config selection. */
    private static function requireDashboardCalendarAssets(): void
    {
        $directory = dirname(__DIR__, 2).'/public/';
        $assets = [
            'vendor/fullcalendar/7.1.3/LICENSE.md' => '000882c713ee4c9729c7eeafbebb04a98a633517c2e95f1258fc488853124df3',
            'vendor/fullcalendar/7.1.3/all/global.js' => '14564575f16e82de18da6ccf6d21da12fdd0d0f5d50100f22daad59f2cdeb9bd',
            'vendor/fullcalendar/7.1.3/licenses/Apache-2.0.txt' => 'cfc7749b96f63bd31c3c42b5c471bf756814053e847c10f3eb003417bc523d30',
            'vendor/fullcalendar/7.1.3/licenses/preact-LICENSE' => '1fe6958409c8c257a70c587a18b6f7f412b179b456630790d30b2ec9a8e4b7d4',
            'vendor/fullcalendar/7.1.3/licenses/temporal-polyfill-LICENSE' => 'c29814b29591d08896b63870ffdd652785f5b2e3f902860645f00871b596068d',
            'vendor/fullcalendar/7.1.3/licenses/temporal-spec-LICENSE' => 'fea66f1be8df22727cd9750a85865270949107151b6414986c23ebe1929f66b3',
            'vendor/fullcalendar/7.1.3/licenses/temporal-utils-LICENSE' => 'c29814b29591d08896b63870ffdd652785f5b2e3f902860645f00871b596068d',
            'vendor/fullcalendar/7.1.3/locales/de/global.js' => '28e51fb439c94d51ae92d27162842a7666a03c96e5d74133b5e75b566bbbbe22',
            'vendor/fullcalendar/7.1.3/locales/zh-tw/global.js' => 'eed62343d77e5e072ac26ab84713eeebdd376b4c711c3d18c5e131acc11f07fd',
            'vendor/fullcalendar/7.1.3/provenance.json' => '3eb6b91f243a86b4be2ab7891d8b6be8ed7b5791a2307934dca7bf735b7ed36a',
            'vendor/fullcalendar/7.1.3/skeleton.css' => '08b1350f84d3ba970d1533a22f6f58c877d3175a68c9617d470f2421ece4a112',
            'vendor/fullcalendar/7.1.3/themes/classic/global.js' => 'a66eb65fc730f3b662f9c1ef30f8f741c42dcfe381d089f78dac322bf4dfbd5d',
            'vendor/fullcalendar/7.1.3/themes/classic/palette.css' => '5fa284fcef81b68bca7780f72fd110929284d0bf993b9ce5a8bffed54e3d38ee',
            'vendor/fullcalendar/7.1.3/themes/classic/theme.css' => 'e284a8b53bc52d362d172d37fdc59fe3ae821bc11d6a85d6fab7e20bdf45f043',
            'assets/js/chrono-calendar.js' => '28e08f3d98272460b703adf080c31148fb3480f8df9ea32ef44021b72145c9ec',
        ];
        foreach ($assets as $name => $hash) {
            $path = $directory.$name;
            if (is_link($path) || ! is_file($path) || ! is_readable($path)
                || ! hash_equals($hash, hash_file('sha256', $path) ?: '')) {
                throw new LogicException('Pinned local Dashboard calendar assets are not admitted.');
            }
        }
    }

    /** Admit the whole opt-in stack, including reads, before registering any route. */
    private static function requireSessionBlocking(Application $app): void
    {
        $config = $app->make('config');
        $store = $config->get('session.block_store');
        if ($config->get('session.block') !== true || ! is_string($store) || $store === ''
            || $config->get('session.driver') === 'cookie'
            || $config->get('cache.stores.'.$store.'.driver') !== 'file') {
            throw new LogicException('The opt-in local web stack requires explicit persistent Laravel session blocking.');
        }
        foreach (['session.block_lock_seconds', 'session.block_wait_seconds'] as $key) {
            $seconds = $config->get($key);
            if (! is_int($seconds) || $seconds < 1) {
                throw new LogicException('Positive integer Laravel session lock and wait durations are required.');
            }
        }
        $backend = $app->make(CacheFactory::class)->store($store)->getStore();
        if (! $backend instanceof FileStore || ! $backend instanceof LockProvider) {
            throw new LogicException('A stock Laravel FileStore LockProvider is required for local web sessions.');
        }
    }
}
