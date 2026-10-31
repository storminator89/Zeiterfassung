<?php

namespace App\Providers;

use App\Mcp\ChronoServer;
use App\Mcp\LocalProfile;
use App\Mcp\ManagedProfile;
use App\FreshWeb\FreshWebConfiguration;
use App\Mcp\OwnApiClient;
use App\Mcp\OwnApiGateway;
use App\Http\Middleware\McpHttpAccess;
use App\Http\Middleware\RequireFreshWebProfile;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;

final class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $directory = $this->app->bound(FreshWebConfiguration::class) ? config('chrono_fresh_web.instance_directory') : null;
        $defaults = config('chrono_mcp');
        $this->app->singleton(ManagedProfile::class, fn () => new ManagedProfile($directory, $defaults));
        // Lazy singletons: secrets are read once per explicitly started process.
        $this->app->singleton(LocalProfile::class, fn () => new LocalProfile(config('chrono_mcp')));
        $this->app->singleton(OwnApiClient::class, fn ($app) => new OwnApiClient($app->make(LocalProfile::class)));
        $this->app->scoped(OwnApiGateway::class, fn ($app) => $app->make(OwnApiClient::class));
    }

    public function boot(): void
    {
        try { $effective = $this->app->make(ManagedProfile::class)->read(); }
        catch (\Throwable) { $effective = ['enabled' => false, 'apps_enabled' => false]; }
        config(['chrono_mcp.http_enabled' => $effective['enabled'], 'chrono_mcp.apps_enabled' => $effective['apps_enabled']]);
        if ($this->app->bound(FreshWebConfiguration::class) && config('app.debug') === false) {
            $this->app->make('router')->group(['middleware'=>[RequireFreshWebProfile::class,McpHttpAccess::class,'throttle:120,1']], function (): void {
                Mcp::web('/mcp', ChronoServer::class);
            });
        }
        if ($this->app->runningInConsole() && config('chrono_mcp.enabled') === true && config('app.debug') === false) {
            Mcp::local('chrono', ChronoServer::class);
        }
    }
}
