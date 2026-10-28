<?php

namespace App\Chrono;

use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\RuntimeAdmission;
use RuntimeException;
use SQLite3;
use Throwable;

/** Request-owned secret-bearing file; never exposes a path or arbitrary stream callback. */
final class DatabaseSnapshotArtifact
{
    private const SCHEMA = "SELECT type,name,sql FROM sqlite_schema WHERE name NOT LIKE 'sqlite_%' ORDER BY type,name";

    private readonly string $path;
    private ?array $fileIdentity = null;
    private int $length = 0;
    private bool $ready = false;
    private bool $discarded = false;
    private bool $cleanupLogged = false;

    private function __construct(
        private readonly string $directory,
        private readonly array $directoryIdentity,
        private readonly OfflineFiles $files,
    ) {
        $this->path = $directory.'/snapshot.sqlite';
    }

    /** @internal Only the fixed freshly authorized RuntimeDatabase operation calls this. */
    public static function capture(SQLite3 $source, RuntimeAdmission $admission, OfflineFiles $files): self
    {
        $artifact = null;
        $destination = $check = null;
        $file = null;
        try {
            $path = @$files->validate($admission->path);
            $stat = @lstat($path);
            if ($stat === false || $stat['dev'] !== $admission->device || $stat['ino'] !== $admission->inode
                || $source->querySingle('PRAGMA query_only') !== 1) {
                throw new RuntimeException;
            }
            // validate() already requires this existing parent to be private and
            // outside both webroots. Never chmod an existing source/directory.
            $directory = dirname($path).'/snapshot-export-'.bin2hex(random_bytes(16));
            if (! @mkdir($directory, 0700)) {
                throw new RuntimeException;
            }
            $directoryStat = @lstat($directory);
            if ($directoryStat === false) {
                @rmdir($directory);
                throw new RuntimeException;
            }
            $artifact = new self($directory, ['dev' => $directoryStat['dev'], 'ino' => $directoryStat['ino']], $files);
            register_shutdown_function(static function () use ($artifact): void {
                $artifact->discard();
            });
            if (($directoryStat['mode'] & 0170000) !== 0040000 || ($directoryStat['mode'] & 0777) !== 0700) {
                throw new RuntimeException;
            }
            $file = @fopen($artifact->path, 'x+b');
            if ($file === false) {
                throw new RuntimeException;
            }
            $stat = fstat($file);
            if ($stat === false) {
                throw new RuntimeException;
            }
            $artifact->fileIdentity = ['dev' => $stat['dev'], 'ino' => $stat['ino']];
            // This is only the just-created request file in our new private dir.
            if (! @chmod($artifact->path, 0600) || ! fclose($file)) {
                throw new RuntimeException;
            }
            $file = null;
            $artifact->requireOwnedFile();
            if ($stat['dev'] === $admission->device && $stat['ino'] === $admission->inode) {
                throw new RuntimeException;
            }

            $destination = new SQLite3($artifact->path, SQLITE3_OPEN_READWRITE); // no CREATE or source writes
            $destination->enableExceptions(true);
            if (! $destination->busyTimeout(5000) || ! $source->backup($destination, 'main', 'main')
                || ! $destination->close()) {
                throw new RuntimeException;
            }
            $destination = null;

            $check = new SQLite3($artifact->path, SQLITE3_OPEN_READONLY);
            $check->enableExceptions(true);
            if (! $check->busyTimeout(5000) || ! $check->exec('PRAGMA query_only=ON')) {
                throw new RuntimeException;
            }
            $databases = self::rows($check, 'PRAGMA database_list');
            if (count($databases) !== 1 || $databases[0]['name'] !== 'main' || $databases[0]['file'] !== $artifact->path
                || $check->querySingle('PRAGMA query_only') !== 1
                || $check->querySingle('PRAGMA quick_check') !== 'ok'
                // Online backup may assign the destination a new schema cookie;
                // require exact schema SQL, not source cookie equality.
                || self::rows($check, self::SCHEMA) !== self::rows($source, self::SCHEMA)
                || self::rows($check, 'SELECT id,migration,batch FROM framework_migrations ORDER BY id') !== $admission->expectedLedgerRows() || ! $check->close()) {
                throw new RuntimeException;
            }
            $check = null;
            $stat = $artifact->requireOwnedFile();
            if ($stat['size'] < 1) {
                throw new RuntimeException;
            }
            $artifact->length = $stat['size'];
            $artifact->ready = true;

            return $artifact;
        } catch (Throwable) {
            throw new RuntimeException('Database snapshot could not be created.');
        } finally {
            if (is_resource($file)) {
                @fclose($file);
            }
            foreach ([$destination, $check] as $handle) {
                if ($handle instanceof SQLite3) {
                    try {
                        if (! $handle->close()) {
                            error_log('Chrono snapshot destination cleanup failed.');
                        }
                    } catch (Throwable) {
                        error_log('Chrono snapshot destination cleanup failed.');
                    }
                }
            }
            if ($artifact !== null && ! $artifact->ready) {
                $artifact->discard();
            }
        }
    }

    public function headers(): array
    {
        $stat = $this->requireOwnedFile();
        if (! $this->ready || $this->discarded || $stat['size'] !== $this->length) {
            throw new RuntimeException('Database snapshot is unavailable.');
        }

        return [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="timetracking.sqlite"',
            'Content-Length' => (string) $this->length,
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    /** Fixed StreamedResponse target. After headers, never append an error document. */
    public function stream(): void
    {
        $handle = null;
        try {
            $this->headers();
            $handle = @fopen($this->path, 'rb');
            if ($handle === false || ! $this->matchesFile(fstat($handle))) {
                throw new RuntimeException;
            }
            $sent = 0;
            while (! feof($handle)) {
                $chunk = @fread($handle, 1048576);
                if ($chunk === false || ($chunk === '' && ! feof($handle))) {
                    throw new RuntimeException;
                }
                $sent += strlen($chunk);
                if ($sent > $this->length) {
                    throw new RuntimeException;
                }
                echo $chunk;
                if (connection_aborted()) {
                    throw new RuntimeException;
                }
            }
            if ($sent !== $this->length || ! $this->matchesFile(fstat($handle))) {
                throw new RuntimeException;
            }
        } catch (Throwable) {
            // Client sees a truncated known-length response, never SQL/path text
            // or an appended HTML error. Status cannot be repaired after headers.
            error_log('Chrono snapshot stream failed.');
        } finally {
            if (is_resource($handle)) {
                if (! @fclose($handle)) {
                    error_log('Chrono snapshot stream cleanup failed.');
                }
            }
            $this->discard();
        }
    }

    /** Idempotent exact-file cleanup, never a glob or recursive directory delete. */
    public function discard(): bool
    {
        $this->ready = false;
        if ($this->discarded) {
            return true;
        }
        try {
            clearstatcache(true, $this->directory);
            $directory = @lstat($this->directory);
            if ($directory === false) {
                $this->discarded = true;
                return true;
            }
            if (($directory['mode'] & 0170000) !== 0040000 || realpath($this->directory) !== $this->directory
                || $directory['dev'] !== $this->directoryIdentity['dev'] || $directory['ino'] !== $this->directoryIdentity['ino']) {
                throw new RuntimeException;
            }
            clearstatcache(true, $this->path);
            $file = @lstat($this->path);
            if ($file !== false) {
                // Even a failed chmod of our just-created empty file must be
                // removable. Delete only its exact inode, never a replacement.
                if (! $this->matchesIdentity($file) || realpath($this->path) !== $this->path) {
                    throw new RuntimeException;
                }
                if (! @unlink($this->path)) {
                    throw new RuntimeException;
                }
            }
            if (! @rmdir($this->directory)) {
                throw new RuntimeException;
            }
            $this->discarded = true;
            return true;
        } catch (Throwable) {
            if (! $this->cleanupLogged) {
                error_log('Chrono snapshot private artifact cleanup failed.');
                $this->cleanupLogged = true;
            }
            return false;
        }
    }

    public function __destruct()
    {
        $this->discard();
    }

    public function __debugInfo(): array
    {
        return ['ready' => $this->ready, 'length' => $this->length];
    }

    private function requireOwnedFile(): array
    {
        @$this->files->validate($this->path);
        clearstatcache(true, $this->directory);
        $directory = @lstat($this->directory);
        clearstatcache(true, $this->path);
        $stat = @lstat($this->path);
        if ($directory === false || $directory['dev'] !== $this->directoryIdentity['dev']
            || $directory['ino'] !== $this->directoryIdentity['ino'] || ! $this->matchesFile($stat)) {
            throw new RuntimeException('Database snapshot is unavailable.');
        }

        return $stat;
    }

    private function matchesFile(array|false $stat): bool
    {
        return $this->matchesIdentity($stat) && ($stat['mode'] & 0777) === 0600;
    }

    private function matchesIdentity(array|false $stat): bool
    {
        return $stat !== false && $this->fileIdentity !== null
            && ($stat['mode'] & 0170000) === 0100000 && $stat['nlink'] === 1
            && $stat['dev'] === $this->fileIdentity['dev'] && $stat['ino'] === $this->fileIdentity['ino'];
    }

    private static function rows(SQLite3 $source, string $sql): array
    {
        $result = $source->query($sql);
        $rows = [];
        try {
            while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
                $rows[] = $row;
            }
        } finally {
            $result->finalize();
        }

        return $rows;
    }
}
