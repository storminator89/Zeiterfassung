<?php

namespace App\Accounts;

use InvalidArgumentException;
use LogicException;

/**
 * Captured by a trusted authenticated facade, never from request/session input.
 * This is a V5 snapshot, not an authentication provider or a bearer credential.
 */
final readonly class AccountLifecycleAuthority
{
    private function __construct(
        public int $id,
        public string $source,
        public string $role,
        public int $generation,
        #[\SensitiveParameter] private string $passwordVersion,
    ) {}

    /** @param array<string, mixed> $row Current authenticated account snapshot. */
    public static function capture(#[\SensitiveParameter] array $row): self
    {
        if (! self::activeRow($row)
            || ! is_int($row['id'] ?? null) || $row['id'] < 1
            || ! in_array($row['auth_source'] ?? null, ['local', 'ldap'], true)
            || ! in_array($row['role'] ?? null, ['user', 'supervisor', 'admin'], true)
            || ! is_string($row['password'] ?? null) || $row['password'] === '') {
            throw new InvalidArgumentException('A current active V5 authenticated account snapshot is required.');
        }

        return new self($row['id'], $row['auth_source'], $row['role'], $row['auth_generation'],
            hash('sha256', $row['password']));
    }

    /** Same-snapshot/same-lock verification; never adopt a missing generation. */
    public function matches(#[\SensitiveParameter] array $row): bool
    {
        return self::activeRow($row)
            && ($row['id'] ?? null) === $this->id
            && ($row['auth_source'] ?? null) === $this->source
            && ($row['role'] ?? null) === $this->role
            && $row['auth_generation'] === $this->generation
            && is_string($row['password'] ?? null) && $row['password'] !== ''
            && hash_equals($this->passwordVersion, hash('sha256', $row['password']));
    }

    /** Check the existing facade tuple as well as the V5 captured generation. */
    public function binds(int $id, ?string $source, #[\SensitiveParameter] ?string $version, ?string $role): bool
    {
        return $id === $this->id && $source === $this->source
            && ($role === null || $role === $this->role)
            && is_string($version) && hash_equals($this->passwordVersion, $version);
    }

    /** Syntactic login eligibility only; knowing the password remains a recovery acceptance gate. */
    public static function locallyLoginEligibleAdmin(#[\SensitiveParameter] array $row): bool
    {
        $password = $row['password'] ?? null;

        return self::activeRow($row)
            && ($row['role'] ?? null) === 'admin' && ($row['auth_source'] ?? null) === 'local'
            && is_string($row['username'] ?? null) && $row['username'] !== ''
            && trim($row['username']) === $row['username']
            && is_string($password) && (password_get_info($password)['algo'] ?? null) !== null
            // LocalSession refuses both values even if their stored hash is valid.
            && ! password_verify('', $password)
            && ! password_verify('defaultpassword', $password);
    }

    private static function activeRow(array $row): bool
    {
        return ($row['account_status'] ?? null) === 'active'
            && is_int($row['auth_generation'] ?? null) && $row['auth_generation'] >= 1;
    }

    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'source' => $this->source, 'role' => $this->role,
            'generation' => $this->generation];
    }

    public function __serialize(): array
    {
        throw new LogicException('Captured V5 account authority must not be serialized.');
    }
}
