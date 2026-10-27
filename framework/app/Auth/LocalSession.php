<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Request;

/** Backend actions only; callers MUST use the documented web/CSRF/throttle stack. */
final class LocalSession
{
    public function __construct(private Factory $auth) {}

    public function attempt(Request $request): bool
    {
        $username = $request->input('username');
        $password = $request->input('password');
        if (! is_string($username) || ! is_string($password)
            || trim($username) === '' || $password === '' || $password === 'defaultpassword') {
            return false;
        }

        // Standard SessionGuard regenerates both ID and CSRF token on success and
        // stores the standard auth.session password marker. No native session is
        // read/copied, no LDAP bind/fallback, token issuance or password rewrite.
        return $this->auth->guard('web')->attempt([
            'username' => trim($username),
            'password' => $password,
            'auth_source' => 'local',
            'role' => ['user', 'supervisor', 'admin'],
        ], false);
    }

    public function logout(Request $request): void
    {
        $this->auth->guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
