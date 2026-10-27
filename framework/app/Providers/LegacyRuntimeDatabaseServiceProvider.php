<?php

namespace App\Providers;

use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\AuditContext;
use App\LegacyDatabase\AuditFunctions;
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\RuntimeAdmission;
use App\LegacyDatabase\RuntimeDatabase;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use LogicException;

final class LegacyRuntimeDatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->app->make('config');
        $read = $config->get('chrono_runtime.read_enabled', false);
        $write = $config->get('chrono_runtime.write_enabled', false);
        if (! is_bool($read) || ! is_bool($write) || ($write && ! $read)) {
            throw new LogicException('Runtime reads and writes require separate explicit boolean opt-ins.');
        }
        if (! $read) {
            return;
        }
        $manifest = $config->get('chrono_runtime.admission');
        if (! is_array($manifest)) {
            throw new LogicException('The trusted runtime database admission manifest is required.');
        }

        // Pure validation only. No path stat, file read or PDO while registering.
        $admission = RuntimeAdmission::fromConfiguration($manifest);
        $configuration = $admission->configuration();
        // Laravel config:cache persists provider-generated configuration. A fresh
        // cached boot may reuse only this exact manifest-derived value, not an
        // unrelated chrono configuration or an already-resolved connection.
        if (($config->has('database.connections.chrono') && $config->get('database.connections.chrono') !== $configuration)
            || ($write && $this->app->bound(AttendanceWriter::class))) {
            throw new LogicException('Runtime admission must not replace another chrono connection or writer.');
        }
        $config->set('database.connections.chrono', $configuration);
        $this->app->singleton(RuntimeDatabase::class, fn () => new RuntimeDatabase(
            $admission, new OfflineFiles([dirname($this->app->basePath()), $this->app->publicPath()]),
        ));
        $this->callAfterResolving('db', function (DatabaseManager $database): void {
            if (isset($database->getConnections()['chrono'])) {
                throw new LogicException('Runtime admission must not reuse an already resolved chrono connection.');
            }
            // A supported named resolver, not a replacement global SQLite driver.
            $database->extend('chrono', fn (array $configuration, string $name) =>
                $this->app->make(RuntimeDatabase::class)->readConnection($configuration, $name)
            );
        });

        if ($write) {
            // Transient, deliberately not singleton/scoped: one dedicated writer
            // per authenticated operation resolver, with current scoped audit.
            $this->app->bind(AttendanceWriter::class, fn ($app) => $app->make(RuntimeDatabase::class)->writer(
                $app->make(AuditContext::class), $app->make(AuditFunctions::class),
            ));
        }
    }
}
