<?php

namespace App\Api;

use App\Http\Controllers\Api\LegacyApiReadController;
use App\Http\Controllers\Api\LegacyApiLoginController;
use App\Http\Controllers\Api\LegacyApiAttendanceController;
use App\Http\Middleware\LegacyApiWire;
use App\Http\Middleware\RequireLegacyApiToken;
use Illuminate\Routing\Router;

/** Dormant candidate only. Never called from bootstrap or a shipped route file. */
final class LegacyApiRoutes
{
    public function registerCandidate(Router $router): void
    {
        $router->group(['prefix' => 'api.php', 'middleware' => [LegacyApiWire::class]], function (Router $router): void {
            foreach (['users' => 'identity', 'timeentries' => 'entries'] as $path => $action) {
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
                $route = $router->any($path, $controller);
                if ($path !== 'login') {
                    $route->middleware(RequireLegacyApiToken::class);
                }
                $route->setAction($route->getAction() + ['legacy_api_path' => '/api.php/'.$path, 'legacy_api_method' => 'POST']);
            }
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
