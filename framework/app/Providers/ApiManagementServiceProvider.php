<?php

namespace App\Providers;

use App\Api\AdmittedLegacyApiConfiguration;
use App\Api\Management\{AdmittedApiPolicyOperations,ApiAdminBudget,ApiPolicyOperations,ApiPolicyReadSnapshot,ManagedApiCapability,PersistentApiAdminBudget};
use App\Auth\AccountLifecycleWebIdentity;
use App\Chrono\{LocalWebApiManagement,ManagedLocalWebApiManagement};
use App\FreshWeb\FreshWebConfiguration;
use App\LegacyDatabase\{AttendanceWriter,RuntimeDatabase};
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\ServiceProvider;
use LogicException;

/** Request-lifetime UI capability + independent effective policy, never an installer. */
final class ApiManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! $this->app->bound(FreshWebConfiguration::class) || ! $this->app->bound(RuntimeDatabase::class)
            || ! $this->app->make(RuntimeDatabase::class)->hasGenerationBoundAccountAuthority()) { return; }
        // Even an original Env-selected V5 API must honor a later UI Disable.
        // No DB/key access merely by binding this lazy snapshot factory.
        $this->app->scoped(ApiPolicyReadSnapshot::class, fn ($app) => new ApiPolicyReadSnapshot(
            $app->make(RuntimeDatabase::class), $app->make('config')));
        $capability = ManagedApiCapability::fromApplication($this->app);
        if ($capability === null) { return; }
        $this->app->instance(ManagedApiCapability::class, $capability);
        $this->app->scoped(ApiAdminBudget::class, fn ($app) => new PersistentApiAdminBudget(
            $app->make(CacheFactory::class)->store('file')));
        $this->app->scoped(ApiPolicyOperations::class, fn ($app) => new AdmittedApiPolicyOperations(
            $app->make(ManagedApiCapability::class),
            function () use ($app): AttendanceWriter {
                if (! $app->bound(AttendanceWriter::class)) { throw new LogicException('Admitted policy writer required.'); }
                return $app->make(AttendanceWriter::class);
            },
            function () use ($app) {
                if (! $app->bound(AdmittedLegacyApiConfiguration::class)) { throw new LogicException('Original admitted API source required.'); }
                $profile = $app->make(AdmittedLegacyApiConfiguration::class);
                $profile->assertCurrent($app);
                return $profile->key();
            },
        ));
        $this->app->scoped(LocalWebApiManagement::class, fn ($app) => new ManagedLocalWebApiManagement(
            $app->make('auth'), $app->make('hash'), $app->make('config'), $app->make(AccountLifecycleWebIdentity::class),
            $app->make(ManagedApiCapability::class), $app->make(ApiPolicyReadSnapshot::class),
            $app->make(ApiAdminBudget::class), $app->make(ApiPolicyOperations::class)));
    }
}
