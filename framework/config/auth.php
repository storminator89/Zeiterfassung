<?php

return [
    // No stock Laravel User model, user table, password broker or login route.
    // Auth (#76) must implement the reviewed legacy authentication contracts first.
    'defaults' => ['guard' => 'web', 'passwords' => 'users'],
    'guards' => [],
    'providers' => [],
    'passwords' => [],
    'password_timeout' => 10800,
];
