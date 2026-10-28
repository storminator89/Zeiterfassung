<?php

namespace App\Auth;

use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Exceptions\HttpResponseException;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/** Own local credentials only. Never recovery, another account, LDAP or V5 activation. */
final class OwnPasswordChange
{
    /** @param Closure(): AttendanceWriter $writer @param Closure(): bool $isV5 Trusted composition only. */
    public function __construct(private Factory $auth, private Hasher $hasher, private Repository $config,
        private CacheRepository $attempts, private Closure $writer, private Closure $isV5) {}

    public function available(): bool
    {
        if (($this->isV5)()) { return false; }
        $guard = $this->auth->guard('web');
        $user = $guard->user();

        return $guard instanceof SessionGuard && $user instanceof LegacyUser && $user->exists
            && (int) $user->getKey() > 0 && $user->auth_source === 'local'
            && in_array($user->role, ['user', 'supervisor', 'admin'], true)
            && is_string($user->getAuthPassword()) && $user->getAuthPassword() !== '';
    }

    /** Raw secrets remain request-local; no flash data, DTO, audit field or retained property. */
    public function change(#[\SensitiveParameter] array $form, string $remoteAddress): void
    {
        if (($this->isV5)()) { throw new LogicException('Own password changes are not admitted for V5.'); }
        $guard = $this->auth->guard('web');
        $user = $guard->user();
        if (! $guard instanceof SessionGuard || ! $user instanceof LegacyUser || ! $user->exists || (int) $user->getKey() < 1) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($user->auth_source !== 'local' || ! in_array($user->role, ['user', 'supervisor', 'admin'], true)) {
            throw new AuthorizationException('Only your own local password can be changed.');
        }
        if (array_diff(array_keys($form), ['_token', 'csrf_token', 'password_action', 'current_password', 'password', 'password_confirmation']) !== []
            || ($form['password_action'] ?? null) !== 'change') {
            throw new InvalidArgumentException('Passwortfelder prüfen. Andere Einstellungen nicht mitsenden.');
        }
        foreach (['current_password', 'password', 'password_confirmation'] as $field) {
            if (! is_string($form[$field] ?? null)) { throw new InvalidArgumentException('Passwortfelder prüfen.'); }
        }
        $current = $form['current_password'];
        $password = $form['password']; // Never trim, normalize or truncate either password.
        if ($current === '' || strlen($current) > 4096 || $current === 'defaultpassword'
            || strlen($password) < 12 || strlen($password) > 72 || $password === 'defaultpassword'
            || preg_match('/[\x00-\x1f\x7f]/', $password) || strlen($form['password_confirmation']) > 72
            || ! hash_equals($password, $form['password_confirmation']) || hash_equals($current, $password)) {
            throw new InvalidArgumentException('Aktuelles Passwort und neues Passwort prüfen: neu 12–72 Bytes, keine Steuerzeichen, passende Bestätigung und nicht das bisherige Passwort.');
        }
        if ($this->config->get('hashing.driver') !== 'bcrypt') {
            throw new LogicException('Own password changes require the configured bcrypt hasher.');
        }
        $this->consumeBudget((int) $user->getKey(), $remoteAddress);
        $oldHash = $user->getAuthPassword();
        if (! is_string($oldHash) || $oldHash === '' || ! $this->hasher->check($current, $oldHash)) {
            throw new InvalidArgumentException('Aktuelles Passwort und neues Passwort prüfen.');
        }
        if ($this->hasher->check($password, $oldHash)) {
            throw new InvalidArgumentException('Das neue Passwort muss sich vom bisherigen unterscheiden.');
        }
        $version = hash('sha256', $oldHash);
        // Standard configured hashing OUTSIDE the existing single writer lock.
        $nextHash = $this->hasher->make($password);
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) { throw new LogicException('An admitted operation-scoped writer is required.'); }
        try { $writer->replaceOwnLocalPassword((int) $user->getKey(), $user->role, $version, $nextHash); }
        catch (\AttendanceError $error) {
            if ($error->status === 401) { throw new AuthenticationException('Unauthenticated.', ['web']); }
            throw $error;
        }
    }

    private function consumeBudget(int $user, string $remoteAddress): void
    {
        // Fixed persistent stock cache, separate from the shared login DB quota.
        // FileStore::increment itself is not atomic across different sessions.
        $store = $this->attempts->getStore();
        if (! $store instanceof FileStore) { throw new LogicException('Persistent file password accounting is required.'); }
        $limiter = new RateLimiter($this->attempts);
        $retry = $store->lock('chrono-own-password-accounting', 10)->block(2, function () use ($limiter, $user, $remoteAddress): int {
            $limits = [
                ['key' => 'chrono-own-password:user:'.hash('sha256', (string) $user), 'max' => 5],
                ['key' => 'chrono-own-password:ip:'.hash('sha256', $remoteAddress), 'max' => 20],
            ];
            $retry = 0;
            foreach ($limits as $limit) {
                // Book BEFORE expensive verification, including failed checks.
                $hits = $limiter->hit($limit['key'], 600);
                $remaining = $limiter->availableIn($limit['key']);
                // Stock FileStore increment returns a count even on put failure.
                // The shared lock plus readback confirms both persisted counter
                // and live expiry; an accounting failure must not allow hashing.
                if (! is_int($hits) || $hits < 1 || $limiter->attempts($limit['key']) !== $hits || $remaining < 1) {
                    throw new LogicException('Password attempt accounting was not confirmed.');
                }
                if ($hits > $limit['max']) { $retry = max($retry, $remaining); }
            }
            return $retry;
        });
        if ($retry > 0) {
            throw new HttpResponseException(new Response('Zu viele Passwortversuche. Bitte später erneut versuchen.', 429,
                ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store', 'Retry-After' => (string) $retry]));
        }
    }

    public function __debugInfo(): array { return ['source' => 'own local password operation']; }
    public function __serialize(): array { throw new LogicException('Password operations must not be serialized.'); }
}
