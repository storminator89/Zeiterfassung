<?php

namespace App\Providers;

use App\Console\Commands\RehearseLegacyAdoption;
use App\Console\Commands\OfflineLegacyV2;
use App\Console\Commands\OfflineUserAuditV3;
use App\Console\Commands\PrepareFreshApplication;
use App\Console\Commands\ProvisionFirstAdminSetup;
use App\Console\Commands\ProvisionFreshApplicationKey;
use App\LegacyDatabase\AuditContext;
use App\LegacyDatabase\AuditFunctions;
use App\LegacyDatabase\MemoryOnly;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\ServiceProvider;

final class LegacyFixtureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AuditContext::class);
        $this->app->singleton(AuditFunctions::class, fn ($app) => new AuditFunctions(
            fn (): AuditContext => $app->make(AuditContext::class),
        ));
    }

    public function boot(): void
    {
        // Merely registering a named driver does not open a connection. The default
        // scratch connection and every legacy path/configuration remain untouched.
        $this->app->make('db')->extend('chrono_fixture', function (array $config, string $name) {
            MemoryOnly::configuration($config);
            $config['driver'] = 'sqlite';
            $config['name'] = $name;
            $pdo = (new SQLiteConnector)->connect($config);
            MemoryOnly::connection($pdo);
            $this->app->make(AuditFunctions::class)->register($pdo);

            return new SQLiteConnection($pdo, ':memory:', '', $config);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([RehearseLegacyAdoption::class, OfflineLegacyV2::class, OfflineUserAuditV3::class, PrepareFreshApplication::class, ProvisionFirstAdminSetup::class, ProvisionFreshApplicationKey::class]);
        }
    }
}
