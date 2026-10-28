<?php

namespace App\Auth;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use LogicException;
use SensitiveParameter;

/** Existing-account profile: no enrollment/source conversion; local standard rehash only. */
final class ExistingAccountUserProvider extends EloquentUserProvider
{
    private const ROLES = ['user', 'supervisor', 'admin'];

    public function __construct(Hasher $hasher, private ExistingLdapUserProvider $directory)
    {
        parent::__construct($hasher, LegacyUser::class);
    }

    public function admits(#[SensitiveParameter] ?Authenticatable $user): bool
    {
        if (! $user instanceof LegacyUser || ! $user->exists || (int) $user->getKey() < 1
            || ! is_string($user->getAuthPassword()) || ! $user->getAuthPassword()) {
            return false;
        }

        return ($user->auth_source === 'local' && in_array($user->role, self::ROLES, true))
            || ($user->auth_source === 'ldap' && $user->role === 'user');
    }

    /** Trusted source and DB-hash fingerprint only; never the directory password or raw DB hash. */
    public function context(#[SensitiveParameter] LegacyUser $user): array
    {
        if (! $this->admits($user)) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }

        return [$user->auth_source, hash('sha256', $user->getAuthPassword())];
    }

    public function retrieveById($identifier): ?LegacyUser
    {
        $user = parent::retrieveById($identifier);

        return $this->admits($user) ? $user : null;
    }

    public function retrieveByCredentials(#[SensitiveParameter] array $credentials): ?LegacyUser
    {
        $login = $this->loginCredentials($credentials);
        if ($login === null) {
            return null;
        }

        // Fixed columns/values only. Request fields/closures cannot become query predicates.
        $user = parent::retrieveByCredentials([
            'username' => $login['username'],
            'auth_source' => ['local', 'ldap'],
            'role' => self::ROLES,
        ]);

        return $this->admits($user) ? $user : null;
    }

    public function validateCredentials(Authenticatable $user, #[SensitiveParameter] array $credentials): bool
    {
        $login = $this->loginCredentials($credentials);
        if (! $this->admits($user) || $login === null || $user->username !== $login['username']) {
            return false;
        }
        if ($user->auth_source === 'ldap') {
            // No catch/fallback: operational errors remain operational errors.
            return $this->directory->validateCredentials($user, $login);
        }

        return $login['password'] !== 'defaultpassword' && parent::validateCredentials($user, $login);
    }

    public function retrieveByToken($identifier, #[SensitiveParameter] $token): ?LegacyUser
    {
        return null; // The existing schema has no remember_token column.
    }

    public function updateRememberToken(Authenticatable $user, #[SensitiveParameter] $token): void
    {
        throw new LogicException('Existing-account remember-me is not admitted.');
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[SensitiveParameter] array $credentials, bool $force = false): void
    {
        if ($this->admits($user) && $user->auth_source === 'local') {
            // Preserve the existing local framework contract. Automatic login
            // rehash remains disabled by the unchanged hashing configuration.
            parent::rehashPasswordIfRequired($user, $credentials, $force);
        }
        // Directory credentials never become a local hash, including force=true.
    }

    private function loginCredentials(#[SensitiveParameter] array $credentials): ?array
    {
        if (array_diff_key($credentials, ['username' => true, 'password' => true]) !== []
            || ! is_string($credentials['username'] ?? null) || ! is_string($credentials['password'] ?? null)) {
            return null;
        }
        $username = trim($credentials['username']);
        // Preserve local literal "0" credentials; the unchanged LDAP delegate still refuses them.
        if ($username === '' || $credentials['password'] === '') {
            return null;
        }

        return ['username' => $username, 'password' => $credentials['password']];
    }
}
