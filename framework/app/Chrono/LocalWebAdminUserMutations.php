<?php

namespace App\Chrono;

/** Existing edit/delete forms only; never account creation, intake, tokens or LDAP synchronization. */
interface LocalWebAdminUserMutations
{
    public const COMMAND_FIELDS = [
        'edit_user' => ['edit_user', 'user_id', 'username', 'email', 'role', 'password',
            'department', 'supervisor', 'regelarbeitszeit', 'ueberstunden'],
        'delete_user' => ['delete_user', 'user_id'],
    ];

    public function apply(#[\SensitiveParameter] array $command): AdminUserMutationResult;
}
