<?php

// Trusted deployment metadata only. Both switches remain closed by default;
// loading/caching this file never opens a database or adopts an existing file.
// Record this manifest only after the separate reviewed offline-v2 admission.
return [
    'read_enabled' => env('FRAMEWORK_CHRONO_READ_ENABLED', false),
    'write_enabled' => env('FRAMEWORK_CHRONO_WRITE_ENABLED', false),
    'admission' => [
        'path' => env('FRAMEWORK_CHRONO_DB_PATH'),
        'device' => env('FRAMEWORK_CHRONO_DB_DEVICE'),
        'inode' => env('FRAMEWORK_CHRONO_DB_INODE'),
        'schema_version' => env('FRAMEWORK_CHRONO_DB_SCHEMA_VERSION'),
        'schema_signature' => env('FRAMEWORK_CHRONO_DB_SCHEMA_SIGNATURE'),
        'foreign_keys' => env('FRAMEWORK_CHRONO_DB_FOREIGN_KEYS'),
        'journal_mode' => env('FRAMEWORK_CHRONO_DB_JOURNAL_MODE'),
        'synchronous' => env('FRAMEWORK_CHRONO_DB_SYNCHRONOUS'),
    ],
];
