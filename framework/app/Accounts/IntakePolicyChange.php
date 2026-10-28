<?php

namespace App\Accounts;

use InvalidArgumentException;

/** Strict raw form command. No actor, source, directory, secret or audit field. */
final readonly class IntakePolicyChange
{
    private function __construct(
        public int $expectedRevision,
        public bool $manualEnabled,
        public bool $ldapSyncEnabled,
        public bool $registrationEnabled,
    ) {}

    /** The transport must remove only its known CSRF/action fields beforehand. */
    public static function fromForm(array $form): self
    {
        $keys = ['revision', 'manual_enabled', 'ldap_sync_enabled', 'registration_enabled'];
        if (count($form) !== count($keys) || array_diff($keys, array_keys($form)) !== []) {
            throw new InvalidArgumentException('Ungültige Auswahl der Aufnahmewege.');
        }
        $revision = $form['revision'];
        $maximum = (string) PHP_INT_MAX;
        if (! is_string($revision) || preg_match('/\A[1-9][0-9]*\z/', $revision) !== 1
            || strlen($revision) > strlen($maximum)
            || (strlen($revision) === strlen($maximum) && strcmp($revision, $maximum) > 0)) {
            throw new InvalidArgumentException('Ungültige Richtlinienversion. Bitte neu laden.');
        }
        foreach (['manual_enabled', 'ldap_sync_enabled', 'registration_enabled'] as $field) {
            if (! in_array($form[$field], ['0', '1'], true)) {
                throw new InvalidArgumentException('Ungültige Auswahl der Aufnahmewege.');
            }
        }

        return new self((int) $revision, $form['manual_enabled'] === '1',
            $form['ldap_sync_enabled'] === '1', $form['registration_enabled'] === '1');
    }
}
