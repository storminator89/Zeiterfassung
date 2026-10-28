<?php

namespace App\Accounts;

use UnexpectedValueException;

/** Verified database values, never configuration/session defaults or credentials. */
final readonly class IntakePolicy
{
    private function __construct(
        public bool $manualEnabled,
        public bool $ldapSyncEnabled,
        public bool $registrationEnabled,
        public int $revision,
        public int $updatedAt,
        public ?int $updatedBy,
    ) {}

    public static function fromRow(array $row): self
    {
        $keys = ['id', 'manual_enabled', 'ldap_sync_enabled', 'registration_enabled', 'revision', 'updated_at', 'updated_by'];
        if (count($row) !== count($keys) || array_diff($keys, array_keys($row)) !== []
            || $row['id'] !== 1 || ! is_int($row['revision']) || $row['revision'] < 1
            || ! is_int($row['updated_at']) || $row['updated_at'] < 0
            || ($row['updated_by'] !== null && (! is_int($row['updated_by']) || $row['updated_by'] < 1))) {
            throw new UnexpectedValueException('The persisted intake policy is invalid.');
        }
        foreach (['manual_enabled', 'ldap_sync_enabled', 'registration_enabled'] as $field) {
            if (! in_array($row[$field], [0, 1], true)) {
                throw new UnexpectedValueException('The persisted intake policy is invalid.');
            }
        }

        return new self($row['manual_enabled'] === 1, $row['ldap_sync_enabled'] === 1,
            $row['registration_enabled'] === 1, $row['revision'], $row['updated_at'], $row['updated_by']);
    }

    public function allows(IntakeMethod $method): bool
    {
        return match ($method) {
            IntakeMethod::Manual => $this->manualEnabled,
            IntakeMethod::LdapSync => $this->ldapSyncEnabled,
            IntakeMethod::Registration => $this->registrationEnabled,
        };
    }
}
