<?php

namespace App\Console\Commands;

use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\RuntimeDatabase;
use App\Operations\ReadOnlyRuntimeDiagnostics;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

/** Private operator CLI; not an HTTP readiness/health endpoint or repair tool. */
final class RuntimeDiagnostics extends Command
{
    protected $signature = 'chrono:diagnose
        {--backup= : Existing private backup file; timestamp only, never a restore assertion}
        {--max-backup-age=86400 : Maximum observed backup age in seconds}
        {--min-free-bytes=134217728 : Minimum free bytes on the admitted database volume}
        {--terminal : Observe enabled device contact freshness, without accessing credentials}';

    protected $description = 'Report bounded read-only runtime observations as redacted JSON; never activate or repair an installation.';

    public function handle(): int
    {
        $report = ['schema_version' => 1, 'diagnostic_id' => bin2hex(random_bytes(16)),
            'checked_at' => gmdate('c'), 'scope' => 'read_only_runtime_observations',
            'readiness' => 'not_assessed',
            'unassessed' => ['login', 'writer', 'API', 'LDAP', 'hardware', 'TLS', 'log_capture', 'restore_integrity'],
            'runbook' => 'docs/framework/operational-diagnostics.md'];
        $exit = self::FAILURE;
        // Local to this synchronous CLI operation; a warning must not expose
        // a private path before the generic JSON catch. No PHP/host setting is
        // changed, no warning details retained, and the previous handler returns.
        set_error_handler(static function (): never {
            throw new \RuntimeException('Diagnostic observation refused.');
        });
        try {
            $maximumAge = $this->integerOption('max-backup-age', 1);
            $minimumFree = $this->integerOption('min-free-bytes', 0);
            $backup = $this->option('backup');
            if ($backup !== null && (! is_string($backup) || $backup === '')) {
                throw new InvalidArgumentException;
            }
            $config = $this->laravel->make('config');
            $read = $config->get('chrono_runtime.read_enabled', false);
            if (! is_bool($read)) {
                throw new InvalidArgumentException;
            }
            if (! $read) {
                // Closed defaults stay closed: do not resolve RuntimeDatabase,
                // database manager, files, paths, writers or setup/key services.
                $report += ['state' => 'disabled', 'codes' => ['runtime_reads_disabled'], 'checks' => []];
                $exit = 3;
            } else {
                $checks = (new ReadOnlyRuntimeDiagnostics(
                    $this->laravel->make(RuntimeDatabase::class),
                    $config->get('database.connections.chrono'),
                    new OfflineFiles([dirname($this->laravel->basePath()), $this->laravel->publicPath()]),
                ))->inspect(time(), $minimumFree, $backup, $maximumAge, $this->option('terminal') === true);
                $codes = [];
                if ($checks['storage']['state'] !== 'sufficient') { $codes[] = 'storage_'.$checks['storage']['state']; }
                if ($checks['backup_timestamp']['state'] !== 'recent') { $codes[] = 'backup_'.$checks['backup_timestamp']['state']; }
                if (! in_array($checks['terminal_contact']['state'], ['recent', 'not_selected'], true)) {
                    $codes[] = 'terminal_'.$checks['terminal_contact']['state'];
                }
                $report += ['state' => $codes === [] ? 'healthy' : 'degraded', 'codes' => $codes, 'checks' => $checks];
                $exit = $codes === [] ? self::SUCCESS : self::FAILURE;
            }
        } catch (InvalidArgumentException) {
            $report += ['state' => 'invalid', 'codes' => ['invalid_options_or_read_flag'], 'checks' => []];
            $exit = self::INVALID;
        } catch (Throwable) {
            // No exception message/type/previous/trace, DSN, file path, SQL,
            // request data or native status is logged or copied to the report.
            $report += ['state' => 'degraded', 'codes' => ['runtime_observation_refused'], 'checks' => []];
        } finally {
            restore_error_handler();
        }
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $exit;
    }

    private function integerOption(string $name, int $minimum): int
    {
        $raw = $this->option($name);
        if (! is_string($raw) || preg_match('/^(0|[1-9][0-9]*)$/D', $raw) !== 1
            || ($value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum]])) === false) {
            throw new InvalidArgumentException;
        }

        return $value;
    }
}
