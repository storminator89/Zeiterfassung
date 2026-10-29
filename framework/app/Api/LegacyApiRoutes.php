<?php

namespace App\Api;

use App\Http\Controllers\Api\LegacyApiReadController;
use App\Http\Controllers\Api\LegacyApiLoginController;
use App\Http\Controllers\Api\LegacyApiAttendanceController;
use App\Http\Middleware\LegacyApiWire;
use App\Http\Middleware\RequireLegacyApiToken;
use App\Http\Middleware\RequireAdmittedLegacyApiProfile;
use Illuminate\Routing\Router;

/** Single route definition; public composition requires the explicit admitted profile. */
final class LegacyApiRoutes
{
    public function registerCandidate(Router $router, bool $requireAdmittedProfile = false): void
    {
        $middleware = [LegacyApiWire::class];
        if ($requireAdmittedProfile) { $middleware[] = RequireAdmittedLegacyApiProfile::class; }
        $router->group(['prefix' => 'api.php', 'middleware' => $middleware], function (Router $router): void {
            foreach (['users' => 'identity', 'timeentries' => 'entries', 'corrections' => 'corrections'] as $path => $action) {
                $route = $router->any($path, [LegacyApiReadController::class, $action])
                    ->middleware(RequireLegacyApiToken::class);
                // Laravel otherwise normalizes trailing slashes/percent escapes
                // and honors method overrides. The wire layer keeps the
                // exact legacy URL and raw HTTP method before authentication.
                $route->setAction($route->getAction() + ['legacy_api_path' => '/api.php/'.$path, 'legacy_api_method' => 'GET']);
            }
            foreach ([
                'login' => LegacyApiLoginController::class,
                'attendance' => LegacyApiAttendanceController::class,
                'workentry' => [LegacyApiAttendanceController::class, 'start'],
                'setendzeit' => [LegacyApiAttendanceController::class, 'end'],
            ] as $path => $controller) {
                // GET/HEAD attendance belong only to the read candidate below,
                // including when Laravel compiles the route collection.
                $route = $path === 'attendance'
                    ? $router->match(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $path, $controller)
                    : $router->any($path, $controller);
                if ($path !== 'login') {
                    $route->middleware(RequireLegacyApiToken::class);
                }
                $route->setAction($route->getAction() + ['legacy_api_path' => '/api.php/'.$path, 'legacy_api_method' => 'POST']);
            }
            // Separate GET/HEAD registration changes neither the POST body
            // parsing nor its guard/shared writer. The wire layer
            // continues to reject raw HEAD before authentication, as natively.
            $route = $router->get('attendance', [LegacyApiReadController::class, 'attendance'])
                ->middleware(RequireLegacyApiToken::class);
            $route->setAction($route->getAction() + ['legacy_api_path' => '/api.php/attendance', 'legacy_api_method' => 'GET']);

            $route = $router->any('timeentry/{identifier}', fn () => LegacyApiResponse::text(
                'Use an approved correction request to cancel a record.', 409,
            ))->where('identifier', '[0-9]+')->middleware(RequireLegacyApiToken::class);
            $route->setAction($route->getAction() + [
                'legacy_api_method' => 'DELETE',
                'legacy_api_pattern' => '~^/api.php/timeentry/[0-9]+$~D',
            ]);

            $router->any('{path?}', fn () => LegacyApiResponse::json([
                'success' => false, 'message' => 'Invalid endpoint',
            ], 400))->where('path', '.*');
        });
    }
}
