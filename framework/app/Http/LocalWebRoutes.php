<?php

namespace App\Http;

use App\Http\Controllers\LocalWebAttendanceController;
use App\Http\Controllers\LocalWebCorrectionsController;
use App\Http\Controllers\LocalWebHomeController;
use App\Http\Controllers\LocalWebHistoryController;
use App\Http\Controllers\LocalWebSessionController;
use App\Http\Controllers\LocalWebWorkModelsController;
use App\Http\Middleware\LegacyFormCsrf;
use App\Http\Middleware\RefreshLocalAccount;
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
    public static function register(Application $app, bool $includeHistory = false): void
    {
        self::requireSessionBlocking($app);
        $router = $app->make(Router::class);
        // Keep Laravel's middleware sorter from moving identity ahead of CSRF.
        $kernel = $app->make(Kernel::class);
        $kernel->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, LegacyFormCsrf::class);
        $kernel->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, RefreshLocalAccount::class);

        // Native attendance/correction/model fields were never globally trimmed/coerced.
        // " 1 " must not become either a confirmation or a cancellation flag.
        $nativeFormPath = fn (Request $request): bool => in_array($request->path(), ['/', 'index.php', 'attendance.php', 'adjustments.php', 'get_time_records.php', 'save.php', 'work_models.php'], true);
        TrimStrings::skipWhen($nativeFormPath);
        ConvertEmptyStringsToNull::skipWhen($nativeFormPath);

        $app->make(ExceptionHandler::class)->renderable(function (AuthenticationException $error, Request $request) use ($includeHistory) {
            // History is a full page, not an async fragment or form transport.
            if ($includeHistory && $request->path() === 'history.php') {
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
        ]], function () use ($router, $includeHistory): void {
            $router->get('login.php', [LocalWebSessionController::class, 'create'])->name('login');
            $router->post('login.php', [LocalWebSessionController::class, 'store'])->middleware('throttle:chrono-web-login');
            $router->group(['middleware' => [RefreshLocalAccount::class, 'auth:web', 'auth.session']], function () use ($router, $includeHistory): void {
                foreach (['/', 'index.php', 'attendance.php'] as $path) {
                    $router->get($path, [LocalWebAttendanceController::class, 'page']);
                    $router->post($path, [LocalWebAttendanceController::class, 'transition']);
                }
                $router->get('adjustments.php', [LocalWebCorrectionsController::class, 'index']);
                $router->post('adjustments.php', [LocalWebCorrectionsController::class, 'store']);
                $router->get('get_time_records.php', [LocalWebHomeController::class, 'records']);
                $router->post('save.php', [LocalWebHomeController::class, 'absence']);
                $router->get('work_models.php', [LocalWebWorkModelsController::class, 'index']);
                $router->post('work_models.php', [LocalWebWorkModelsController::class, 'store']);
                if ($includeHistory) {
                    $router->get('history.php', [LocalWebHistoryController::class, 'index']);
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
