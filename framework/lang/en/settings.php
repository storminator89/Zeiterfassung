<?php

// Fixed own-local-password UI labels only; no credential input or model.
return [
    'title' => 'Change your own local password',
    'contract' => 'Your local account only. The new password must contain 12–72 bytes without control characters; it is not truncated. A confirmed change signs you out and requires a new login. Existing web sessions and API tokens fail their next password-version check. No LDAP/terminal PIN change or account recovery. This form does not save other settings.',
    'current' => 'Current password',
    'new' => 'New password (12–72 bytes)',
    'confirm' => 'Confirm new password',
    'submit' => 'Change password and sign out',
];
