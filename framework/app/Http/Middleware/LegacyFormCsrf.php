<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;

/** Legacy wire adapter only; Laravel still compares and owns the session token. */
final class LegacyFormCsrf extends PreventRequestForgery
{
    // Existing UI uses its hidden field / X-CSRF-Token, not an extra XSRF cookie.
    protected $addHttpCookie = false;

    protected function getTokenFromRequest($request)
    {
        return $request->input('csrf_token') ?? parent::getTokenFromRequest($request);
    }

    protected function hasValidOrigin($request)
    {
        // Laravel 13 also permits same-origin metadata without a token. The old
        // form contract requires a token even then: do not silently weaken it.
        return false;
    }

    public function handle($request, Closure $next)
    {
        try {
            return parent::handle($request, $next);
        } catch (TokenMismatchException) {
            return new Response('Invalid CSRF token. Reload the page.', 403, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }
    }
}
