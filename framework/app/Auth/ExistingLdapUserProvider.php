<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use LogicException;

/**
 * Dormant read/credential boundary for EXISTING directory-managed users only.
 * Not registered as a provider/guard; missing lifecycle admission forbids cutover.
 * Laravel continues to own provider retrieval, guards, sessions and middleware.
 */
final class ExistingLdapUserProvider extends EloquentUserProvider
{
    private const ROLES = ['user', 'supervisor', 'admin'];

    public function __construct(Hasher $hasher, private LdapCredentialVerifier $directory)
    {
        parent::__construct($hasher, LegacyUser::class);
    }

    public function retrieveById($identifier): ?LegacyUser
    {
        $user = parent::retrieveById($identifier);

        return $this->eligible($user) ? $user : null;
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?LegacyUser
    {
        $login = $this->loginCredentials($credentials);
        if ($login === null) {
            return null;
        }

        // Never pass request-supplied columns/closures to Eloquent's query API.
        // No directory query, account creation or source conversion at retrieval.
        $user = parent::retrieveByCredentials([
            'username' => $login['username'],
            'auth_source' => 'ldap',
            'role' => self::ROLES,
        ]);

        return $this->eligible($user) ? $user : null;
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        $login = $this->loginCredentials($credentials);
        if (! $this->eligible($user) || $login === null || $user->username !== $login['username']) {
            return false;
        }

        // Intentionally no catch/local-password fallback, model save, rehash,
        // session/token operation or claim of completed lifecycle admission.
        return $this->directory->verify($login['username'], $login['password']);
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?LegacyUser
    {
        return null; // The legacy schema has no remember_token.
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
        throw new LogicException('LDAP remember-me is not admitted.');
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void
    {
        // Never turn directory credentials into a reusable local password hash,
        // even when a caller requests force=true. Auth-version work belongs #29.
    }

    private function eligible(?Authenticatable $user): bool
    {
        return $user instanceof LegacyUser && $user->exists && $user->getKey() !== null
            && $user->auth_source === 'ldap' && in_array($user->role, self::ROLES, true);
    }

    private function loginCredentials(#[\SensitiveParameter] array $credentials): ?array
    {
        if (array_diff_key($credentials, ['username' => true, 'password' => true]) !== []
            || ! is_string($credentials['username'] ?? null) || ! is_string($credentials['password'] ?? null)) {
            return null;
        }
        $username = trim($credentials['username']);
        // Match native login's empty() rejection, including the literal "0".
        if (empty($username) || empty($credentials['password'])) {
            return null;
        }

        return ['username' => $username, 'password' => $credentials['password']];
    }
}
