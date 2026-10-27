<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\RequestGuard;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/** Select the bearer identity for the shared services; never reuse a web session. */
final class RequireLegacyApiToken
{
    public function __construct(private AuthManager $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $previous = $this->auth->getDefaultDriver();
        $guard = $this->auth->guard('chrono_api');
        if (! $guard instanceof RequestGuard) {
            throw new LogicException('The legacy API request guard is required.');
        }
        // Also safe for a reused application: a previous token/user cannot leak.
        $guard->forgetUser();
        $guard->setRequest($request);
        try {
            $guard->user(); // Throws the existing text 401, never a login redirect.
            $this->auth->shouldUse('chrono_api');
            return $next($request);
        } finally {
            $guard->forgetUser();
            $this->auth->shouldUse($previous);
        }
    }
}
