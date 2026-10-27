<?php

use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [
    'default' => 'stderr',
    'deprecations' => ['channel' => 'stderr', 'trace' => false],
    'channels' => [
        'stderr' => [
            'driver' => 'monolog',
            'level' => 'warning',
            'handler' => StreamHandler::class,
            'handler_with' => ['stream' => 'php://stderr'],
            'processors' => [PsrLogMessageProcessor::class],
        ],
    ],
];
