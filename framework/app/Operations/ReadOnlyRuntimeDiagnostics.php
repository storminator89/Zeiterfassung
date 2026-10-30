<?php

namespace App\Operations;

use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\RuntimeDatabase;
use LogicException;
use PDO;
use Throwable;

/** Fixed observations only: no writer, key, identity, repair or HTTP capability. */
final readonly class ReadOnlyRuntimeDiagnostics
{
    public function __construct(
        private RuntimeDatabase $runtime,
        private array $readConfiguration,
        private OfflineFiles $files,
    ) {}

    public function inspect(int $now, int $minimumFreeBytes, ?string $backup,
        int $maximumBackupAge, bool $terminal): array
    {
        if ($now < 1 || $minimumFreeBytes < 0 || $maximumBackupAge < 1) {
            throw new LogicException('Invalid diagnostic thresholds.');
        }

        // A fresh handle through the original exact named admission, never a
        // cached manager connection, inferred path or alternate SQLite driver.
        $connection = $this->runtime->readConnection($this->readConfiguration, 'chrono');
        try {
            $pdo = $connection->getPdo();
            if ($pdo->inTransaction() || (int) $pdo->query('PRAGMA query_only')->fetchColumn() !== 1) {
                throw new LogicException('Read-only diagnostics require their own admitted handle.');
            }
            $contact = $connection->transaction(function () use ($pdo, $now, $terminal): array {
                if ($pdo->query('SELECT 1')->fetchColumn() !== 1) {
                    throw new LogicException('Read-only runtime observation refused.');
                }
                if (! $terminal) {
                    return ['state' => 'not_selected'];
                }
                require_once dirname(__DIR__, 3).'/lib/terminal_status.php';
                // No IDs, names, locations, users, device/credential keys,
                // pairing codes, grants or card/PIN hashes are selected.
                $statement = $pdo->query('SELECT last_seen FROM terminals WHERE enabled=1 ORDER BY id LIMIT 1001');
                $rows = $statement->fetchAll(PDO::FETCH_COLUMN);
                $statement->closeCursor();
                return \terminal_contact_observation($rows, $now);
            }, 1);

            // Only the already admitted database's volume, not an arbitrary
            // operator/request path. No mount/permission/configuration change.
            $free = @disk_free_space(dirname($this->readConfiguration['database']));
            $free = is_float($free) && is_finite($free) && $free >= 0 && $free <= PHP_INT_MAX ? (int) $free : null;

            return [
                'runtime' => ['state' => 'readable', 'query_only' => true],
                'storage' => ['state' => $free === null ? 'unknown' : ($free < $minimumFreeBytes ? 'low' : 'sufficient'),
                    'available_bytes' => $free, 'minimum_bytes' => $minimumFreeBytes],
                'backup_timestamp' => $this->backupTimestamp($backup, $now, $maximumBackupAge),
                'terminal_contact' => $contact,
            ];
        } finally {
            // No raw handle escapes, no writer is resolved and no repair runs.
            unset($pdo);
            $connection->disconnect();
        }
    }

    private function backupTimestamp(?string $backup, int $now, int $maximumAge): array
    {
        $result = ['state' => 'not_supplied', 'age_seconds' => null,
            'maximum_age_seconds' => $maximumAge, 'restorability' => 'not_verified'];
        if ($backup === null) {
            return $result;
        }
        try {
            // Explicit existing private single-link file only. Stat metadata,
            // never contents, keys, backup inventories, hashes or a live copy.
            $path = $this->files->validate($backup);
            clearstatcache(true, $path);
            $before = @lstat($path);
            clearstatcache(true, $path);
            $after = @lstat($path);
            $database = @lstat($this->readConfiguration['database']);
            if ($before === false || $before !== $after || $before['size'] < 1
                || $before['mtime'] < 1 || $before['mtime'] > $now
                || $database === false || [$before['dev'], $before['ino']] === [$database['dev'], $database['ino']]) {
                throw new LogicException('Backup timestamp unavailable.');
            }
            $age = $now - $before['mtime'];

            return array_replace($result, ['state' => $age > $maximumAge ? 'stale' : 'recent', 'age_seconds' => $age]);
        } catch (Throwable) {
            return array_replace($result, ['state' => 'unavailable']);
        }
    }
}
