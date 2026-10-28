<?php

namespace App\Providers;

use App\Api\AdmittedLegacyApiConfiguration;
use App\Api\LegacyApiRoutes;
use App\Api\LegacyApiTokenKey;
use App\Http\Middleware\LegacyApiMethodIngress;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel as FoundationHttpKernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use LogicException;

/** Default-off composition of existing sources, not key/database provisioning. */
final class AdmittedLegacyApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $profile = AdmittedLegacyApiConfiguration::fromApplication($this->app);
        if ($profile === null) { return; }
        $this->app->instance(AdmittedLegacyApiConfiguration::class, $profile);
        $this->app->instance(LegacyApiTokenKey::class, $profile->key());
    }

    public function boot(): void
    {
        if (! $this->app->bound(AdmittedLegacyApiConfiguration::class)) { return; }
        $this->app->booted(function (): void {
            $this->app->make(AdmittedLegacyApiConfiguration::class)->assertCurrent($this->app);
            $router = $this->app->make(Router::class);
            foreach ($router->getRoutes() as $route) {
                if ($route->uri() === 'api.php' || str_starts_with($route->uri(), 'api.php/')) {
                    throw new LogicException('The admitted API must not replace another route profile.');
                }
            }
            // One extant route definition, now with its explicit trailing profile gate.
            (new LegacyApiRoutes)->registerCandidate($router, requireAdmittedProfile: true);
            // Keep stock maintenance/CORS/global gates ahead of this narrow
            // raw-method adapter, but run it BEFORE route method selection.
            // The helper covers both an already-resolved HTTP kernel (normal
            // HTTP boot) and a kernel resolved later (console boot first).
            $this->callAfterResolving(HttpKernel::class, static function (FoundationHttpKernel $kernel): void {
                $kernel->pushMiddleware(LegacyApiMethodIngress::class);
            });
        });
    }
}
