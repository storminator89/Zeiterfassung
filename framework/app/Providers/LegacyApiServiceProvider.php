<?php

namespace App\Providers;

use App\Api\LegacyApiIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

final class LegacyApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Driver registration is lazy: no key, DB, session or route is opened.
        $this->app->make('auth')->viaRequest('chrono-legacy-jwt', fn (Request $request) =>
            $this->app->make(LegacyApiIdentity::class)->authenticate($request)
        );
    }
}
