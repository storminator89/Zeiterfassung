<?php

return [
    // Inactive backend only: `chrono` is deliberately NOT a configured connection.
    // No login routes, native-PHP session bridge, password broker or new user table.
    'defaults' => ['guard' => 'web', 'passwords' => 'users'],
    'guards' => [
        'web' => ['driver' => 'session', 'provider' => 'chrono_users'],
        'chrono_api' => ['driver' => 'chrono-legacy-jwt'],
    ],
    'providers' => ['chrono_users' => ['driver' => 'eloquent', 'model' => App\Auth\LegacyUser::class]],
    'passwords' => [],
    'password_timeout' => 10800,
];
