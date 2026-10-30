<?php

namespace App\Auth;

use App\Accounts\AccountLifecycleAuthority;
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
        private CacheRepository $attempts, private Closure $writer, private Closure $isV5,
        private ?AccountLifecycleWebIdentity $lifecycle = null) {}

    public function available(): bool
    {
        $v5 = ($this->isV5)();
        $guard = $this->auth->guard('web');
        $user = $guard->user();

        $eligible = $guard instanceof SessionGuard && $user instanceof LegacyUser && $user->exists
            && (int) $user->getKey() > 0 && $user->auth_source === 'local'
            && in_array($user->role, ['user', 'supervisor', 'admin'], true)
            && is_string($user->getAuthPassword()) && $user->getAuthPassword() !== '';
        if (! $eligible) { return false; }
        try {
            $this->authority($user, $v5);
            return true;
        } catch (AuthenticationException|LogicException) {
            return false;
        }
    }

    /** Raw secrets remain request-local; no flash data, DTO, audit field or retained property. */
    public function change(#[\SensitiveParameter] array $form, string $remoteAddress): void
    {
        $v5 = ($this->isV5)();
        if ($v5 && ($this->lifecycle === null || ! $this->lifecycle->requiresGeneration())) {
            throw new LogicException('A generation-bound V5 web identity is required.');
        }
        $guard = $this->auth->guard('web');
        $user = $guard->user();
        if (! $guard instanceof SessionGuard || ! $user instanceof LegacyUser || ! $user->exists || (int) $user->getKey() < 1) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($user->auth_source !== 'local' || ! in_array($user->role, ['user', 'supervisor', 'admin'], true)) {
            throw new AuthorizationException('Only your own local password can be changed.');
        }
        $authority = $this->authority($user, $v5);
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
        try {
            if ($authority !== null) {
                $writer->replaceOwnLocalPasswordForAccountLifecycle($authority, $nextHash);
            } else {
                $writer->replaceOwnLocalPassword((int) $user->getKey(), $user->role, $version, $nextHash);
            }
        } catch (\AttendanceError $error) {
            if ($error->status === 401) { throw new AuthenticationException('Unauthenticated.', ['web']); }
            throw $error;
        }
    }

    /** Explicit fingerprint revocation, not a password change, recovery or device-PIN operation. */
    public function revokeWebApiSessions(#[\SensitiveParameter] array $form, string $remoteAddress): void
    {
        $v5 = ($this->isV5)();
        $guard = $this->auth->guard('web');
        $user = $guard->user();
        if (! $guard instanceof SessionGuard || ! $user instanceof LegacyUser || ! $user->exists || (int) $user->getKey() < 1) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($user->auth_source !== 'local' || ! in_array($user->role, ['user', 'supervisor', 'admin'], true)) {
            throw new AuthorizationException('Only your own local Web/API sessions can be revoked.');
        }
        $authority = $this->authority($user, $v5);
        if (array_diff(array_keys($form), ['_token', 'csrf_token', 'session_action', 'current_password', 'session_confirmation']) !== []
            || ($form['session_action'] ?? null) !== 'revoke_web_api'
            || ($form['session_confirmation'] ?? null) !== '1'
            || ! is_string($form['current_password'] ?? null)) {
            throw new InvalidArgumentException('Abmeldefelder und ausdrückliche Bestätigung prüfen. Andere Einstellungen nicht mitsenden.');
        }
        $current = $form['current_password'];
        // No new-password minimum is imposed on a valid older short password.
        // Bcrypt cannot freshly hash >72 bytes/NUL losslessly; refuse, never truncate.
        if ($current === '' || strlen($current) > 72 || str_contains($current, "\0") || $current === 'defaultpassword') {
            throw new InvalidArgumentException('Aktuelles lokales Passwort unverändert eingeben (höchstens 72 Bytes, kein NUL). Für ältere längere Passwörter den getrennten Passwortwechsel verwenden.');
        }
        if ($this->config->get('hashing.driver') !== 'bcrypt') {
            throw new LogicException('Own session revocation requires the configured bcrypt hasher.');
        }
        // Shared budget: switching between password change and revocation does
        // not create a second password-guessing allowance.
        $this->consumeBudget((int) $user->getKey(), $remoteAddress);
        $oldHash = $user->getAuthPassword();
        $oldInfo = is_string($oldHash) ? password_get_info($oldHash) : [];
        $oldCost = $oldInfo['options']['cost'] ?? null;
        if (($oldInfo['algoName'] ?? null) !== 'bcrypt' || ! is_int($oldCost) || $oldCost < 4 || $oldCost > 31) {
            throw new InvalidArgumentException('Diese Abmeldung unterstützt vorhandene Bcrypt-Passwörter. Für andere gespeicherte Formate den getrennten Passwortwechsel verwenden.');
        }
        $configuredCost = filter_var($this->config->get('hashing.bcrypt.rounds'), FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 4, 'max_range' => 31]]);
        if ($configuredCost === false) { throw new LogicException('A supported configured bcrypt cost is required.'); }
        if (! $this->hasher->check($current, $oldHash)) {
            throw new InvalidArgumentException('Aktuelles lokales Passwort und Abmeldebestätigung prüfen.');
        }
        // Always make a new salted hash, even when current cost is up to date.
        // Authentication still accepts the SAME password; old fingerprint-bound
        // Websessions and JWTs fail their next existing protected check.
        $cost = max($oldCost, $configuredCost); // Never downgrade a stronger existing hash for logout.
        $nextHash = $this->hasher->make($current, ['rounds' => $cost]);
        $nextInfo = password_get_info($nextHash);
        if (hash_equals($oldHash, $nextHash) || ($nextInfo['algoName'] ?? null) !== 'bcrypt'
            || ($nextInfo['options']['cost'] ?? null) !== $cost) {
            throw new LogicException('A fresh non-downgraded password fingerprint is required.');
        }
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) { throw new LogicException('An admitted operation-scoped writer is required.'); }
        try {
            if ($authority !== null) {
                $writer->revokeOwnLocalWebApiSessionsForAccountLifecycle($authority, $nextHash);
            } else {
                $writer->revokeOwnLocalWebApiSessions((int) $user->getKey(), $user->role, hash('sha256', $oldHash), $nextHash);
            }
        } catch (\AttendanceError $error) {
            if ($error->status === 401) { throw new AuthenticationException('Unauthenticated.', ['web']); }
            throw $error;
        }
    }

    /** Profile and refreshed model are trusted composition, never form/session authority fields. */
    private function authority(LegacyUser $user, bool $v5): ?AccountLifecycleAuthority
    {
        if ($v5) {
            if ($this->lifecycle === null || ! $this->lifecycle->requiresGeneration()) {
                throw new LogicException('A generation-bound V5 web identity is required.');
            }
            return $this->lifecycle->capture($user);
        }
        if ($this->lifecycle?->requiresGeneration()) {
            throw new LogicException('V5 web authority cannot target an older password profile.');
        }
        return null;
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
