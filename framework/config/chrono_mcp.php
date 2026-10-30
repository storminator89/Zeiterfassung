<?php

// Private local process configuration only. Never place the token in Laravel config.
return [
    'enabled' => env('CHRONO_MCP_ENABLED', false),
    'apps_enabled' => env('CHRONO_MCP_APPS_ENABLED', false),
    'origin' => env('CHRONO_MCP_API_ORIGIN'),
    'deployment' => env('CHRONO_MCP_DEPLOYMENT'),
    'account_id' => env('CHRONO_MCP_ACCOUNT_ID'),
    'token_file' => env('CHRONO_MCP_TOKEN_FILE'),
];
