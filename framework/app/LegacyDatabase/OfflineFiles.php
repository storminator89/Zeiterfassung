<?php

namespace App\LegacyDatabase;

use Illuminate\Database\Connectors\SQLiteConnector;
use PDO;
use Pdo\Sqlite;
use RuntimeException;
use Throwable;

/** File admission only; the caller owns the offline prerequisite and schema checks. */
final class OfflineFiles
{
    public function __construct(private readonly array $forbiddenWebRoots) {}

    public function validate(string $path): string
    {
        if ($path === '' || $path[0] !== '/' || str_contains($path, "\0")) {
            throw new RuntimeException('An explicit absolute existing private file is required.');
        }

        $parts = explode('/', substr($path, 1));
        if (array_intersect($parts, ['', '.', '..']) !== []) {
            throw new RuntimeException('Use a canonical absolute file path without aliases.');
        }
        $current = '';
        foreach ($parts as $part) {
            $current .= '/'.$part;
            clearstatcache(true, $current);
            $stat = @lstat($current);
            if ($stat === false || ($stat['mode'] & 0170000) === 0120000) {
                throw new RuntimeException('Missing files and symbolic path components are not supported.');
            }
        }

        $canonical = realpath($path);
        if ($canonical === false || $canonical !== $path) {
            throw new RuntimeException('The file path is not canonical.');
        }
        foreach ($this->forbiddenWebRoots as $root) {
            $root = realpath($root);
            if ($root === false) {
                throw new RuntimeException('The forbidden webroot boundary is not available.');
            }
            if ($canonical === $root || str_starts_with($canonical, rtrim($root, '/').'/')) {
                throw new RuntimeException('Database and backup files must remain outside application webroots.');
            }
        }

        $stat = lstat($canonical);
        $parent = lstat(dirname($canonical));
        if (($stat['mode'] & 0170000) !== 0100000 || $stat['nlink'] !== 1
            || ! in_array($stat['mode'] & 0777, [0400, 0600], true)) {
            throw new RuntimeException('A private regular single-link readable file is required.');
        }
        if ($parent === false || ($parent['mode'] & 0170000) !== 0040000
            || ($parent['mode'] & 0077) !== 0 || ($parent['mode'] & 0700) !== 0700) {
            throw new RuntimeException('The immediate file directory must be private to its owner.');
        }

        return $canonical;
    }

    /** @return array{dev:int,ino:int,size:int,mtime:int,sha256:string} */
    public function snapshot(string $path): array
    {
        $path = $this->validate($path);
        $this->requireColdRollbackFile($path);
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The admitted file could not be read.');
        }

        try {
            $before = fstat($handle);
            $digest = hash_init('sha256');
            if ($before === false || hash_update_stream($digest, $handle) !== $before['size']) {
                throw new RuntimeException('The file snapshot could not be completed.');
            }
            $hash = hash_final($digest);
            $after = fstat($handle);
            clearstatcache(true, $path);
            $pathStat = @lstat($path);
            if ($after === false || $pathStat === false
                || $this->identity($before) !== $this->identity($after)
                || $this->identity($before) !== $this->identity($pathStat)) {
                throw new RuntimeException('The file changed during read-only inspection.');
            }
            $this->validate($path);
            $this->requireColdRollbackFile($path);

            return $this->identity($before) + ['sha256' => $hash];
        } finally {
            fclose($handle);
        }
    }

    public function openReadOnly(string $path): PDO
    {
        $path = $this->validate($path);
        $before = $this->snapshot($path);

        try {
            $pdo = (new SQLiteConnector)->connect($this->configuration($path, Sqlite::OPEN_READONLY));
            $pdo->exec('PRAGMA query_only=ON');
            $this->requireMainFile($pdo, $path);
            if ($this->snapshot($path) !== $before) {
                throw new RuntimeException('The file changed while opening it read-only.');
            }

            return $pdo;
        } catch (Throwable) {
            // Never expose a native DSN/file path or exception text to logs/output.
            throw new RuntimeException('The private database could not be opened safely read-only.');
        }
    }

    /** Return stock connector configuration only after revalidating the inspected file. */
    public function readWriteConfiguration(string $path, array $expectedSnapshot): array
    {
        $path = $this->validate($path);
        if ((fileperms($path) & 0200) === 0) {
            throw new RuntimeException('The target must explicitly permit owner writes; permissions are never changed automatically.');
        }
        if ($this->snapshot($path) !== $expectedSnapshot) {
            throw new RuntimeException('The inspected file changed before write admission.');
        }

        return $this->configuration($path, Sqlite::OPEN_READWRITE) + ['transaction_mode' => 'IMMEDIATE'];
    }

    /** @return array{target:array,backup:array} */
    public function verifyBackup(string $target, string $backup, bool $requireIdenticalBytes = true): array
    {
        $target = $this->validate($target);
        $backup = $this->validate($backup);
        $targetState = $this->snapshot($target);
        $backupState = $this->snapshot($backup);
        if ($target === $backup
            || ($targetState['dev'] === $backupState['dev'] && $targetState['ino'] === $backupState['ino'])) {
            throw new RuntimeException('Backup and target must be distinct independent files.');
        }
        if ($requireIdenticalBytes
            && ($targetState['size'] !== $backupState['size'] || ! hash_equals($targetState['sha256'], $backupState['sha256']))) {
            throw new RuntimeException('A byte-identical stopped database backup is required before adoption.');
        }

        return ['target' => $targetState, 'backup' => $backupState];
    }

    private function requireColdRollbackFile(string $path): void
    {
        foreach (['-wal', '-shm', '-journal'] as $suffix) {
            clearstatcache(true, $path.$suffix);
            if (@lstat($path.$suffix) !== false) {
                throw new RuntimeException('SQLite sidecars require an operator-prepared consistent offline backup.');
            }
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The SQLite header could not be read.');
        }
        try {
            $header = fread($handle, 100);
        } finally {
            fclose($handle);
        }
        // Reject persistent WAL before connecting: even a read-only WAL connection
        // can require shared-memory sidecars. Never checkpoint or rewrite headers.
        if ($header === false || strlen($header) < 100
            || substr($header, 0, 16) !== "SQLite format 3\0"
            || ord($header[18]) !== 1 || ord($header[19]) !== 1) {
            throw new RuntimeException('Only a cold rollback-journal SQLite file is supported in this increment.');
        }
    }

    private function configuration(string $path, int $flags): array
    {
        return [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'busy_timeout' => 5000,
            'options' => [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                Sqlite::ATTR_OPEN_FLAGS => $flags,
            ],
        ];
    }

    private function requireMainFile(PDO $pdo, string $path): void
    {
        foreach ($pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC) as $database) {
            if ($database['name'] !== 'main' || $database['file'] !== $path) {
                throw new RuntimeException('An unexpected database was opened.');
            }
        }
    }

    private function identity(array $stat): array
    {
        return array_intersect_key($stat, array_flip(['dev', 'ino', 'size', 'mtime']));
    }
}
