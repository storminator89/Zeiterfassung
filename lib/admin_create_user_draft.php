<?php
// Definitions only. The caller must first establish a known rejected create,
// before writing, or after the original assignment validator and closed rollback.
function admin_create_user_rejected_draft(array $post): ?array
{
    if (($post['add_user'] ?? null) !== '1') { return null; }
    $formFields = ['add_user', 'username', 'password', 'email', 'role', 'department',
        'supervisor', 'regelarbeitszeit', 'ueberstunden', 'csrf_token'];
    foreach (array_keys($post) as $field) {
        if (!in_array($field, $formFields, true)) { return null; } // Other/unknown actions and fields are not this form.
    }
    $draft = [];
    foreach (['username', 'email', 'regelarbeitszeit', 'ueberstunden'] as $field) {
        $value = $post[$field] ?? null;
        $draft[$field] = is_string($value) && strlen($value) <= 1000
            && preg_match('//u', $value) === 1 && preg_match('/[\x00-\x1F\x7F]/', $value) !== 1 ? $value : null;
    }

    return $draft; // No password, CSRF, credential, role or relationship field.
}
