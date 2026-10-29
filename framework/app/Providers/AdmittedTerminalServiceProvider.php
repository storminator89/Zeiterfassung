<?php

namespace App\Providers;

use App\Terminal\AdmittedTerminalConfiguration;
use App\Terminal\AdmittedTerminalKeys;
use Illuminate\Support\ServiceProvider;

/** Default-off, lazy read-only binding of already admitted existing root files. */
final class AdmittedTerminalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $profile = AdmittedTerminalConfiguration::fromApplication($this->app);
        if ($profile === null) { return; }
        $this->app->instance(AdmittedTerminalConfiguration::class, $profile);
        $this->app->instance(AdmittedTerminalKeys::class, $profile->keys());
    }

    public function boot(): void
    {
        if (! $this->app->bound(AdmittedTerminalConfiguration::class)) { return; }
        $this->app->booted(fn () => $this->app->make(AdmittedTerminalConfiguration::class)->assertCurrent($this->app));
    }
}
