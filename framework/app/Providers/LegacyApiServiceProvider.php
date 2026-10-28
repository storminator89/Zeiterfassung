<?php

namespace App\Providers;

use App\Api\LegacyApiIdentity;
use App\Api\AccountLifecycleApiIdentity;
use App\Chrono\ApiAttendance;
use App\Chrono\LegacyApiAttendance;
use App\LegacyDatabase\AttendanceWriter;
use LogicException;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

final class LegacyApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Pure immutable selection, not a V5 opener or API activation switch.
        $this->app->scoped(AccountLifecycleApiIdentity::class, fn ($app) =>
            AccountLifecycleApiIdentity::fromApplication($app));
        $this->app->scoped(ApiAttendance::class, fn ($app) => new LegacyApiAttendance(
            $app->make('auth'),
            function () use ($app): AttendanceWriter {
                if (! $app->bound(AttendanceWriter::class)) {
                    throw new LogicException('No admitted operation-scoped attendance writer is configured.');
                }
                return $app->make(AttendanceWriter::class);
            }, $app->make(AccountLifecycleApiIdentity::class),
        ));
        // No signing-key, login-budget, connection or writer binding is shipped.
    }

    public function boot(): void
    {
        // Driver registration is lazy: no key, DB, session or route is opened.
        $this->app->make('auth')->viaRequest('chrono-legacy-jwt', fn (Request $request) =>
            $this->app->make(LegacyApiIdentity::class)->authenticate($request)
        );
    }
}
