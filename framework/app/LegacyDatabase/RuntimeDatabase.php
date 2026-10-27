<?php

namespace App\LegacyDatabase;

use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Database\SQLiteConnection;
use LogicException;
use PDO;
use Pdo\Sqlite;
use RuntimeException;
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
