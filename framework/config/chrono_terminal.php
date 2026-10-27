<?php

// Trusted deployment opt-in only. No root source, writer, route or host admission
// is inferred from request headers/server fields or supplied by this config.
return [
    'http_enabled' => env('FRAMEWORK_CHRONO_TERMINAL_HTTP_ENABLED', false),
];
