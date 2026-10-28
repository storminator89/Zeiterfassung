<?php

namespace App\Ldap;

use DomainException;
use InvalidArgumentException;
use LogicException;
use PDO;

/**
 * In-process stage from a separately approved, completed LDAP transport.
 * Shape validation is NOT proof of TLS/bind/search/cleanup or account ownership.
 * No network, encryption, configuration lookup, actor or connection creation.
 */
final readonly class MaterializedLdapImport
{
    private function __construct(
        public array $entries,
        public string $host,
        public string $port,
        public string $bindUser,
        #[\SensitiveParameter] public string $ciphertext,
        public string $baseDn,
    ) {}

    /** Accept existing v2 ciphertext only, never a raw bind-password parameter. */
    public static function fromArray(#[\SensitiveParameter] array $stage): self
    {
        $keys = ['entries', 'host', 'port', 'bind_user', 'ciphertext', 'base_dn'];
        if (count($stage) !== count($keys) || array_diff($keys, array_keys($stage)) !== []) {
            throw new InvalidArgumentException('Invalid materialized LDAP import stage.');
        }
        foreach (array_slice($keys, 1) as $field) {
            if (! is_string($stage[$field]) || trim($stage[$field]) === '' || str_contains($stage[$field], "\0")) {
                throw new InvalidArgumentException('Invalid materialized LDAP import stage.');
            }
        }
        if (! ctype_digit($stage['port']) || (int) $stage['port'] < 1 || (int) $stage['port'] > 65535) {
            throw new InvalidArgumentException('Invalid materialized LDAP import stage.');
        }
        $encoded = str_starts_with($stage['ciphertext'], 'v2:') ? substr($stage['ciphertext'], 3) : '';
        $ciphertext = base64_decode($encoded, true);
        // Native v2 envelope: 12-byte IV, 16-byte GCM tag, nonempty ciphertext.
        // This only validates its shape; this class never decrypts/authenticates it.
        if (! is_string($ciphertext) || strlen($ciphertext) < 29 || base64_encode($ciphertext) !== $encoded) {
            throw new InvalidArgumentException('Invalid materialized LDAP import stage.');
        }
        $entries = $stage['entries'];
        if (! is_array($entries) || ! isset($entries['count']) || ! is_int($entries['count'])
            || $entries['count'] < 0 || count($entries) !== $entries['count'] + 1) {
            throw new InvalidArgumentException('Invalid materialized LDAP import stage.');
        }
        for ($index = 0; $index < $entries['count']; ++$index) {
            if (! isset($entries[$index]) || ! is_array($entries[$index])) {
                throw new InvalidArgumentException('Invalid materialized LDAP import stage.');
            }
            $entry = $entries[$index];
            // Preserve the native missing-uid/mail skip and first-value semantics.
            if (! isset($entry['uid'][0], $entry['mail'][0])) {
                continue;
            }
            foreach (['uid', 'mail', 'cn', 'sn', 'ou', 'manager'] as $attribute) {
                if (isset($entry[$attribute][0])
                    && (! is_array($entry[$attribute]) || ! is_string($entry[$attribute][0]))) {
                    throw new InvalidArgumentException('Invalid materialized LDAP import stage.');
                }
            }
            if (isset($entry['dn']) && ! is_string($entry['dn'])) {
                throw new InvalidArgumentException('Invalid materialized LDAP import stage.');
            }
        }

        return new self($entries, $stage['host'], $stage['port'], $stage['bind_user'], $stage['ciphertext'], $stage['base_dn']);
    }

    /** Caller owns the same IMMEDIATE writer transaction used for the import. */
    public function refuseLocalAccountCollisions(PDO $pdo): void
    {
        if (! $pdo->inTransaction()) {
            throw new LogicException('LDAP collision checks require the caller writer transaction.');
        }
        $statement = $pdo->prepare('SELECT auth_source FROM users WHERE username=?');
        for ($index = 0; $index < $this->entries['count']; ++$index) {
            $entry = $this->entries[$index];
            if (! isset($entry['uid'][0], $entry['mail'][0])) {
                continue;
            }
            // Exactly the native username lookup: no trimming, case folding,
            // guessed DN equivalence or email-based account linking.
            $statement->execute([$entry['uid'][0]]);
            $existing = $statement->fetch(PDO::FETCH_ASSOC);
            $statement->closeCursor();
            if ($existing !== false && $existing['auth_source'] !== 'ldap') {
                // Refuse the WHOLE stage before settings, departments or users.
                // Unknown sources fail closed; do not export names, IDs or DNs.
                throw new DomainException('LDAP import refused: a username belongs to a non-directory account.');
            }
        }
    }
}
