<?php

namespace App\Http\Middleware;

use App\FreshWeb\FreshWebConfiguration;
use App\Http\Controllers\LocalWebFirstAdminSetupController;
use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Route-specific gate BEFORE cookies/session/CSRF/auth; never an authorization replacement. */
final class RequireFreshWebProfile
{
    public function __construct(private Application $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        $config = $this->app->make('config');
        if ($config->get('chrono_fresh_web.enabled', false) !== true
            || ! $this->app->bound(FreshWebConfiguration::class)) { return $this->closed(); }
        try {
            $profile = $this->app->make(FreshWebConfiguration::class);
            $route = $request->route();
            // Reject encoded/trailing aliases before raw-field parsing and session work.
            if (! $route instanceof Route || ! $this->canonicalRequest($request, $route, $profile->origin)) { return $this->closed(); }
            $profile->assertCurrent($config);
            $this->requireSessionPolicy($profile, $route);
            $profile->assertTerminalCurrent($this->app);
            $setup = $profile->setupConfiguration();
            $config->set('chrono_setup', $setup);
            // Controller identity covers EVERY router-accepted setup URI, not only raw path text.
            if ($route->getControllerClass() === LocalWebFirstAdminSetupController::class && $setup['enabled'] !== true) {
                return $this->closed();
            }
        } catch (Throwable) {
            return $this->headers(new Response('Fresh web admission not confirmed.', 503,
                ['Content-Type' => 'text/plain; charset=UTF-8']));
        }
        return $this->headers($next($request));
    }

    /** A per-route lease must not override the admitted V5 non-expiring global lease. */
    private function requireSessionPolicy(FreshWebConfiguration $profile, Route $route): void
    {
        if ($profile->requiresNonExpiringSessionLock()
            && ($route->locksFor() !== null || $route->waitsFor() !== null)) {
            throw new \LogicException('A route override cannot replace the admitted V5 session lock policy.');
        }
    }

    private function canonicalRequest(Request $request, Route $route, string $origin): bool
    {
        return $request->getPathInfo() === '/'.ltrim($route->uri(), '/')
            && $request->isSecure() && $request->getSchemeAndHttpHost() === $origin;
    }

    private function closed(): Response
    {
        return $this->headers(new Response('Not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']));
    }

    private function headers(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Frame-Options', 'DENY');
        if (! $response->headers->has('Content-Security-Policy')) {
            // Leave reviewed inline/local UI scripts intact; preserve stricter auth CSP.
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'; base-uri 'self'; object-src 'none'; form-action 'self'");
        }
        return $response;
    }
}
