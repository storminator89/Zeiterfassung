<?php

namespace App\Ldap;

use InvalidArgumentException;
use LogicException;
use Throwable;

/** Pure target shape, NOT proof of endpoint/CA admission or request authorization. */
final readonly class LdapSyncTarget
{
    private function __construct(public string $uri, public string $port, public string $baseDn) {}

    public static function fromTrustedUri(string $uri, string $baseDn): self
    {
        try {
            $parts = parse_url($uri);
            if (! is_array($parts) || array_diff(array_keys($parts), ['scheme', 'host', 'port']) !== []
                || ($parts['scheme'] ?? null) !== 'ldap' || ! is_string($parts['host'] ?? null)
                || ! is_int($parts['port'] ?? null) || $parts['port'] < 1 || $parts['port'] > 65535
                || $uri !== 'ldap://'.$parts['host'].':'.$parts['port']
                || $baseDn === '' || trim($baseDn) !== $baseDn || str_contains($baseDn, "\0")) {
                throw new InvalidArgumentException;
            }
            $host = $parts['host'];
            $validHost = str_starts_with($host, '[') && str_ends_with($host, ']')
                ? filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
                : filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
            if (! $validHost) {
                throw new InvalidArgumentException;
            }

            return new self($uri, (string) $parts['port'], $baseDn);
        } catch (Throwable) {
            throw new InvalidArgumentException('A single explicit canonical LDAP sync target is required.');
        }
    }

    public function __debugInfo(): array
    {
        return ['target' => 'explicit operation profile'];
    }

    public function __serialize(): array
    {
        throw new LogicException('LDAP sync targets must not be serialized.');
    }
}
