<?php

return [
    'default' => env('FRAMEWORK_CACHE_STORE', 'file'),
    'stores' => [
        'array' => ['driver' => 'array', 'serialize' => false],
        'file' => [
            'driver' => 'file',
            'path' => env('FRAMEWORK_CACHE_PATH', storage_path('framework/cache/data')),
            'lock_path' => env('FRAMEWORK_CACHE_LOCK_PATH', storage_path('framework/cache/locks')),
        ],
    ],
    'prefix' => env('FRAMEWORK_CACHE_PREFIX', 'chrono-framework-cache-'),
    'serializable_classes' => false,
];
