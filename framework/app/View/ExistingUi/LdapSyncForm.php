<?php

namespace App\View\ExistingUi;

use App\Ldap\LdapSyncTarget;
use LogicException;

/** Target-only presentation data, NOT authorization or credential-document admission. */
final readonly class LdapSyncForm
{
    private function __construct(public string $host, public string $port, public string $baseDn) {}

    /** Call only with the fresh authorized service result, not a request-built target. */
    public static function fromTarget(#[\SensitiveParameter] LdapSyncTarget $target): self
    {
        return new self($target->uri, $target->port, $target->baseDn);
    }

    public function __debugInfo(): array
    {
        return ['form' => 'authorized target metadata only'];
    }

    public function __serialize(): array
    {
        throw new LogicException('LDAP form metadata must not be cached or flashed.');
    }
}
