<?php

namespace App\Http\Middleware;

use App\Auth\LegacyUser;
use App\Auth\AccountLifecycleWebIdentity;
use App\Auth\ExistingAccountUserProvider;
use App\Auth\LocalSession;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Reload identity/roles before stock auth:web + auth.session and any policy. */
final class RefreshLocalAccount
{
    public function __construct(private Factory $auth, private LocalSession $sessions,
        private ?AccountLifecycleWebIdentity $lifecycle = null) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->auth->guard('web');
        $identifier = $request->session()->get($guard->getName());
        $user = $identifier === null ? null : $guard->getProvider()->retrieveById($identifier);

        $provider = $guard instanceof SessionGuard ? $guard->getProvider() : null;
        $hasSource = $request->session()->exists(LocalSession::SOURCE_MARKER);
        $source = $hasSource ? $request->session()->get(LocalSession::SOURCE_MARKER) : 'local';
        $hash = $user instanceof LegacyUser ? $user->getAuthPassword() : null;
        $standardMarker = $request->session()->get(LocalSession::PASSWORD_MARKER);
        // Truly absent old source means LOCAL only. Explicit null is not absent.
        // Source-marked sessions must not use auth.session's missing-hash adoption.
        $marked = ! $hasSource || (is_string($source) && in_array($source, ['local', 'ldap'], true)
            && is_string($hash) && (bool) $hash
            && $request->session()->exists(LocalSession::PASSWORD_MARKER)
            && is_string($standardMarker) && $standardMarker !== '');
        $eligible = $user instanceof LegacyUser && $user->auth_source === $source
            && ($source === 'local' ? in_array($user->role, ['user', 'supervisor', 'admin'], true)
                : ($provider instanceof ExistingAccountUserProvider && $provider->admits($user)));
        // V5 never adopts an absent/null/string marker, even on the stock
        // local-only Eloquent profile. Both user ID and integer generation bind it.
        $generationMatches = $this->lifecycle?->sessionMatches($request, $user) ?? true;
        if (! $marked || ! $eligible || ! $generationMatches) {
            $this->sessions->logout($request);
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }

        $guard->setUser($user);

        // Password revocation is NOT reimplemented here. The next middleware is
        // Laravel's AuthenticateSession (`auth.session`), with its login marker.
        return $next($request);
    }
}
