<?php

use App\Providers\AppServiceProvider;
use App\Providers\LegacyFixtureServiceProvider;
use App\Providers\LegacyAuthServiceProvider;
use App\Providers\ChronoDomainServiceProvider;
use App\Providers\LegacyApiServiceProvider;
use App\Providers\LegacyRuntimeDatabaseServiceProvider;
use App\Providers\FreshWebServiceProvider;
use App\Providers\SharedLoginBudgetServiceProvider;
use App\Providers\AdmittedLegacyApiServiceProvider;
use App\Providers\ApiManagementServiceProvider;
use App\Providers\AdmittedTerminalServiceProvider;
use App\Providers\McpServiceProvider;

return [
    AppServiceProvider::class,
    LegacyFixtureServiceProvider::class,
    LegacyAuthServiceProvider::class,
    ChronoDomainServiceProvider::class,
    LegacyApiServiceProvider::class,
    FreshWebServiceProvider::class,
    LegacyRuntimeDatabaseServiceProvider::class,
    SharedLoginBudgetServiceProvider::class,
    ApiManagementServiceProvider::class,
    AdmittedLegacyApiServiceProvider::class,
    AdmittedTerminalServiceProvider::class,
    McpServiceProvider::class,
];
