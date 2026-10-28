<?php

namespace App\Providers;

use App\Auth\SelfRegistration;
use App\Auth\FirstAdminSetup;
use App\Accounts\IntakePolicyAccess;
use App\Accounts\IntakePolicyStore;
use App\Chrono\DatabaseSnapshotExport;
use App\Chrono\LegacyLocalWebAttendance;
use App\Chrono\LegacyLocalWebAbsences;
use App\Chrono\LegacyLocalWebCorrections;
use App\Chrono\LegacyLocalWebCarryIn;
use App\Chrono\LegacyLocalWebDepartments;
use App\Chrono\LegacyLocalWebAdminUsers;
use App\Chrono\LegacyLocalWebWorkModels;
use App\Chrono\LocalWebAttendance;
use App\Chrono\LocalWebAbsences;
use App\Chrono\LocalWebCorrections;
use App\Chrono\LocalWebCarryIn;
use App\Chrono\LocalWebDepartments;
use App\Chrono\LocalWebAdminUsers;
use App\Chrono\LocalWebAdminUserMutations;
use App\Chrono\LegacyLocalWebAdminUserMutations;
use App\Chrono\LocalWebLdapSync;
use App\Chrono\LegacyLocalWebLdapSync;
use App\Ldap\LdapSyncTransport;
use App\Ldap\AdmittedLdapSettingsCipher;
use App\Chrono\LocalWebWorkModels;
use App\Chrono\LocalWebPauseSettings;
use App\Chrono\LegacyLocalWebPauseSettings;
use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\RuntimeDatabase;
use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(FirstAdminSetup::class, fn ($app) => new FirstAdminSetup(
            $app->make('config'), $app->make('hash'),
            function () use ($app): AttendanceWriter {
                if (! $app->bound(AttendanceWriter::class)) {
                    throw new LogicException('No admitted operation-scoped first-admin writer is configured.');
                }
                return $app->make(AttendanceWriter::class);
            },
        ));
        // Construction is pure; resolve a fresh admitted writer only per action.
        $this->app->scoped(LocalWebAdminUserMutations::class, fn ($app) => new LegacyLocalWebAdminUserMutations(
            $app->make('auth'),
            function () use ($app): AttendanceWriter {
                if (! $app->bound(AttendanceWriter::class)) {
                    throw new LogicException('No admitted operation-scoped user-mutation writer is configured.');
                }
                return $app->make(AttendanceWriter::class);
            },
        ));
        // Pure/lazy service only. No default directory, root loader or connection.
        $this->app->scoped(LocalWebLdapSync::class, fn ($app) => new LegacyLocalWebLdapSync(
            $app->make('auth'), $app->make('db'), new IntakePolicyStore,
            function () use ($app): LdapSyncTransport {
                if (! $app->bound(LdapSyncTransport::class)) { throw new LogicException('No admitted LDAP sync transport.'); }
                return $app->make(LdapSyncTransport::class);
            },
            function () use ($app): AdmittedLdapSettingsCipher {
                if (! $app->bound(AdmittedLdapSettingsCipher::class)) { throw new LogicException('No admitted existing LDAP settings root.'); }
                return $app->make(AdmittedLdapSettingsCipher::class);
            },
            function () use ($app): AttendanceWriter {
                if (! $app->bound(AttendanceWriter::class)) { throw new LogicException('No admitted LDAP sync writer.'); }
                return $app->make(AttendanceWriter::class);
            },
        ));
        $this->app->scoped(IntakePolicyAccess::class, fn ($app) => new IntakePolicyAccess(
            $app->bound(RuntimeDatabase::class) && $app->make(RuntimeDatabase::class)->isIntakePolicyV4(),
            $app->make('auth'), $app->make('db'), new IntakePolicyStore,
            function () use ($app): AttendanceWriter {
                if (! $app->bound(AttendanceWriter::class)) {
                    throw new LogicException('No admitted operation-scoped intake policy writer is configured.');
                }
                return $app->make(AttendanceWriter::class);
            },
        ));
        $this->app->scoped(SelfRegistration::class, fn ($app) => new SelfRegistration(
            $app->make('config'), $app->make('hash'),
            function () use ($app): AttendanceWriter {
                if (! $app->bound(AttendanceWriter::class)) {
                    throw new LogicException('No admitted operation-scoped registration writer is configured.');
                }
                return $app->make(AttendanceWriter::class);
            }, $app->make(IntakePolicyAccess::class),
        ));
        $this->app->scoped(DatabaseSnapshotExport::class, function ($app): DatabaseSnapshotExport {
            // No autowired/default/environment source. Construction is pure;
            // only the fixed guarded operation may open its private source.
            if (! $app->bound(RuntimeDatabase::class)) {
                throw new LogicException('No explicitly admitted database snapshot source is configured.');
            }

            return new DatabaseSnapshotExport($app->make('auth'), $app->make(RuntimeDatabase::class));
        });
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
        $this->app->scoped(LocalWebAdminUsers::class, fn ($app) => new LegacyLocalWebAdminUsers(
            $app->make('auth'),
            function () use ($app): AttendanceWriter {
                if (! $app->bound(AttendanceWriter::class)) {
                    throw new LogicException('No admitted operation-scoped attendance writer is configured.');
                }

                return $app->make(AttendanceWriter::class);
            }, $app->make(IntakePolicyAccess::class),
        ));
        $this->app->scoped(LocalWebCarryIn::class, fn ($app) => new LegacyLocalWebCarryIn(
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
