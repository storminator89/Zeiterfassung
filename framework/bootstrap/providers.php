<?php

use App\Providers\AppServiceProvider;
use App\Providers\LegacyFixtureServiceProvider;
use App\Providers\LegacyAuthServiceProvider;
use App\Providers\ChronoDomainServiceProvider;
use App\Providers\LegacyApiServiceProvider;

return [
    AppServiceProvider::class,
    LegacyFixtureServiceProvider::class,
    LegacyAuthServiceProvider::class,
    ChronoDomainServiceProvider::class,
    LegacyApiServiceProvider::class,
];
