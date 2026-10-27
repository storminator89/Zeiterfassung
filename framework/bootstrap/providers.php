<?php

use App\Providers\AppServiceProvider;
use App\Providers\LegacyFixtureServiceProvider;
use App\Providers\LegacyAuthServiceProvider;

return [
    AppServiceProvider::class,
    LegacyFixtureServiceProvider::class,
    LegacyAuthServiceProvider::class,
];
