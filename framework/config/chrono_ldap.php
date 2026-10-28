<?php

return [
    // Explicit trusted operator LOGIN metadata only; defaults remain disabled.
    // No request/config-table discovery, service-bind credentials, sync target/key
    // admission, CA access or LDAP/network work occurs while loading this config.
    'enabled' => env('FRAMEWORK_CHRONO_LDAP_LOGIN_ENABLED', false),
    'uri' => env('FRAMEWORK_CHRONO_LDAP_LOGIN_URI'),
    'base_dn' => env('FRAMEWORK_CHRONO_LDAP_LOGIN_BASE_DN'),
    'ca_file' => env('FRAMEWORK_CHRONO_LDAP_LOGIN_CA_FILE'),
];
