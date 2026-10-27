<?php

return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => env('FRAMEWORK_FILES_PATH', storage_path('app/private')),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],
    ],
    'links' => [],
];
