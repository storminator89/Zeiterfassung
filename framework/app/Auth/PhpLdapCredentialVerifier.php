<?php

namespace App\Auth;

use RuntimeException;
use SensitiveParameter;
use Throwable;

/** Explicit trusted configuration only. No registration, secrets store or account lifecycle. */
final class PhpLdapCredentialVerifier implements LdapCredentialVerifier
{
    public function __construct(
        // Keep the supplied type so weak PHP callers cannot enable via "false"/1.
        private readonly mixed $enabled = false,
        private readonly string $uri = '',
        private readonly string $baseDn = '',
        private readonly ?string $caFile = null,
    ) {}

    public function verify(#[SensitiveParameter] string $username, #[SensitiveParameter] string $password): bool
    {
        if ($this->enabled !== true) {
            throw new RuntimeException('LDAP verification is not enabled.');
        }
        // No anonymous/unauthenticated simple bind; never trim or decode a password.
        if ($username === '' || str_contains($username, "\0") || $password === '') {
            return false;
        }

        try {
            $this->validateConfiguration();

            return $this->verifyConnected($username, $password);
        } catch (Throwable) {
            // No server diagnostics, DN, username, password, path or previous exception.
            throw new RuntimeException('LDAP verification is unavailable.');
        }
    }

    private function validateConfiguration(): void
    {
        if (! extension_loaded('ldap') || ! function_exists('ldap_count_references')) {
            throw new RuntimeException('LDAP extension unavailable.');
        }
        foreach (['LDAP_OPT_PROTOCOL_VERSION', 'LDAP_OPT_NETWORK_TIMEOUT', 'LDAP_OPT_TIMEOUT',
            'LDAP_OPT_TIMELIMIT', 'LDAP_OPT_REFERRALS', 'LDAP_OPT_X_TLS_REQUIRE_CERT',
            'LDAP_OPT_X_TLS_DEMAND', 'LDAP_OPT_X_TLS_CACERTFILE'] as $option) {
            if (! defined($option)) {
                throw new RuntimeException('Required LDAP option unavailable.');
            }
        }
        $parts = parse_url($this->uri);
        if (! is_array($parts) || array_diff(array_keys($parts), ['scheme', 'host', 'port']) !== []
            || ($parts['scheme'] ?? null) !== 'ldap' || ! is_string($parts['host'] ?? null)
            || ! is_int($parts['port'] ?? null) || $parts['port'] < 1 || $parts['port'] > 65535
            || $this->uri !== 'ldap://'.$parts['host'].':'.$parts['port']
            || $this->baseDn === '' || trim($this->baseDn) !== $this->baseDn || str_contains($this->baseDn, "\0")) {
            throw new RuntimeException('Explicit StartTLS configuration required.');
        }
        $host = $parts['host'];
        $validHost = str_starts_with($host, '[') && str_ends_with($host, ']')
            ? filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            : filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
        if (! $validHost) {
            throw new RuntimeException('A single explicit LDAP host is required.');
        }
        if ($this->caFile !== null && (! str_starts_with($this->caFile, '/')
            || str_contains($this->caFile, "\0") || realpath($this->caFile) !== $this->caFile
            || ! is_file($this->caFile) || ! is_readable($this->caFile))) {
            throw new RuntimeException('Explicit readable CA file required.');
        }
    }

    private function verifyConnected(#[SensitiveParameter] string $username, #[SensitiveParameter] string $password): bool
    {
        $connection = null;
        $results = [];
        try {
            // One URI includes its port; no deprecated two-argument connect/failover list.
            $connection = @ldap_connect($this->uri);
            if (! $connection instanceof \LDAP\Connection) {
                throw new RuntimeException('Connection unavailable.');
            }
            $options = [LDAP_OPT_PROTOCOL_VERSION => 3, LDAP_OPT_NETWORK_TIMEOUT => 5,
                LDAP_OPT_TIMEOUT => 5, LDAP_OPT_TIMELIMIT => 5, LDAP_OPT_REFERRALS => 0,
                LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_DEMAND];
            if ($this->caFile !== null) {
                $options[LDAP_OPT_X_TLS_CACERTFILE] = $this->caFile;
            }
            foreach ($options as $option => $value) {
                if (@ldap_set_option($connection, $option, $value) !== true) {
                    throw new RuntimeException('Connection security option unavailable.');
                }
            }
            $effective = null;
            if (@ldap_get_option($connection, LDAP_OPT_X_TLS_REQUIRE_CERT, $effective) !== true
                || $effective !== LDAP_OPT_X_TLS_DEMAND) {
                throw new RuntimeException('Certificate validation unavailable.');
            }
            if ($this->caFile !== null && (@ldap_get_option($connection, LDAP_OPT_X_TLS_CACERTFILE, $effective) !== true
                || $effective !== $this->caFile)) {
                throw new RuntimeException('Connection-scoped CA unavailable.');
            }
            // PHP/OpenLDAP refreshes this connection's TLS context here. Never set
            // process-global TLS defaults or bypass certificate/name verification.
            if (@ldap_start_tls($connection) !== true) {
                throw new RuntimeException('Verified StartTLS unavailable.');
            }
            $dn = 'uid='.ldap_escape($username, '', LDAP_ESCAPE_DN).','.$this->baseDn;
            if (@ldap_bind($connection, $dn, $password) !== true) {
                if (ldap_errno($connection) === 49) {
                    return false;
                }
                throw new RuntimeException('Directory bind unavailable.');
            }
            $filter = '(uid='.ldap_escape($username, '', LDAP_ESCAPE_FILTER).')';
            $result = @ldap_search($connection, $this->baseDn, $filter, ['uid'], false, 2, 5, LDAP_DEREF_NEVER);
            if (! $result instanceof \LDAP\Result) {
                throw new RuntimeException('Directory search unavailable.');
            }
            $results[] = $result;
            $this->requireIdentity($connection, $result, $username);
            // Confirm the actual bound DN has the same exact UID too, without
            // inventing a case-folded/string-normalized DN-equivalence algorithm.
            $result = @ldap_read($connection, $dn, $filter, ['uid'], false, 1, 5, LDAP_DEREF_NEVER);
            if (! $result instanceof \LDAP\Result) {
                throw new RuntimeException('Bound identity unavailable.');
            }
            $results[] = $result;
            $this->requireIdentity($connection, $result, $username);

            return true;
        } finally {
            $clean = true;
            foreach ($results as $result) {
                try { if (@ldap_free_result($result) !== true) { $clean = false; } }
                catch (Throwable) { $clean = false; }
            }
            if ($connection instanceof \LDAP\Connection) {
                try { if (@ldap_unbind($connection) !== true) { $clean = false; } }
                catch (Throwable) { $clean = false; }
            }
            if (! $clean) {
                throw new RuntimeException('Directory cleanup unavailable.');
            }
        }
    }

    private function requireIdentity(\LDAP\Connection $connection, \LDAP\Result $result, #[SensitiveParameter] string $username): void
    {
        $code = -1;
        $referrals = null;
        if (@ldap_parse_result($connection, $result, $code, $matchedDn, $diagnostic, $referrals) !== true
            || $code !== 0 || ! in_array($referrals, [null, []], true)
            || @ldap_count_references($connection, $result) !== 0
            || @ldap_count_entries($connection, $result) !== 1) {
            throw new RuntimeException('Complete unique directory identity required.');
        }
        $entries = @ldap_get_entries($connection, $result);
        if (! is_array($entries) || ($entries['count'] ?? null) !== 1
            || ($entries[0]['uid']['count'] ?? null) !== 1
            || ($entries[0]['uid'][0] ?? null) !== $username) {
            throw new RuntimeException('Exact directory identity required.');
        }
    }
}
