<?php

namespace App\Providers;

use App\Chrono\ChronoReads;
use App\Chrono\LocalWebCorrectionWithdrawals;
use App\Chrono\LegacyLocalWebCorrectionWithdrawals;
use App\Chrono\LegacyChronoReads;
use App\Chrono\LegacyLocalWebTerminalSites;
use App\Chrono\LocalWebTerminalSites;
use App\Chrono\LocalWebTerminalCredentialRevocations;
use App\Chrono\LegacyLocalWebTerminalCredentialRevocations;
use App\Chrono\LocalWebTerminalDeviceRevocations;
use App\Chrono\LegacyLocalWebTerminalDeviceRevocations;
use App\LegacyDatabase\AttendanceWriter;
use App\Terminal\AdmittedTerminalKeys;
use App\Terminal\LegacyTerminalProtocol;
use App\Terminal\TerminalProtocol;
use LogicException;
use Illuminate\Support\ServiceProvider;

final class ChronoDomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Lazy/scoped: registration and resolution open no DB or auth session.
        $this->app->scoped(ChronoReads::class, LegacyChronoReads::class);
        // Pure lazy construction; no terminal key, module setting or default writer.
        $this->app->scoped(LocalWebTerminalSites::class, fn ($app) => new LegacyLocalWebTerminalSites(
            $app->make('auth'),
            function () use ($app): AttendanceWriter {
                if (! $app->bound(AttendanceWriter::class)) {
                    throw new LogicException('No admitted operation-scoped terminal site writer is configured.');
                }

                return $app->make(AttendanceWriter::class);
            },
        ));
        // Construction never opens a writer, reads credentials or resolves keys.
        $this->app->scoped(LocalWebTerminalCredentialRevocations::class, fn ($app) => new LegacyLocalWebTerminalCredentialRevocations(
            $app->make('auth'),
            function () use ($app): AttendanceWriter {
                if (! $app->bound(AttendanceWriter::class)) {
                    throw new LogicException('No admitted operation-scoped terminal revocation writer is configured.');
                }

                return $app->make(AttendanceWriter::class);
            },
        ));
        // Pure lazy owner withdrawal service; no route or capability is selected.
        $this->app->scoped(LocalWebCorrectionWithdrawals::class, fn ($app) => new LegacyLocalWebCorrectionWithdrawals(
            $app->make('auth'),
            function () use ($app): AttendanceWriter {
                if (! $app->bound(AttendanceWriter::class)) {
                    throw new LogicException('No admitted operation-scoped correction withdrawal writer is configured.');
                }

                return $app->make(AttendanceWriter::class);
            },
        ));
        // A fixed Admin operation, independently selected; no writer/key is resolved here.
        $this->app->scoped(LocalWebTerminalDeviceRevocations::class, fn ($app) => new LegacyLocalWebTerminalDeviceRevocations(
            $app->make('auth'),
            function () use ($app): AttendanceWriter {
                if (! $app->bound(AttendanceWriter::class)) {
                    throw new LogicException('No admitted operation-scoped terminal device revocation writer is configured.');
                }

                return $app->make(AttendanceWriter::class);
            },
        ));
        // Real backend, but no route, root-key binding or fallback is installed.
        // Check explicit key admission before resolving any writer/connection.
        $this->app->scoped(TerminalProtocol::class, function ($app): TerminalProtocol {
            if (! $app->bound(AdmittedTerminalKeys::class) || ! $app->bound(AttendanceWriter::class)) {
                throw new LogicException('Explicit terminal key sources and an admitted operation writer are required.');
            }
            $keys = $app->make(AdmittedTerminalKeys::class);
            if (! $keys instanceof AdmittedTerminalKeys) {
                throw new LogicException('Explicit existing terminal key sources are required.');
            }
            $writer = $app->make(AttendanceWriter::class);
            if (! $writer instanceof AttendanceWriter) {
                throw new LogicException('An admitted operation-scoped terminal writer is required.');
            }

            return new LegacyTerminalProtocol($writer, $keys);
        });
    }
    public function boot(): void
    {
        // No route by default, no key/writer resolution or source binding here.
        // Runtime gating in the adapter also closes an older compiled route.
        if ($this->app->make('config')->get('chrono_terminal.http_enabled', false) === true) {
            $this->loadRoutesFrom($this->app->basePath('routes/terminal.php'));
        }
    }

}
