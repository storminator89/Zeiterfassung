<?php

namespace App\Providers;

use App\Auth\AccountLifecycleWebIdentity;
use App\Chrono\LegacyLocalWebAccountLifecycle;
use App\Chrono\LocalWebAccountLifecycle;
use App\FreshWeb\FreshWebConfiguration;
use App\Ldap\AdmittedLdapSettingsCipher;
use App\Ldap\LdapSyncTransport;
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
        if ($profile->ldapSyncProfile() !== null
            && ($this->app->bound(LdapSyncTransport::class) || $this->app->bound(AdmittedLdapSettingsCipher::class))) {
            throw new LogicException('Fresh LDAP sync must not replace another transport/root profile.');
        }
        $profile->configure($this->app->make('config'));
        if (($sync = $profile->ldapSyncProfile()) !== null) {
            // Pure lazy bindings; authorized orchestration resolves these after its fresh policy check.
            $this->app->scoped(LdapSyncTransport::class, fn () => $sync->transport());
            $this->app->scoped(AdmittedLdapSettingsCipher::class, fn () => $sync->cipher());
        }
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
            $profile->assertTerminalCurrent($this->app);
            if (! $this->app->bound(RuntimeDatabase::class) || ! $this->app->bound(AttendanceWriter::class)
                || (! $this->app->make(RuntimeDatabase::class)->isIntakePolicyV4()
                    && ! $this->app->make(RuntimeDatabase::class)->isAccountLifecycleV5())) {
                throw new LogicException('Fresh web requires the complete admitted local runtime and intake writer.');
            }
            $router = $this->app->make(Router::class);
            foreach ($router->getRoutes() as $route) {
                if (in_array($route->uri(), ['/', 'index.php', 'login.php', 'register.php', 'setup.php', 'attendance.php',
                    'adjustments.php', 'get_time_records.php', 'get_latest_record.php', 'save.php', 'delete.php', 'import.php', 'work_models.php', 'history.php', 'supervisor.php',
                    'dashboard.php', 'export_csv.php', 'settings.php', 'admin.php', 'get_user.php', 'logout.php'], true)) {
                    throw new LogicException('Fresh web must not replace another web route profile.');
                }
            }
            if ($this->app->make(RuntimeDatabase::class)->isAccountLifecycleV5()
                && $this->app->bound(LocalWebAccountLifecycle::class)) {
                throw new LogicException('Fresh V5 web must not replace another account lifecycle profile.');
            }
            // Prepared local V5 uses the existing owner-only withdrawal flow.
            // This metadata does not bypass the registrar's Runtime/eligibility gate.
            $includeCorrectionWithdrawals = $this->app->make(RuntimeDatabase::class)->isAccountLifecycleV5();
            $this->app->instance(Interactions::class, new Interactions(
                login: true, attendance: true, logout: true, corrections: true, home: true, absences: true,
                workModels: true, history: true, supervisor: true, dashboard: true, adminPauseSettings: true,
                correctionWithdrawals: $includeCorrectionWithdrawals,
                ldapSessionRevocations: $profile->ldapLoginEnabled(),
            ));
            // Terminal keys still require their separate exact admission; no DB-download capability is inferred.
            $router->group(['middleware' => [RequireFreshWebProfile::class]], function () use ($config, $profile, $includeCorrectionWithdrawals): void {
                LocalWebRoutes::register($this->app, includeHistory: true, includeSupervisor: true, includeDashboard: true,
                    includeCsv: true, includeSettings: true, includeAdminPauseSettings: true,
                    includeRegistration: true, includeSetup: $config->get('chrono_setup.enabled') === true,
                    includeLdapSync: $profile->ldapSyncProfile() !== null,
                    includeLdapSessionRevocations: $profile->ldapLoginEnabled(),
                    useSharedLoginBudget: SharedLoginBudgetServiceProvider::selected($this->app),
                    includeCorrectionWithdrawals: $includeCorrectionWithdrawals);
            });
            // The unchanged registrar must return first. Its current V5 Runtime
            // hold still refuses before routes or this prepared capability exist.
            if ($this->app->make(RuntimeDatabase::class)->isAccountLifecycleV5()) {
                $this->app->scoped(LocalWebAccountLifecycle::class, static function ($app): LegacyLocalWebAccountLifecycle {
                    $runtime = $app->make(RuntimeDatabase::class);
                    $identity = $app->make(AccountLifecycleWebIdentity::class);
                    if (! $runtime->isAccountLifecycleV5() || ! $identity->requiresGeneration()) {
                        throw new LogicException('The explicit V5 account lifecycle profile is required.');
                    }
                    return new LegacyLocalWebAccountLifecycle(
                        $app->make('auth'), $app->make('db'), $app->make('encrypter'),
                        static function () use ($app): AttendanceWriter {
                            if (! $app->bound(AttendanceWriter::class)) {
                                throw new LogicException('An admitted operation-scoped lifecycle writer is required.');
                            }
                            return $app->make(AttendanceWriter::class);
                        },
                        $identity, $runtime,
                    );
                });
            }
        });
    }
}
