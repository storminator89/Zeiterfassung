<?php

namespace App\Http\Middleware;

use App\Api\AdmittedLegacyApiConfiguration;
use App\Api\LegacyApiResponse;
use App\Api\Management\ApiPolicyReadSnapshot;
use Throwable;
use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** After raw wire/OPTIONS rejection; policy snapshot BEFORE bearer/key/domain work. */
final class RequireAdmittedLegacyApiProfile
{
    public function __construct(private Application $app, private ?ApiPolicyReadSnapshot $policies = null) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->app->bound(AdmittedLegacyApiConfiguration::class)) {
            return LegacyApiResponse::text('Invalid endpoint', 404);
        }
        $profile = $this->app->make(AdmittedLegacyApiConfiguration::class);
        $profile->assertCurrent($this->app);
        if (! $request->isSecure() || $request->getSchemeAndHttpHost() !== $profile->origin) {
            return LegacyApiResponse::text('Invalid endpoint', 400);
        }
        // A selected key/profile is capability, not the UI-controlled operating bit.
        // Check every API call BEFORE login budget, bearer, key or controller work.
        if ($profile->requiresOperatingPolicy()) {
            if ($this->policies === null) { return LegacyApiResponse::text('API-Freigabe nicht bestätigt.', 503); }
            try {
                if (! $this->policies->read()->enabled) { return LegacyApiResponse::text('API ist deaktiviert.', 503); }
            } catch (Throwable) { return LegacyApiResponse::text('API-Freigabe nicht bestätigt.', 503); }
        }
        return $next($request);
    }
}
