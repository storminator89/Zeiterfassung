<?php

namespace App\Providers;

use App\Mcp\ChronoServer;
use App\Mcp\LocalProfile;
use App\Mcp\OwnApiClient;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;

final class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Lazy singletons: secrets are read once per explicitly started process.
        $this->app->singleton(LocalProfile::class, fn () => new LocalProfile(config('chrono_mcp')));
        $this->app->singleton(OwnApiClient::class, fn ($app) => new OwnApiClient($app->make(LocalProfile::class)));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole() && config('chrono_mcp.enabled') === true && config('app.debug') === false) {
            Mcp::local('chrono', ChronoServer::class);
        }
    }
}
