<?php

namespace App\Chrono;

/** Inactive Admin command boundary; callers still need session/CSRF/throttle. */
interface LocalWebLdapSync
{
    public const COMMAND_FIELDS = ['sync_ldap', 'ldap_host', 'ldap_port', 'ldap_user', 'ldap_pass', 'ldap_base_dn'];

    /** Native manager plan only; never directory entries, passwords or settings roots. */
    public function synchronize(#[\SensitiveParameter] array $command): array;
}
