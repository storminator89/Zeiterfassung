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

/** One explicit offline schema operation, never a request writer or path opener. */
final class UserAuditV3Upgrade
{
    public function __construct(private readonly V2Preflight $preflight, private readonly array $mapping) {}

    public static function migrationName(string $signature): string
    {
        return RuntimeAdmission::userAuditV3MigrationName($signature);
    }

    /** Read-only; both references must be anonymous, canonical and separately built. */
    public function inspect(PDO $target, PDO $v2Reference, PDO $v3Reference): array
    {
        $base = $this->preflight->inspect($v2Reference, $v2Reference);
        $post = $this->preflight->inspectUserAuditV3($v3Reference, $v3Reference);
        $versions = $target->query('SELECT version FROM app_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
        if ($versions === [1, 2]) {
            $checked = $this->preflight->inspect($target, $v2Reference);
            if ($checked['schema_signature'] !== $base['schema_signature']
                || V2Adoption::ledgerState($target, $base['schema_signature']) !== 'owned') {
                throw new RuntimeException('An exactly adopted canonical V2 source is required.');
            }
            $profile = 'legacy-v2';
        } elseif ($versions === [1, 2, 3]) {
            $checked = $this->preflight->inspectUserAuditV3($target, $v3Reference);
            if ($checked['schema_signature'] !== $post['schema_signature']
                || $target->query('SELECT id,migration,batch FROM framework_migrations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)
                    !== RuntimeAdmission::userAuditV3LedgerRows($base['schema_signature'], $post['schema_signature'])
                || $target->query("SELECT seq FROM sqlite_sequence WHERE name='framework_migrations'")->fetchColumn() !== 2) {
                throw new RuntimeException('The complete canonical V3 schema and exact two-row ledger are required.');
            }
            $profile = 'user-audit-v3';
        } else {
            throw new RuntimeException('Only canonical adopted V2 or complete user-audit-v3 is supported.');
        }

        return $checked + ['profile' => $profile, 'base_schema_signature' => $base['schema_signature'],
            'post_schema_signature' => $post['schema_signature']];
    }

    /** Only marker3 is excluded; all business/audit rows and other sequences remain covered. */
    public function preservedSource(PDO $pdo): array
    {
        $mapping = $this->mapping;
        $mapping['tables'] = array_values(array_filter($mapping['tables'], static fn (array $table): bool => $table['name'] !== 'app_migrations'));

        return SourceSnapshot::capture($pdo, $mapping);
    }

    /** Caller already verified the stopped target and distinct current backup. */
    public function applyConnection(SQLiteConnection $connection, PDO $v2Reference, PDO $v3Reference, ?Closure $sourceGuard = null): array
    {
        $pdo = $connection->getPdo();
        if (! $pdo instanceof Sqlite || $pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION
            || $connection->getName() !== 'chrono_offline_user_audit_v3' || $connection->transactionLevel() !== 0
            || $pdo->inTransaction() || $connection->getConfig('transaction_mode') !== 'IMMEDIATE') {
            throw new RuntimeException('The fixed offline upgrade must own its IMMEDIATE transaction.');
        }
        $checked = $this->inspect($pdo, $v2Reference, $v3Reference);
        $sourceGuard?->__invoke($pdo);
        if ($checked['profile'] === 'user-audit-v3') {
            return $checked + ['changed' => false, 'already_applied' => true];
        }
        $source = $this->preservedSource($pdo);
        $connection->setReconnector(static fn () => throw new RuntimeException('Reconnect during user audit upgrade is forbidden.'));
        try {
            $connection->beginTransaction();
            $this->requireActive($connection, $pdo);
            $locked = $this->inspect($pdo, $v2Reference, $v3Reference);
            if ($locked !== $checked || $this->preservedSource($pdo) !== $source) {
                throw new RuntimeException('The source changed before the offline upgrade lock.');
            }
            $sourceGuard?->__invoke($pdo);
            require_once dirname(__DIR__, 3).'/lib/user_audit_v3.php';
            \user_audit_v3_apply_locked($pdo);
            $repository = new DatabaseMigrationRepository(new ConnectionResolver(['chrono_offline_user_audit_v3' => $connection]), V2Preflight::LEDGER);
            $repository->setSource('chrono_offline_user_audit_v3');
            $repository->log(self::migrationName($checked['post_schema_signature']), 2);
            $after = $this->inspect($pdo, $v2Reference, $v3Reference);
            if ($after['profile'] !== 'user-audit-v3' || $this->preservedSource($pdo) !== $source
                || $pdo->query("SELECT seq FROM sqlite_sequence WHERE name='framework_migrations'")->fetchColumn() !== 2) {
                throw new RuntimeException('Unexpected post-upgrade schema, ledger sequence or source change.');
            }
            $sourceGuard?->__invoke($pdo);
            $this->requireActive($connection, $pdo);
            $connection->commit();
            if ($connection->getRawPdo() !== $pdo || $connection->transactionLevel() !== 0 || $pdo->inTransaction()) {
                throw new RuntimeException('Upgrade commit outcome requires offline review.');
            }

            return $after + ['changed' => true, 'already_applied' => false];
        } catch (Throwable) {
            try {
                // Entry was idle; only this captured handle may own our transaction.
                if ($connection->getRawPdo() === $pdo && $connection->transactionLevel() > 0) {
                    $connection->rollBack(0);
                } elseif ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($pdo->inTransaction() || ($connection->getRawPdo() === $pdo && $connection->transactionLevel() !== 0)) {
                    throw new RuntimeException;
                }
            } catch (Throwable) {
                throw new RuntimeException('User audit upgrade outcome is uncertain; keep offline and review before retry or admission.');
            }
            // No raw SQL/values/previous exception, retry, restore or rollback claim.
            throw new RuntimeException('User audit upgrade was not confirmed; keep offline and inspect the saved state.');
        }
    }

    private function requireActive(SQLiteConnection $connection, PDO $pdo): void
    {
        if ($connection->getRawPdo() !== $pdo || $connection->transactionLevel() !== 1 || ! $pdo->inTransaction()) {
            throw new RuntimeException('The one offline transaction or captured handle changed.');
        }
    }
}
