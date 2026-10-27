<?php

namespace App\LegacyDatabase;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\SQLiteConnection;
use PDO;
use RuntimeException;
use Throwable;

final class V2Adoption
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly V2Preflight $preflight,
        private readonly Repository $config,
    ) {}

    public function register(string $connectionName, PDO $reference): array
    {
        $configuration = $this->config->get('database.connections.'.$connectionName);
        if (! is_array($configuration) || ($configuration['driver'] ?? null) !== 'chrono_fixture') {
            throw new RuntimeException('Only explicitly configured memory fixture connections are supported.');
        }
        // Reject unsupported file/default drivers before resolving a PDO at all.
        MemoryOnly::configuration($configuration);
        $connection = $this->database->connection($connectionName);
        MemoryOnly::connection($connection->getPdo());

        return $this->registerConnection($connection, $reference);
    }

    // No path opens here. The offline caller supplies a preflighted, backup-verified
    // one-shot writer; the existing memory entry point keeps its admission guard.
    public function registerConnection(SQLiteConnection $connection, PDO $reference, ?Closure $sourceGuard = null): array
    {
        $pdo = $connection->getPdo();
        if ($connection->transactionLevel() !== 0 || $pdo->inTransaction()
            || $connection->getConfig('transaction_mode') !== 'IMMEDIATE') {
            throw new RuntimeException('Adoption must own its transaction boundary.');
        }

        // Preflight precedes even the creation of the Laravel tracking table.
        $initial = $this->preflight->inspect($pdo, $reference);
        self::ledgerState($pdo, $initial['schema_signature']);
        // One migration handle, no reconnect/retry while the ledger is being changed.
        // Laravel's transaction level also disables query retry inside the transaction.
        $connection->setReconnector(static fn () => throw new RuntimeException('Reconnect during offline adoption is forbidden.'));
        $connection->beginTransaction();
        try {
            $checked = $this->preflight->inspect($pdo, $reference);
            self::ledgerState($pdo, $checked['schema_signature']);
            $sourceGuard?->__invoke($pdo);
            $migration = self::migrationName($checked['schema_signature']);
            $repository = $this->repository($connection);
            if (! $repository->repositoryExists()) {
                $repository->createRepository();
            }
            $ran = $repository->getRan();
            if ($ran !== [] && ($ran !== [$migration] || $repository->getMigrationBatches() !== [$migration => 1])) {
                throw new RuntimeException('Unexpected adoption ledger requires review.');
            }
            if ($ran === []) {
                $repository->log($migration, 1);
            }
            self::ledgerState($pdo, $checked['schema_signature']);
            $sourceGuard?->__invoke($pdo);
            if ($connection->getPdo() !== $pdo) {
                throw new RuntimeException('Connection changed during adoption.');
            }
            $connection->commit();

            return $checked + ['registered' => $ran === [], 'migration' => $migration];
        } catch (Throwable $error) {
            $connection->rollBack();
            throw $error;
        }
    }

    public static function migrationName(string $signature): string
    {
        return '2026_10_27_000001_adopt_legacy_v2_'.$signature;
    }

    public static function ledgerState(PDO $pdo, string $signature): string
    {
        if ($pdo->query("SELECT 1 FROM sqlite_schema WHERE type='table' AND name='framework_migrations'")->fetchColumn() === false) {
            return 'absent';
        }
        $rows = $pdo->query('SELECT id,migration,batch FROM framework_migrations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        if ($rows !== [['id' => 1, 'migration' => self::migrationName($signature), 'batch' => 1]]) {
            throw new RuntimeException('Only the exactly-owned adoption ledger can be reused or removed.');
        }

        return 'owned';
    }

    public function removeOwnedLedger(SQLiteConnection $connection, PDO $reference, ?Closure $sourceGuard = null): array
    {
        if ($connection->transactionLevel() !== 0 || $connection->getConfig('transaction_mode') !== 'IMMEDIATE') {
            throw new RuntimeException('Ledger backout must own an immediate transaction.');
        }
        $pdo = $connection->getPdo();
        $this->preflight->inspect($pdo, $reference);
        $connection->setReconnector(static fn () => throw new RuntimeException('Reconnect during ledger backout is forbidden.'));
        $connection->beginTransaction();
        try {
            $checked = $this->preflight->inspect($pdo, $reference);
            $sourceGuard?->__invoke($pdo);
            $owned = self::ledgerState($pdo, $checked['schema_signature']) === 'owned';
            if ($owned) {
                $this->repository($connection)->deleteRepository();
            }
            $sourceGuard?->__invoke($pdo);
            if ($connection->getPdo() !== $pdo) {
                throw new RuntimeException('Connection changed during ledger backout.');
            }
            $connection->commit();

            return $checked + ['ledger_removed' => $owned];
        } catch (Throwable $error) {
            $connection->rollBack();
            throw $error;
        }
    }

    private function repository(SQLiteConnection $connection): DatabaseMigrationRepository
    {
        $name = $connection->getName();
        if (! is_string($name) || $name === '') {
            throw new RuntimeException('A specifically named adoption connection is required.');
        }
        $resolver = new ConnectionResolver([$name => $connection]);
        $repository = new DatabaseMigrationRepository($resolver, V2Preflight::LEDGER);
        $repository->setSource($name);

        return $repository;
    }
}
