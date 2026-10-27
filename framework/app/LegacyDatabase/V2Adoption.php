<?php

namespace App\LegacyDatabase;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
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
        $pdo = $connection->getPdo();
        MemoryOnly::connection($pdo);
        if ($connection->transactionLevel() !== 0 || $pdo->inTransaction()) {
            throw new RuntimeException('Adoption must own its transaction boundary.');
        }

        // Preflight precedes even the creation of the Laravel tracking table.
        $this->preflight->inspect($pdo, $reference);
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $checked = $this->preflight->inspect($pdo, $reference);
            $migration = '2026_10_27_000001_adopt_legacy_v2_'.$checked['schema_signature'];
            $repository = new DatabaseMigrationRepository($this->database, V2Preflight::LEDGER);
            $repository->setSource($connectionName);
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
            if ($connection->getPdo() !== $pdo) {
                throw new RuntimeException('Connection changed during adoption.');
            }
            $pdo->exec('COMMIT');

            return $checked + ['registered' => $ran === [], 'migration' => $migration];
        } catch (Throwable $error) {
            $pdo->exec('ROLLBACK');
            throw $error;
        }
    }
}
