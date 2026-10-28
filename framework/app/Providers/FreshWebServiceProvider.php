<?php

namespace App\Providers;

use App\FreshWeb\FreshWebConfiguration;
use App\Http\LocalWebRoutes;
use App\Http\Middleware\RequireFreshWebProfile;
use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\RuntimeDatabase;
use App\View\ExistingUi\Interactions;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use LogicException;

/** Default-off, standard request-lifetime Laravel composition; not an installer. */
final class FreshWebServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $profile = FreshWebConfiguration::fromApplication($this->app);
        if ($profile === null) { return; }
        $profile->configure($this->app->make('config'));
        $this->app->instance(FreshWebConfiguration::class, $profile);
    }

    public function boot(): void
    {
        // Also orders the gate before sessions in any leftover compiled fresh route.
        $this->app->make(Kernel::class)->addToMiddlewarePriorityBefore(EncryptCookies::class, RequireFreshWebProfile::class);
        if (! $this->app->bound(FreshWebConfiguration::class)) { return; }
        $this->app->booted(function (): void {
            $config = $this->app->make('config');
            $profile = $this->app->make(FreshWebConfiguration::class);
            $profile->assertCurrent($config);
            if (! $this->app->bound(RuntimeDatabase::class) || ! $this->app->bound(AttendanceWriter::class)
                || ! $this->app->make(RuntimeDatabase::class)->isIntakePolicyV4()) {
                throw new LogicException('Fresh web requires the complete admitted V4 runtime and intake writer.');
            }
            $router = $this->app->make(Router::class);
            foreach ($router->getRoutes() as $route) {
                if (in_array($route->uri(), ['/', 'index.php', 'login.php', 'register.php', 'setup.php', 'attendance.php',
                    'adjustments.php', 'get_time_records.php', 'save.php', 'work_models.php', 'history.php', 'supervisor.php',
                    'dashboard.php', 'export_csv.php', 'settings.php', 'admin.php', 'get_user.php', 'logout.php'], true)) {
                    throw new LogicException('Fresh web must not replace another web route profile.');
                }
            }
            $this->app->instance(Interactions::class, new Interactions(
                login: true, attendance: true, logout: true, corrections: true, home: true, absences: true,
                workModels: true, history: true, supervisor: true, dashboard: true, adminPauseSettings: true,
            ));
            // Terminal protocol/key and secret-bearing DB-download capabilities are NOT inferred.
            $router->group(['middleware' => [RequireFreshWebProfile::class]], function () use ($config): void {
                LocalWebRoutes::register($this->app, includeHistory: true, includeSupervisor: true, includeDashboard: true,
                    includeCsv: true, includeSettings: true, includeAdminPauseSettings: true,
                    includeRegistration: true, includeSetup: $config->get('chrono_setup.enabled') === true);
            });
        });
    }
}
