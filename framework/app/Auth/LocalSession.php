<?php

namespace App\Auth;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

/** Backend actions only; callers MUST use the documented web/CSRF/throttle stack. */
final class LocalSession
{
    public const SOURCE_MARKER = 'chrono_web_auth_source';
    public const PASSWORD_MARKER = 'password_hash_web';

    public function __construct(private Factory $auth, private ?WriterLoginBudget $loginBudget = null,
        private ?AccountLifecycleWebIdentity $lifecycle = null) {}

    public function attempt(#[\SensitiveParameter] Request $request): bool
    {
        $username = $request->input('username');
        $password = $request->input('password');
        if (! is_string($username) || ! is_string($password)
            || trim($username) === '' || $password === '') {
            return false;
        }
        if ($this->loginBudget !== null) {
            try {
                $limited = $this->loginBudget->limited(trim($username), (string) ($request->server('REMOTE_ADDR') ?? 'local'));
            } catch (Throwable) {
                throw new ServiceUnavailableHttpException(null, 'Anmeldung derzeit nicht bestätigt.');
            }
            if ($limited) {
                // The operation has already confirmed COMMIT and idle state.
                throw new HttpResponseException(new Response('Too many login attempts. Try again later.', 429,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Retry-After' => '600']));
            }
        }
        if ($this->lifecycle?->requiresGeneration()) {
            // A failed/interrupted login must never reuse another login's old
            // generation marker, even if SessionGuard updated its ID/hash first.
            $request->session()->forget(AccountLifecycleWebIdentity::GENERATION_MARKER);
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
                $this->lifecycle?->establishSession($request, $user);

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
        $credentials = [
            'username' => trim($username),
            'password' => $password,
            'auth_source' => 'local',
            'role' => ['user', 'supervisor', 'admin'],
        ];
        if ($this->lifecycle?->requiresGeneration()) {
            if (! $guard instanceof SessionGuard) {
                throw new ServiceUnavailableHttpException(null, 'Anmeldung derzeit nicht bestätigt.');
            }
            // Stock Eloquent local profile must reject inactive/missing-generation
            // rows too. Keep Laravel credential checking/session fixation handling.
            $accepted = $guard->attemptWhen($credentials, fn ($user): bool => $this->lifecycle->admits($user), false);
        } else {
            $accepted = $guard->attempt($credentials, false);
        }
        if ($accepted) {
            $request->session()->put(self::SOURCE_MARKER, 'local');
            if ($this->lifecycle?->requiresGeneration()) {
                $user = $guard->user();
                $marker = $request->session()->get(self::PASSWORD_MARKER);
                if (! $user instanceof LegacyUser || ! $this->lifecycle->admits($user)
                    || ! $request->session()->exists(self::PASSWORD_MARKER)
                    || ! is_string($marker) || $marker === '') {
                    $this->logout($request);
                    throw new ServiceUnavailableHttpException(null, 'Anmeldung derzeit nicht bestätigt.');
                }
                $this->lifecycle->establishSession($request, $user);
            }
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
