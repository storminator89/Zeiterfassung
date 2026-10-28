<?php

namespace App\Api;

use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Fixed counter operations on an already admitted, caller-owned IMMEDIATE PDO.
 * No opener, DDL, transaction ownership, retries, authentication or HTTP output.
 * inTransaction() is necessary, NOT proof of IMMEDIATE or physical admission.
 */
final class SharedSqliteLoginBudgetStore
{
    /** $now is one trusted server epoch sample, never a request-selected value. */
    public function consumeLocked(PDO $pdo, string $username, string $remoteAddress, int $now): LoginBudgetDecision
    {
        try {
            if ($now < 600 || ! $pdo->inTransaction()
                || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite'
                || $pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
                throw new RuntimeException;
            }

            // Validate BEFORE cleanup can hide a bad row or SQLite's +1 can
            // coerce malformed TEXT/REAL into an integer.
            $this->requireValidRows($pdo);

            // Strictly older, not <=; retain the original window start on hits.
            $this->execute($pdo, 'DELETE FROM main.login_attempts WHERE starts < ?', [$now - 600]);

            // Native priority: an exhausted IP consumes only its own counter.
            foreach (['ip:'.$remoteAddress => 60, 'user:'.strtolower($username) => 15] as $name => $limit) {
                $bucket = hash('sha256', $name);
                $this->execute($pdo, 'INSERT INTO main.login_attempts(bucket, starts, attempts) VALUES (?, ?, 1) ON CONFLICT(bucket) DO UPDATE SET attempts=attempts+1', [$bucket, $now]);
                $statement = $pdo->prepare('SELECT attempts FROM main.login_attempts WHERE bucket=?');
                if ($statement === false || ! $statement->execute([$bucket])) {
                    throw new RuntimeException;
                }
                try {
                    $attempts = $statement->fetchColumn();
                    // The admitted stock SQLite handle returns INTEGER as int.
                    // A missing/corrupt/overflowed counter is technical failure.
                    if (! is_int($attempts) || $attempts < 1 || $statement->fetchColumn() !== false) {
                        throw new RuntimeException;
                    }
                } finally {
                    if (! $statement->closeCursor()) {
                        throw new RuntimeException;
                    }
                }
                if ($attempts > $limit) {
                    return $this->decision($pdo, true);
                }
            }

            return $this->decision($pdo, false);
        } catch (Throwable) {
            // Caller rolls back/discards on any technical failure. No SQL,
            // identifiers, native driver message or previous exception escapes.
            throw new RuntimeException('Shared login budget operation was not confirmed.');
        }
    }

    private function requireValidRows(PDO $pdo): void
    {
        // All rows, including expired/unrelated buckets, in the same caller
        // lock. Stream values; never cast, normalize, repair or delete them.
        $statement = $pdo->prepare('SELECT starts, attempts, typeof(starts) AS starts_type, typeof(attempts) AS attempts_type FROM main.login_attempts');
        if ($statement === false) {
            throw new RuntimeException;
        }
        try {
            if (! $statement->execute()) {
                throw new RuntimeException;
            }
            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (! is_array($row) || count($row) !== 4
                    || ($row['starts_type'] ?? null) !== 'integer'
                    || ($row['attempts_type'] ?? null) !== 'integer'
                    || ! is_int($row['starts'] ?? null) || $row['starts'] < 0
                    || ! is_int($row['attempts'] ?? null) || $row['attempts'] < 1
                    || $row['attempts'] >= PHP_INT_MAX) {
                    throw new RuntimeException;
                }
            }
        } finally {
            if (! $statement->closeCursor()) {
                throw new RuntimeException;
            }
        }
        if (! $pdo->inTransaction()) {
            throw new RuntimeException;
        }
    }

    private function execute(PDO $pdo, string $sql, array $parameters): void
    {
        $statement = $pdo->prepare($sql);
        if (! $statement instanceof PDOStatement) {
            throw new RuntimeException;
        }
        try {
            if (! $statement->execute($parameters)) {
                throw new RuntimeException;
            }
        } finally {
            if (! $statement->closeCursor()) {
                throw new RuntimeException;
            }
        }
    }

    private function decision(PDO $pdo, bool $limited): LoginBudgetDecision
    {
        if (! $pdo->inTransaction()) {
            throw new RuntimeException;
        }

        // Do NOT translate to 429 here: that exception could roll back the hit.
        return new LoginBudgetDecision($limited);
    }
}
