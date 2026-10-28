<?php

namespace App\Chrono;

/** One existing create command only; no edit/delete, token or LDAP operation. */
interface LocalWebAdminUsers
{
    public const COMMAND_FIELDS = [
        'add_user' => ['add_user', 'username', 'password', 'email', 'role',
            'department', 'supervisor', 'regelarbeitszeit', 'ueberstunden'],
    ];

    /** False is the original required-field refusal; all other failures throw. */
    public function create(array $command): bool;
}
