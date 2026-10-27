<?php

return [
    'driver' => 'bcrypt',
    // Existing PHP password_hash values are verified by Laravel/password_verify,
    // including supported non-bcrypt hashes. No migration-triggered DB write or
    // password-hash change that would also revoke existing API JWTs/LDAP sessions.
    'bcrypt' => ['rounds' => 12, 'verify' => false],
    'rehash_on_login' => false,
];
