<?php

namespace App\Ldap;

use LogicException;
use RuntimeException;
use Throwable;

/**
 * Optional official PHP/OpenLDAP transport, never registered or enabled here.
 * URI/BaseDN/CA/limit are trusted operation composition, NOT request fields.
 * Construction is pure: no file access, extension check, key or connection.
 */
final readonly class PhpLdapSyncTransport implements LdapSyncTransport
{
    public function __construct(
        private mixed $enabled = false,
        private string $uri = '',
        private string $baseDn = '',
        private ?string $caFile = null,
        private int $maxEntries = 1000,
    ) {}

    public function collect(#[\SensitiveParameter] string $bindDn, #[\SensitiveParameter] string $password): array
    {
        if ($this->enabled !== true) {
            throw new RuntimeException('LDAP synchronization transport is not enabled.');
        }
        try {
            // Refuse anonymous/unauthenticated bind. Password bytes are untouched.
            if ($bindDn === '' || trim($bindDn) !== $bindDn || str_contains($bindDn, "\0") || $password === '') {
                throw new RuntimeException;
            }
            $this->requireConfiguration();

            return $this->collectConnected($bindDn, $password);
        } catch (Throwable) {
            // No diagnostics, submitted values, target/CA path or previous error.
            throw new RuntimeException('LDAP synchronization transport is unavailable.');
        }
    }

    public function __debugInfo(): array
    {
        return ['transport' => 'explicit StartTLS sync profile', 'enabled' => $this->enabled === true];
    }

    public function __serialize(): array
    {
        throw new LogicException('LDAP sync transport profiles must not be serialized.');
    }

    private function requireConfiguration(): void
    {
        $parts = parse_url($this->uri);
        if (! is_array($parts) || array_diff(array_keys($parts), ['scheme', 'host', 'port']) !== []
            || ($parts['scheme'] ?? null) !== 'ldap' || ! is_string($parts['host'] ?? null)
            || ! is_int($parts['port'] ?? null) || $parts['port'] < 1 || $parts['port'] > 65535
            || $this->uri !== 'ldap://'.$parts['host'].':'.$parts['port']
            || $this->baseDn === '' || trim($this->baseDn) !== $this->baseDn || str_contains($this->baseDn, "\0")
            || $this->maxEntries < 1 || $this->maxEntries > 10000) {
            throw new RuntimeException;
        }
        $host = $parts['host'];
        $validHost = str_starts_with($host, '[') && str_ends_with($host, ']')
            ? filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            : filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
        if (! $validHost || ! extension_loaded('ldap') || ! function_exists('ldap_count_references')) {
            throw new RuntimeException;
        }
        foreach (['LDAP_OPT_PROTOCOL_VERSION', 'LDAP_OPT_NETWORK_TIMEOUT', 'LDAP_OPT_TIMEOUT',
            'LDAP_OPT_TIMELIMIT', 'LDAP_OPT_REFERRALS', 'LDAP_OPT_X_TLS_REQUIRE_CERT',
            'LDAP_OPT_X_TLS_DEMAND', 'LDAP_OPT_X_TLS_CACERTFILE', 'LDAP_DEREF_NEVER'] as $option) {
            if (! defined($option)) {
                throw new RuntimeException;
            }
        }
        if ($this->caFile !== null && (! str_starts_with($this->caFile, '/')
            || str_contains($this->caFile, "\0") || realpath($this->caFile) !== $this->caFile
            || ! is_file($this->caFile) || ! is_readable($this->caFile))) {
            throw new RuntimeException;
        }
    }

    private function collectConnected(#[\SensitiveParameter] string $bindDn, #[\SensitiveParameter] string $password): array
    {
        $connection = null;
        $result = null;
        try {
            $connection = @ldap_connect($this->uri);
            if (! $connection instanceof \LDAP\Connection) {
                throw new RuntimeException;
            }
            $options = [LDAP_OPT_PROTOCOL_VERSION => 3, LDAP_OPT_NETWORK_TIMEOUT => 5,
                LDAP_OPT_TIMEOUT => 5, LDAP_OPT_TIMELIMIT => 5, LDAP_OPT_REFERRALS => 0,
                LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_DEMAND];
            if ($this->caFile !== null) {
                $options[LDAP_OPT_X_TLS_CACERTFILE] = $this->caFile;
            }
            foreach ($options as $option => $value) {
                if (@ldap_set_option($connection, $option, $value) !== true) {
                    throw new RuntimeException;
                }
            }
            $effective = null;
            if (@ldap_get_option($connection, LDAP_OPT_X_TLS_REQUIRE_CERT, $effective) !== true
                || $effective !== LDAP_OPT_X_TLS_DEMAND) {
                throw new RuntimeException;
            }
            if ($this->caFile !== null && (@ldap_get_option($connection, LDAP_OPT_X_TLS_CACERTFILE, $effective) !== true
                || $effective !== $this->caFile)) {
                throw new RuntimeException;
            }
            // Connection-local verified StartTLS only; never process-global trust.
            if (@ldap_start_tls($connection) !== true || @ldap_bind($connection, $bindDn, $password) !== true) {
                throw new RuntimeException;
            }
            $result = @ldap_search($connection, $this->baseDn, '(objectClass=inetOrgPerson)',
                ['uid', 'cn', 'sn', 'mail', 'ou', 'manager'], 0, $this->maxEntries + 1, 5, LDAP_DEREF_NEVER);
            if (! $result instanceof \LDAP\Result) {
                throw new RuntimeException;
            }
            $code = -1;
            $referrals = null;
            if (@ldap_parse_result($connection, $result, $code, $matchedDn, $diagnostic, $referrals) !== true
                || $code !== 0 || ! in_array($referrals, [null, []], true)
                || @ldap_count_references($connection, $result) !== 0) {
                throw new RuntimeException;
            }
            $count = @ldap_count_entries($connection, $result);
            if (! is_int($count) || $count < 0 || $count > $this->maxEntries) {
                throw new RuntimeException;
            }
            $entries = @ldap_get_entries($connection, $result);
            if (! is_array($entries) || ($entries['count'] ?? null) !== $count || count($entries) !== $count + 1) {
                throw new RuntimeException;
            }
            for ($index = 0; $index < $count; ++$index) {
                if (! isset($entries[$index]) || ! is_array($entries[$index])) {
                    throw new RuntimeException;
                }
            }

            return $entries; // Finally must succeed before the caller receives it.
        } finally {
            $clean = true;
            if ($result instanceof \LDAP\Result) {
                try { if (@ldap_free_result($result) !== true) { $clean = false; } }
                catch (Throwable) { $clean = false; }
            }
            if ($connection instanceof \LDAP\Connection) {
                // Always attempt unbind even if freeing the result failed.
                try { if (@ldap_unbind($connection) !== true) { $clean = false; } }
                catch (Throwable) { $clean = false; }
            }
            if (! $clean) {
                throw new RuntimeException;
            }
        }
    }
}
