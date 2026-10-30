<?php

// Fixed own-local-password UI labels only; no credential input or model.
return [
    'title' => 'Change your own local password',
    'contract' => 'Your local account only. The new password must contain 12–72 bytes without control characters; it is not truncated. A confirmed change signs you out and requires a new login. Existing web sessions and API tokens fail their next password-version check. No LDAP/terminal PIN change or account recovery. This form does not save other settings.',
    'current' => 'Current password',
    'new' => 'New password (12–72 bytes)',
    'confirm' => 'Confirm new password',
    'submit' => 'Change password and sign out',
    'sessions_title' => 'Sign out existing Web/API logins',
    'sessions_contract' => 'Your own local account with an existing Bcrypt password only: after confirmation, existing Web sessions and API tokens fail their next protected check, including this session. Your password stays the same and can still be used for a new login. Already running requests are not retroactively cancelled. This does not delete a device list or revoke LDAP, terminal PINs or terminal grants. Other settings are not saved. Current password: at most 72 bytes, no NUL; change older longer passwords using the separate password-change form first.',
    'sessions_confirm' => 'I want to revoke all existing Web/API logins of my local account and sign in again afterwards.',
    'sessions_submit' => 'Revoke logins and sign out',
];
