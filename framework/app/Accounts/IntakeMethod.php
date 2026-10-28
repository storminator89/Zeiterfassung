<?php

namespace App\Accounts;

/** New-account intake only. Existing authentication and first Admin are separate. */
enum IntakeMethod: string
{
    case Manual = 'manual';
    case LdapSync = 'ldap_sync';
    case Registration = 'registration';
}
