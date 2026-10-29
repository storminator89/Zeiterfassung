<?php

namespace App\Accounts;

use Illuminate\Database\SQLiteConnection;
use LogicException;
use PDO;
use Pdo\Sqlite;
use Throwable;

/** Internal V5 route prerequisite, not an opener or proof of human recovery. */
final class LocalWebRecoveryEligibility
{
    /** Own only the existing Runtime reader; return no row, hash or handle. */
    public static function requireReadSnapshot(SQLiteConnection $reader, bool $includeSetup): void
    {
        try {
            try {
                // Never resolve a lazy PDO or upgrade/reuse an existing transaction.
                $pdo = $reader->getRawPdo();
                if (! $pdo instanceof Sqlite || $reader->getName() !== 'chrono'
                    || $reader->getConfig('transaction_mode') !== 'DEFERRED'
                    || $reader->transactionLevel() !== 0 || $pdo->inTransaction()) {
                    throw new LogicException;
                }
                $statement = $pdo->query('PRAGMA query_only');
                try {
                    if ($statement->fetchColumn() !== 1) { throw new LogicException; }
                } finally {
                    if (! $statement->closeCursor()) { throw new LogicException; }
                }
                $reader->transaction(static function () use ($reader, $pdo, $includeSetup): void {
                    if ($reader->getRawPdo() !== $pdo || ! $pdo->inTransaction()) { throw new LogicException; }
                    self::requireEligibleAdminOrEmptySetup($pdo, $includeSetup);
                    if ($reader->getRawPdo() !== $pdo) { throw new LogicException; }
                }, 1); // No retry, writer resolution or read-to-write upgrade.
            } finally {
                $reader->disconnect();
            }
        } catch (Throwable) {
            throw new LogicException('Local V5 web account availability could not be confirmed.');
        }
    }

    private static function requireEligibleAdminOrEmptySetup(PDO $pdo, bool $includeSetup): void
    {
        $statement = $pdo->query("SELECT username,role,auth_source,password,account_status,auth_generation FROM users WHERE role='admin' AND auth_source='local' AND account_status='active'");
        try {
            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                // Reuse exactly the sole writer's syntactic last-local-Admin rule.
                if (AccountLifecycleAuthority::locallyLoginEligibleAdmin($row)) { return; }
            }
        } finally {
            if (! $statement->closeCursor()) { throw new LogicException; }
        }
        if ($includeSetup) {
            $statement = $pdo->query('SELECT 1 FROM users LIMIT 1');
            try {
                if ($statement->fetchColumn() === false) { return; }
            } finally {
                if (! $statement->closeCursor()) { throw new LogicException; }
            }
        }
        // A token cannot recover a populated source with no eligible local Admin.
        // Existing first-admin token/zero-user/sole-lock rules remain mandatory.
        throw new LogicException;
    }
}
