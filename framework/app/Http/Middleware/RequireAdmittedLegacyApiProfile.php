<?php

namespace App\Http\Middleware;

use App\Api\AdmittedLegacyApiConfiguration;
use App\Api\LegacyApiResponse;
use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** After raw wire/OPTIONS rejection, BEFORE bearer/controller/key/DB work. */
final class RequireAdmittedLegacyApiProfile
{
    public function __construct(private Application $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->app->make('config')->get('chrono_api.enabled', false) !== true
            || ! $this->app->bound(AdmittedLegacyApiConfiguration::class)) {
            return LegacyApiResponse::text('Invalid endpoint', 404);
        }
        $profile = $this->app->make(AdmittedLegacyApiConfiguration::class);
        $profile->assertCurrent($this->app);
        if (! $request->isSecure() || $request->getSchemeAndHttpHost() !== $profile->origin) {
            return LegacyApiResponse::text('Invalid endpoint', 400);
        }
        return $next($request);
    }
}
