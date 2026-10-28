<?php

// Trusted explicit selection only. No paths, secrets, counters or routes here.
// Does not activate API routes or transition an existing instance's counters.
return [
    'enabled' => env('FRAMEWORK_SHARED_LOGIN_BUDGET_ENABLED', false),
];
