<?php

use App\Providers\AppServiceProvider;
use App\Providers\LegacyFixtureServiceProvider;
use App\Providers\LegacyAuthServiceProvider;
use App\Providers\ChronoDomainServiceProvider;

return [
    AppServiceProvider::class,
    LegacyFixtureServiceProvider::class,
    LegacyAuthServiceProvider::class,
    ChronoDomainServiceProvider::class,
];
