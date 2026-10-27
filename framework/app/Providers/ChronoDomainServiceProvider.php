<?php

namespace App\Providers;

use App\Chrono\ChronoReads;
use App\Chrono\LegacyChronoReads;
use Illuminate\Support\ServiceProvider;

final class ChronoDomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Lazy/scoped: registration and resolution open no DB or auth session.
        $this->app->scoped(ChronoReads::class, LegacyChronoReads::class);
    }
}
