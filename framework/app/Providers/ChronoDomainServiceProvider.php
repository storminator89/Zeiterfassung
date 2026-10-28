<?php

namespace App\Providers;

use App\Chrono\ChronoReads;
use App\Chrono\LegacyChronoReads;
use App\Chrono\LegacyLocalWebTerminalSites;
use App\Chrono\LocalWebTerminalSites;
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
