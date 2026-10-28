<?php

// Trusted operator inputs ONLY. No files, stores, keys, PDO or routes are opened here.
// Explicit separate Runtime read/write opt-ins and reviewed prerequisites are required.
return [
    'enabled' => env('FRAMEWORK_FRESH_WEB_ENABLED', false),
    'instance_directory' => env('FRAMEWORK_FRESH_INSTANCE_DIRECTORY'),
    'setup_enabled' => env('FRAMEWORK_FRESH_SETUP_ENABLED', false),
    'session_lock_seconds' => env('FRAMEWORK_FRESH_SESSION_LOCK_SECONDS'),
    'session_wait_seconds' => env('FRAMEWORK_FRESH_SESSION_WAIT_SECONDS'),
];
