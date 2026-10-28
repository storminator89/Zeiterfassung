<?php

namespace App\Ldap;

/**
 * Completed, materialized import search, including successful transport cleanup.
 * The caller MUST check current local administration and intake policy first.
 * No DB write, account linking, encryption or credential persistence here.
 */
interface LdapSyncTransport
{
    /** Native ldap_get_entries shape, retaining first-value/import ordering. */
    public function collect(#[\SensitiveParameter] string $bindDn, #[\SensitiveParameter] string $password): array;
}
