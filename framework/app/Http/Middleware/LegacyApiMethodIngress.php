<?php

namespace App\Http\Middleware;

use App\Api\AdmittedLegacyApiConfiguration;
use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;

/** Selected API only; appended AFTER stock global gates, BEFORE route matching. */
final class LegacyApiMethodIngress
{
    public function __construct(
        private Application $app,
        private LegacyApiWire $wire,
        private Router $router,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $uri = $request->server('REQUEST_URI');
        $path = is_string($uri) ? parse_url($uri, PHP_URL_PATH) : null;
        if (! $this->app->bound(AdmittedLegacyApiConfiguration::class)
            || ! is_string($path) || ($path !== '/api.php' && ! str_starts_with($path, '/api.php/'))) {
            return $next($request);
        }

        // One extant normalization, before Laravel consults getMethod(). POST
        // overrides must not pick another route or reject before the wire runs.
        $this->wire->restoreRawMethod($request);
        if (in_array($request->server('REQUEST_METHOD'), ['GET', 'POST', 'DELETE', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Router::any excludes arbitrary methods. Reuse the existing raw-wire
        // 405/headers instead of routing to a framework HTML/JSON error. The
        // destination is unreachable for these raw methods; no inner stack or
        // profile/credential/key/DB resolution is permitted here.
        $response = $this->wire->handle($request, static function (): never {
            throw new \LogicException('Invalid API method reached a destination.');
        });

        // Match ordinary route response preparation, notably bodyless HEAD.
        return $this->router->prepareResponse($request, $response);
    }
}
