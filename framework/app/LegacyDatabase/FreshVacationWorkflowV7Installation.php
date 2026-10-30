<?php

namespace App\LegacyDatabase;

use Closure;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\SQLiteConnection;
use PDO;
use Pdo\Sqlite;
use RuntimeException;
use Throwable;

/** Fresh explicit source ledger only: no path opener, file creator, upgrade or runtime switch. */
final readonly class FreshVacationWorkflowV7Installation
{
    public function __construct(private VacationWorkflowV7References $references) {}

    /**
     * Caller owns a NEW private source and canonical schema creation. A trusted
     * file-identity/ownership guard is mandatory for a non-memory source; never
     * derive it, connection/path or the signature inventory from request data.
     */
    public function registerFreshConnection(SQLiteConnection $connection, ?Closure $sourceGuard = null): array
    {
        $pdo = $connection->getPdo();
        if (! $pdo instanceof Sqlite || $pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION
            || $connection->getName() !== 'chrono_fresh_vacation_v7'
            || $connection->getConfig('transaction_mode') !== 'IMMEDIATE'
            || $connection->transactionLevel() !== 0 || $pdo->inTransaction()
            || (int) $pdo->query('PRAGMA query_only')->fetchColumn() !== 0
            || (int) $pdo->query('PRAGMA busy_timeout')->fetchColumn() !== 5000) {
            throw new RuntimeException('A fresh dedicated idle IMMEDIATE source connection is required.');
        }
        $databases = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
        $main = array_values(array_filter($databases, static fn (array $row): bool => $row['name'] === 'main'));
        if (count($main) !== 1 || array_diff(array_column($databases, 'name'), ['main', 'temp']) !== []) {
            throw new RuntimeException('Only the one explicit fresh source is supported.');
        }
        $memory = $main[0]['file'] === '';
        if (($memory && $connection->getDatabaseName() !== ':memory:')
            || (! $memory && ($sourceGuard === null || $connection->getDatabaseName() !== $main[0]['file']))
            || ! in_array($pdo->query('PRAGMA journal_mode')->fetchColumn(), $memory ? ['memory'] : ['delete', 'truncate', 'persist'], true)) {
            throw new RuntimeException('A fresh private rollback-journal source requires explicit identity admission.');
        }
        $checked = $this->references->requireFreshLoginBudget($pdo);
        $source = $this->references->preservedSource($pdo);
        $rows = RuntimeAdmission::vacationWorkflowV7LedgerRows(...array_values($this->references->signatures()));
        $state = $this->ledgerState($pdo, $rows);
        $sourceGuard?->__invoke($pdo);
        if ($this->references->requireFreshLoginBudget($pdo) !== $checked || $this->references->preservedSource($pdo) !== $source
            || $this->ledgerState($pdo, $rows) !== $state
            || $connection->getRawPdo() !== $pdo || $pdo->inTransaction() || $connection->transactionLevel() !== 0) {
            throw new RuntimeException('The fresh source changed during initial admission.');
        }
        if ($state === 'complete') {
            return $checked + $this->references->signatures() + ['registered' => false, 'already_registered' => true,
                'schema_version' => $pdo->query('PRAGMA schema_version')->fetchColumn()];
        }
        $connection->setReconnector(static fn () => throw new RuntimeException('Fresh installation must not reconnect.'));
        try {
            $connection->beginTransaction();
            $this->requireActive($connection, $pdo);
            if ($this->references->requireFreshLoginBudget($pdo) !== $checked || $this->references->preservedSource($pdo) !== $source
                || $this->ledgerState($pdo, $rows) !== $state) {
                throw new RuntimeException;
            }
            $sourceGuard?->__invoke($pdo);
            $this->requireActive($connection, $pdo);
            $repository = new DatabaseMigrationRepository(new ConnectionResolver(['chrono_fresh_vacation_v7' => $connection]), V2Preflight::LEDGER);
            $repository->setSource('chrono_fresh_vacation_v7');
            if (! $repository->repositoryExists()) {
                $repository->createRepository();
            }
            foreach ($rows as $row) {
                $repository->log($row['migration'], $row['batch']);
            }
            if ($this->references->requireFreshLoginBudget($pdo) !== $checked || $this->references->preservedSource($pdo) !== $source
                || $this->ledgerState($pdo, $rows) !== 'complete') {
                throw new RuntimeException;
            }
            $sourceGuard?->__invoke($pdo);
            $this->requireActive($connection, $pdo);
            if ($this->references->requireFreshLoginBudget($pdo) !== $checked || $this->references->preservedSource($pdo) !== $source
                || $this->ledgerState($pdo, $rows) !== 'complete') {
                throw new RuntimeException;
            }
            $connection->commit();
            if ($connection->getRawPdo() !== $pdo || $pdo->inTransaction() || $connection->transactionLevel() !== 0) {
                throw new RuntimeException;
            }
            $sourceGuard?->__invoke($pdo);

            return $checked + $this->references->signatures() + ['registered' => true, 'already_registered' => false,
                'schema_version' => $pdo->query('PRAGMA schema_version')->fetchColumn()];
        } catch (Throwable) {
            try {
                if ($connection->getRawPdo() === $pdo && $connection->transactionLevel() > 0) {
                    $connection->rollBack(0);
                } elseif ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($pdo->inTransaction() || ($connection->getRawPdo() === $pdo && $connection->transactionLevel() !== 0)) {
                    throw new RuntimeException;
                }
            } catch (Throwable) {
                throw new RuntimeException('Fresh registration outcome is uncertain; keep the target private for review.');
            }
            throw new RuntimeException('Fresh registration was not confirmed; keep the target private and inspect before retry.');
        }
    }

    private function ledgerState(PDO $pdo, array $expected): string
    {
        if ($pdo->query("SELECT 1 FROM sqlite_schema WHERE type='table' AND name='framework_migrations'")->fetchColumn() === false) {
            return 'absent';
        }
        $rows = $pdo->query('SELECT id,migration,batch FROM framework_migrations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $sequence = $pdo->query("SELECT seq FROM sqlite_sequence WHERE name='framework_migrations'")->fetchColumn();
        if ($rows === [] && in_array($sequence, [false, 0], true)) {
            return 'empty';
        }
        if ($rows === $expected && $sequence === 5) {
            return 'complete';
        }
        throw new RuntimeException('Unexpected installation ledger requires review.');
    }

    private function requireActive(SQLiteConnection $connection, PDO $pdo): void
    {
        if ($connection->getRawPdo() !== $pdo || ! $pdo->inTransaction() || $connection->transactionLevel() !== 1) {
            throw new RuntimeException('The one fresh installation transaction or captured handle changed.');
        }
    }
}
