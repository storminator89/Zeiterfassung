<?php

namespace App\Providers;

use App\Chrono\LegacyLocalWebAttendance;
use App\Chrono\LegacyLocalWebAbsences;
use App\Chrono\LegacyLocalWebCorrections;
use App\Chrono\LegacyLocalWebDepartments;
use App\Chrono\LegacyLocalWebWorkModels;
use App\Chrono\LocalWebAttendance;
use App\Chrono\LocalWebAbsences;
use App\Chrono\LocalWebCorrections;
use App\Chrono\LocalWebDepartments;
use App\Chrono\LocalWebWorkModels;
use App\Chrono\LocalWebPauseSettings;
use App\Chrono\LegacyLocalWebPauseSettings;
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
        $this->app->scoped(LocalWebWorkModels::class, fn ($app) => new LegacyLocalWebWorkModels(
            $app->make('auth'),
            function () use ($app): AttendanceWriter {
                if (! $app->bound(AttendanceWriter::class)) {
                    throw new LogicException('No admitted operation-scoped attendance writer is configured.');
                }

                return $app->make(AttendanceWriter::class);
            },
        ));
        $this->app->scoped(LocalWebPauseSettings::class, fn ($app) => new LegacyLocalWebPauseSettings(
            $app->make('auth'),
            function () use ($app): AttendanceWriter {
                if (! $app->bound(AttendanceWriter::class)) {
                    throw new LogicException('No admitted operation-scoped attendance writer is configured.');
                }

                return $app->make(AttendanceWriter::class);
            },
        ));
        $this->app->scoped(LocalWebDepartments::class, fn ($app) => new LegacyLocalWebDepartments(
            $app->make('auth'),
            function () use ($app): AttendanceWriter {
                if (! $app->bound(AttendanceWriter::class)) {
                    throw new LogicException('No admitted operation-scoped attendance writer is configured.');
                }

                return $app->make(AttendanceWriter::class);
            },
        ));
        $this->app->scoped(LocalWebAbsences::class, fn ($app) => new LegacyLocalWebAbsences(
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
