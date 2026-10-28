<?php

namespace App\Ldap;

use JsonSerializable;
use LogicException;
use RuntimeException;
use Throwable;

/** Target-only GET remains pure; collection checks the same immutable CA before/after TLS. */
final readonly class PinnedLdapSyncTransport implements LdapSyncTransport, JsonSerializable
{
    public function __construct(private LdapSyncTransport $transport, private LdapSyncFileSources $files) {}

    public function target(): LdapSyncTarget { return $this->transport->target(); }

    public function collect(#[\SensitiveParameter] string $bindDn, #[\SensitiveParameter] string $password): array
    {
        try {
            $this->files->admittedCaPath();
            $entries = $this->transport->collect($bindDn, $password);
            $this->files->admittedCaPath(); // Refuse a changed source before any DB import.
            return $entries;
        } catch (Throwable) { throw new RuntimeException('Pinned LDAP synchronization transport is unavailable.'); }
    }

    public function __debugInfo(): array { return ['transport' => 'pinned explicit LDAP sync source']; }
    public function jsonSerialize(): array { return $this->__debugInfo(); }
    public function __serialize(): array { throw new LogicException('LDAP sync transports must not be serialized.'); }
}
