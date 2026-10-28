<?php

namespace App\Auth;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

/** Backend actions only; callers MUST use the documented web/CSRF/throttle stack. */
final class LocalSession
{
    public const SOURCE_MARKER = 'chrono_web_auth_source';
    public const PASSWORD_MARKER = 'password_hash_web';

    public function __construct(private Factory $auth) {}

    public function attempt(#[\SensitiveParameter] Request $request): bool
    {
        $username = $request->input('username');
        $password = $request->input('password');
        if (! is_string($username) || ! is_string($password)
            || trim($username) === '' || $password === '') {
            return false;
        }
        $guard = $this->auth->guard('web');
        $provider = $guard instanceof SessionGuard ? $guard->getProvider() : null;
        if ($provider instanceof ExistingAccountUserProvider) {
            try {
                // Standard guard performs credential verification, fixation/CSRF
                // defenses and its existing password-hash marker, remember=false.
                if (! $guard->attempt(['username' => trim($username), 'password' => $password], false)) {
                    return false;
                }
                $user = $guard->user();
                $marker = $request->session()->get(self::PASSWORD_MARKER);
                if (! $provider->admits($user) || ! $request->session()->exists(self::PASSWORD_MARKER)
                    || ! is_string($marker) || $marker === '') {
                    $this->logout($request);
                    throw new RuntimeException('Standard login marker unavailable.');
                }
                // Never derive identity/source from a request-selected field.
                $request->session()->put(self::SOURCE_MARKER, $provider->context($user)[0]);

                return true;
            } catch (Throwable) {
                // Operational failure is NOT bad credentials, local fallback,
                // confirmed logout, or permission to retry via another identity.
                throw new ServiceUnavailableHttpException(null, 'Anmeldung derzeit nicht bestätigt.');
            }
        }
        if ($password === 'defaultpassword') {
            return false;
        }

        // Unchanged local-only credentials, hasher and remember=false off-path.
        $accepted = $guard->attempt([
            'username' => trim($username),
            'password' => $password,
            'auth_source' => 'local',
            'role' => ['user', 'supervisor', 'admin'],
        ], false);
        if ($accepted) {
            $request->session()->put(self::SOURCE_MARKER, 'local');
        }

        return $accepted;
    }

    public function logout(Request $request): void
    {
        $this->auth->guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
