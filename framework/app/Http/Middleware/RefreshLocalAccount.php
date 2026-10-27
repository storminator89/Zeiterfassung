<?php

namespace App\Http\Middleware;

use App\Auth\LegacyUser;
use App\Auth\LocalSession;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Reload identity/roles before stock auth:web + auth.session and any policy. */
final class RefreshLocalAccount
{
    public function __construct(private Factory $auth, private LocalSession $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->auth->guard('web');
        $identifier = $request->session()->get($guard->getName());
        $user = $identifier === null ? null : $guard->getProvider()->retrieveById($identifier);

        if (! $user instanceof LegacyUser || $user->auth_source !== 'local'
            || ! in_array($user->role, ['user', 'supervisor', 'admin'], true)) {
            $this->sessions->logout($request);
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }

        $guard->setUser($user);

        // Password revocation is NOT reimplemented here. The next middleware is
        // Laravel's AuthenticateSession (`auth.session`), with its login marker.
        return $next($request);
    }
}
