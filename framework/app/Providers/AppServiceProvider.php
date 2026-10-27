<?php

namespace App\Providers;

use App\Chrono\LegacyLocalWebAttendance;
use App\Chrono\LegacyLocalWebCorrections;
use App\Chrono\LocalWebAttendance;
use App\Chrono\LocalWebCorrections;
use App\LegacyDatabase\AttendanceWriter;
use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(LocalWebAttendance::class, fn ($app) => new LegacyLocalWebAttendance(
            $app->make('auth'),
            function () use ($app): AttendanceWriter {
                // No auto-resolution of PDO/configuration. Only a separately
                // admitted operation may explicitly supply this trusted binding.
                if (! $app->bound(AttendanceWriter::class)) {
                    throw new LogicException('No admitted operation-scoped attendance writer is configured.');
                }

                return $app->make(AttendanceWriter::class);
            },
        ));
        $this->app->scoped(LocalWebCorrections::class, fn ($app) => new LegacyLocalWebCorrections(
            $app->make('auth'),
            function () use ($app): AttendanceWriter {
                if (! $app->bound(AttendanceWriter::class)) {
                    throw new LogicException('No admitted operation-scoped attendance writer is configured.');
                }

                return $app->make(AttendanceWriter::class);
            },
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
