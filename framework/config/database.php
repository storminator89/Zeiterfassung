<?php

return [
    // Deliberately independent of DB_* / CHRONO_DB_PATH / APP_DATA_DIR.
    // Never point this scratch connection at the legacy time database.
    'default' => 'sqlite',

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],
    ],

    // No migration files are shipped. Schema/SQLite packages own any future baseline/connector.
    // This table name is NOT the existing app_migrations version ledger.
    'migrations' => [
        'table' => 'framework_migrations',
        'update_date_on_publish' => true,
    ],
];
