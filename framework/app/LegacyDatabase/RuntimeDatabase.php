<?php

namespace App\LegacyDatabase;

use App\Auth\LegacyUser;
use App\Chrono\DatabaseSnapshotArtifact;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Database\SQLiteConnection;
use LogicException;
use PDO;
use Pdo\Sqlite;
use RuntimeException;
use SQLite3;
use Throwable;

/** Opens only the already admitted existing identity; never creates/adopts it. */
final class RuntimeDatabase
{
    public function __construct(
        private readonly RuntimeAdmission $admission,
        private readonly OfflineFiles $files,
    ) {}

    public function readConnection(array $configuration, string $name): SQLiteConnection
    {
        if ($name !== 'chrono' || $configuration !== $this->admission->configuration()) {
            throw new LogicException('Only the explicitly admitted named chrono read configuration is supported.');
        }

        return new SQLiteConnection($this->open(false), $this->admission->path, '', $configuration);
    }

    // Every resolution owns a fresh bare handle, never a manager/read connection.
    public function writer(AuditContext $context, AuditFunctions $functions): AttendanceWriter
    {
        $pdo = $this->open(true);
        try {
            return new AttendanceWriter($pdo, $this->admission->configuration(true), $context, $functions);
        } catch (Throwable) {
            throw new RuntimeException('Runtime database writer admission refused.');
        }
    }

    /** Fixed secret-bearing operation; no source/path selector or raw-handle callback. */
    public function databaseSnapshot(LegacyUser $authenticated): DatabaseSnapshotArtifact
    {
        $identifier = (int) $authenticated->getKey();
        $password = $authenticated->getAuthPassword();
        if ($identifier < 1 || $authenticated->auth_source !== 'local' || ! is_string($password) || $password === '') {
            throw new AuthenticationException;
        }
        if ($authenticated->role !== 'admin') {
            throw new AuthorizationException('Only administrators may export a database snapshot.');
        }

        $source = null;
        $artifact = null;
        $transaction = false;
        try {
            $source = $this->openSnapshotSource();
            if (! $source->exec('BEGIN DEFERRED')) {
                throw new RuntimeException;
            }
            $transaction = true;
            $this->requireSnapshotSource($source);
            $statement = $source->prepare('SELECT id,role,auth_source,password FROM users WHERE id=:actor');
            try {
                $statement->bindValue(':actor', $identifier, SQLITE3_INTEGER);
                $result = $statement->execute();
                try {
                    $actor = $result->fetchArray(SQLITE3_ASSOC);
                    if ($result->fetchArray(SQLITE3_ASSOC) !== false) {
                        throw new RuntimeException;
                    }
                } finally {
                    $result->finalize();
                }
            } finally {
                $statement->close();
            }
            if ($actor === false || $actor['id'] !== $identifier || $actor['auth_source'] !== 'local'
                || ! is_string($actor['password']) || ! hash_equals($password, $actor['password'])) {
                throw new AuthenticationException;
            }
            if ($actor['role'] !== 'admin') {
                throw new AuthorizationException('Only administrators may export a database snapshot.');
            }
            unset($actor, $password);

            // The destination is allocated only AFTER this same-source fresh check.
            $artifact = DatabaseSnapshotArtifact::capture($source, $this->admission, $this->files);
            $this->requireSnapshotSource($source);
            @$this->identity(false);
            if (! $source->exec('ROLLBACK')) {
                throw new RuntimeException;
            }
            $transaction = false;
            @$this->identity(false);
            if (! $source->close()) {
                throw new RuntimeException;
            }
            $source = null;

            return $artifact;
        } catch (AuthenticationException|AuthorizationException $error) {
            $artifact?->discard();
            throw $error;
        } catch (Throwable) {
            $artifact?->discard();
            // No native message/previous exception, SQL, hashes or private paths.
            throw new RuntimeException('Database snapshot could not be created.');
        } finally {
            if ($source instanceof SQLite3) {
                try {
                    if ($transaction && ! $source->exec('ROLLBACK')) {
                        error_log('Chrono snapshot source cleanup failed.');
                    }
                } catch (Throwable) {
                    error_log('Chrono snapshot source cleanup failed.');
                }
                try {
                    if (! $source->close()) {
                        error_log('Chrono snapshot source cleanup failed.');
                    }
                } catch (Throwable) {
                    error_log('Chrono snapshot source cleanup failed.');
                }
            }
        }
    }

    /** Separate native driver admission; existing PDO reader/writer bodies stay untouched. */
    private function openSnapshotSource(): SQLite3
    {
        $source = null;
        try {
            @$this->identity(false);
            $source = new SQLite3($this->admission->path, SQLITE3_OPEN_READONLY);
            $source->enableExceptions(true);
            if (! $source->busyTimeout(5000) || ! $source->exec('PRAGMA query_only=ON')) {
                throw new RuntimeException;
            }
            $this->requireSnapshotSource($source);
            @$this->identity(false);

            return $source;
        } catch (Throwable) {
            if ($source instanceof SQLite3) {
                try {
                    if (! $source->close()) {
                        error_log('Chrono snapshot source cleanup failed.');
                    }
                } catch (Throwable) {
                    error_log('Chrono snapshot source cleanup failed.');
                }
            }
            throw new RuntimeException('Runtime snapshot source admission refused.');
        }
    }

    private function requireSnapshotSource(SQLite3 $source): void
    {
        $databases = $this->snapshotRows($source, 'PRAGMA database_list');
        if (count($databases) !== 1 || $databases[0]['name'] !== 'main' || $databases[0]['file'] !== $this->admission->path) {
            throw new RuntimeException;
        }
        foreach ([
            'schema_version' => $this->admission->schemaVersion,
            'foreign_keys' => $this->admission->foreignKeys,
            'journal_mode' => $this->admission->journalMode,
            'synchronous' => $this->admission->synchronous,
            'busy_timeout' => 5000,
            'query_only' => 1,
        ] as $pragma => $expected) {
            if ($source->querySingle('PRAGMA '.$pragma) !== $expected) {
                throw new RuntimeException;
            }
        }
        // Mechanical SQLite3 equivalent of V2Adoption::ledgerState(PDO).
        if ($this->snapshotRows($source, 'SELECT id,migration,batch FROM framework_migrations ORDER BY id') !== [[
            'id' => 1, 'migration' => V2Adoption::migrationName($this->admission->schemaSignature), 'batch' => 1,
        ]]) {
            throw new RuntimeException;
        }
    }

    private function snapshotRows(SQLite3 $source, string $sql): array
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

    private function open(bool $write): PDO
    {
        $phase = 'private file identity';
        try {
            $this->identity($write);
            $phase = 'existing-file connection';
            $pdo = (new SQLiteConnector)->connect($this->admission->configuration($write));
            if (! $pdo instanceof Sqlite || $pdo->inTransaction()
                || $pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
                throw new RuntimeException;
            }
            if (! $write) {
                $pdo->exec('PRAGMA query_only=ON');
            }

            // SQLite owns runtime rollback-journal sidecars and normal locking.
            // Do not invoke the cold/offline snapshot or no-sidecar policy here.
            $phase = 'opened identity and approved settings';
            $databases = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
            if (count($databases) !== 1 || $databases[0]['name'] !== 'main'
                || $databases[0]['file'] !== $this->admission->path) {
                throw new RuntimeException;
            }
            foreach ([
                'schema_version' => $this->admission->schemaVersion,
                'foreign_keys' => $this->admission->foreignKeys,
                'journal_mode' => $this->admission->journalMode,
                'synchronous' => $this->admission->synchronous,
                'busy_timeout' => 5000,
                'query_only' => $write ? 0 : 1,
            ] as $pragma => $expected) {
                if ($pdo->query('PRAGMA '.$pragma)->fetchColumn() !== $expected) {
                    throw new RuntimeException;
                }
            }

            $phase = 'owned adoption ledger';
            if (V2Adoption::ledgerState($pdo, $this->admission->schemaSignature) !== 'owned') {
                throw new RuntimeException;
            }
            $phase = 'final file identity';
            $this->identity($write);

            return $pdo;
        } catch (Throwable) {
            // Never retain native exception text/previous errors with DSNs or SQL.
            throw new RuntimeException('Runtime database admission refused during '.$phase.'.');
        }
    }

    private function identity(bool $write): void
    {
        $path = $this->files->validate($this->admission->path);
        $stat = lstat($path);
        if ($stat === false || $stat['dev'] !== $this->admission->device || $stat['ino'] !== $this->admission->inode
            || ($write && ($stat['mode'] & 0200) === 0)) {
            throw new RuntimeException;
        }

        // Reject persistent WAL before SQLite can require/create shared-memory
        // sidecars, but allow normal runtime rollback-journal sidecars/locks.
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException;
        }
        try {
            $opened = fstat($handle);
            $header = fread($handle, 20);
            if ($opened === false || $opened['dev'] !== $this->admission->device || $opened['ino'] !== $this->admission->inode
                || $header === false || strlen($header) !== 20 || substr($header, 0, 16) !== "SQLite format 3\0"
                || ord($header[18]) !== 1 || ord($header[19]) !== 1) {
                throw new RuntimeException;
            }
        } finally {
            fclose($handle);
        }
    }
}
