<?php

namespace App\Chrono;

/** Existing raw department commands only; no account/role/SQL input. */
interface LocalWebDepartments
{
    public const COMMAND_FIELDS = [
        'add_department' => ['add_department', 'department_name'],
        'edit_department' => ['edit_department', 'department_id', 'department_name'],
        'delete_department' => ['delete_department', 'department_id'],
    ];

    /** False is only the native empty-create-name refusal; other failures throw. */
    public function apply(array $command): bool;
}
