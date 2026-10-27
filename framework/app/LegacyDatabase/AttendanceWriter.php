<?php

namespace App\LegacyDatabase;

use App\Terminal\AdmittedTerminalKeys;
use App\Terminal\LegacyTerminalRequest;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\SQLiteConnection;
use LogicException;
use PDO;
use Pdo\Sqlite;
use RuntimeException;
use Throwable;

/** Opt-in domain bridge, not a path opener, route, or production connection. */
final class AttendanceWriter
{
    private readonly SQLiteConnection $connection;
    private bool $unusable = false;

    // The caller must first admit/preflight/adopt the known-v2 private file.
    // Own a bare stock connection: no shared manager, listeners, read replica,
    // transaction callbacks, or reconnect hooks can replace this writer's PDO.
    public function __construct(
        private readonly PDO $pdo,
        array $configuration,
        private readonly AuditContext $context,
        AuditFunctions $functions,
    ) {
        if (! $pdo instanceof Sqlite || $pdo->inTransaction()
            || ($configuration['driver'] ?? null) !== 'sqlite'
            || ($configuration['transaction_mode'] ?? null) !== 'IMMEDIATE'
            || ($configuration['busy_timeout'] ?? null) !== 5000
            || $pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new LogicException('A dedicated admitted PHP 8.5 SQLite writer with IMMEDIATE/5000 is required.');
        }
        $databases = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
        if (count($databases) !== 1 || $databases[0]['name'] !== 'main'
            || $databases[0]['file'] !== ($configuration['database'] ?? null)
            || ! in_array($pdo->query('PRAGMA journal_mode')->fetchColumn(), ['delete', 'truncate', 'persist'], true)
            || (int) $pdo->query('PRAGMA busy_timeout')->fetchColumn() !== 5000
            || (int) $pdo->query('PRAGMA query_only')->fetchColumn() !== 0) {
            throw new LogicException('Only the admitted rollback-journal file and its existing settings are supported.');
        }

        $functions->register($pdo);
        $this->connection = new SQLiteConnection($pdo, $configuration['database'], '', $configuration);
        $this->connection->setReconnector(static fn () => throw new RuntimeException('A writer must never reconnect; admit a fresh operation scope instead.'));
        require_once dirname(__DIR__, 3).'/lib/attendance.php';
    }

    public function transition(int $user, string $source, string $eventId, string $action, bool $confirm = false, ?int $expectedRecord = null): array
    {
        return $this->executeLocked(
            fn (): array => attendance_transition_locked($this->pdo, $user, $source, $eventId, $action, $confirm, $expectedRecord),
            fn (Closure $transaction): mixed => $this->context->run($source.':user:'.$user, 'Stempelereignis '.$action.' / '.$eventId, $transaction),
        );
    }

    /** Same captured PDO for the transport's pre-body check and its operation. */
    public function terminalRequireEnabled(): void
    {
        $this->assertIdle();
        require_once dirname(__DIR__, 3).'/lib/terminal_core.php';
        attendance_require_enabled($this->pdo);
        $this->assertIdle();
    }

    /** Native device/grant identity only; no web session, actor or SQL callback. */
    public function terminal(LegacyTerminalRequest $request, AdmittedTerminalKeys $keys): array
    {
        // Match terminal_handle's repeated module check. Never wrap the entire
        // request: native nonce, limits and identify writes must autocommit first.
        $this->terminalRequireEnabled();
        $input = $request->input;
        if (($input['op'] ?? '') === 'pair') {
            terminal_limit($this->pdo, 'pair:'.$request->remoteAddress, 20);
            $device = $this->executeLocked(fn (): array => terminal_pair_locked($this->pdo, $input));

            // Pairing has committed. Key failure remains an unconfirmed failure,
            // not a rollback or retry. Do not imitate native invalid post-COMMIT
            // ROLLBACK masking; the transport still returns the generic 503 shape.
            $response = terminal_pair_response($device, fn (): string => $keys->deviceSecret());
            $this->assertIdle();

            return $response;
        }

        $device = terminal_auth_core($this->pdo, $request->body, [
            'HTTP_X_TERMINAL_ID' => $request->terminalId,
            'HTTP_X_TERMINAL_TIME' => $request->terminalTime,
            'HTTP_X_TERMINAL_NONCE' => $request->terminalNonce,
            'HTTP_X_TERMINAL_SIGNATURE' => $request->terminalSignature,
        ], fn (): string => $keys->deviceSecret());
        $this->assertIdle();
        if (($input['op'] ?? '') === 'identify') {
            $response = terminal_identify_core($this->pdo, $device, $input, fn (): string => $keys->cardSecret());
            $this->assertIdle();

            return $response;
        }

        return $this->executeLocked(fn (): array => terminal_dispatch_locked(
            $this->pdo, $device, $input,
            fn (string $actor, string $reason, Closure $operation): mixed => $this->context->run($actor, $reason, $operation),
        ));
    }

    /** Trusted server identity/role only; never supplied by correction command data. */
    public function correction(int $user, string $role, array $command): void
    {
        require_once dirname(__DIR__, 3).'/lib/corrections.php';

        $this->executeLocked(function () use ($user, $role, $command): void {
            correction_apply_locked($this->pdo, $user, $role, $command,
                fn (string $reason, Closure $operation): mixed => $this->context->run('web:'.$user, $reason, $operation),
            );
        });
    }

    /** Trusted admin identity/role only; command user_id is the model target. */
    public function workModel(int $user, string $role, array $command): void
    {
        require_once dirname(__DIR__, 3).'/lib/practice.php';

        $this->executeLocked(function () use ($user, $role, $command): void {
            practice_work_model_apply_locked($this->pdo, $user, $role, $command,
                fn (string $reason, Closure $operation): mixed => $this->context->run('web:'.$user, $reason, $operation),
            );
        });
    }

    /** One fixed Admin operation; no caller-supplied role, PDO or SQL callback. */
    public function pauseSettings(int $user, mixed $hoursThresholds, mixed $minimumPauses): void
    {
        require_once dirname(__DIR__, 3).'/lib/pause_settings.php';
        // Exact native complete-list validation before any DELETE/INSERT or transaction.
        $rules = \validatePauseSettings($hoursThresholds, $minimumPauses);

        $this->executeLocked(function () use ($user, $rules): void {
            // Re-authorize under the existing IMMEDIATE writer lock, not against
            // a request role or an earlier rendered/refreshed guard snapshot.
            $actor = att_one($this->pdo, 'SELECT role,auth_source FROM users WHERE id=?', [$user]);
            if ($user < 1 || $actor === null || $actor['role'] !== 'admin' || $actor['auth_source'] !== 'local') {
                throw new AuthorizationException('Nur die Verwaltung darf Pauseneinstellungen ändern.');
            }

            $statement = $this->pdo->prepare('DELETE FROM pause_settings');
            $statement->execute();
            $statement->closeCursor();
            $statement = $this->pdo->prepare('INSERT INTO pause_settings (hours_threshold, minimum_pause) VALUES (?, ?)');
            foreach ($rules as $rule) {
                $statement->execute($rule);
                $statement->closeCursor();
            }
            // The existing transaction shell commits the complete list once.
            // This native table has no audit trigger; do not invent an audit claim.
        });
    }

    /** Trusted own-user identity only; no caller-supplied audit actor/reason. */
    public function absence(int $user, string $start, string $end, string $kind): array
    {
        require_once dirname(__DIR__, 3).'/lib/practice.php';

        // Match native date/type refusal before BEGIN. The core also validates
        // under lock through this same parser; no caller can bypass the rules.
        practice_absence_range($start, $end, $kind);

        return $this->executeLocked(fn (): array => practice_absence_apply_locked(
            $this->pdo, $user, $start, $end, $kind,
            fn (string $reason, Closure $operation): mixed => $this->context->run('web:'.$user, $reason, $operation),
        ));
    }

    // The single existing #75 transaction/guard/failure lifecycle, shared only
    // internally. No public arbitrary-write API or second transaction manager.
    private function executeLocked(Closure $body, ?Closure $auditScope = null): mixed
    {
        $this->assertIdle();
        // The legacy operation still sets globals. Framework UDFs use scoped
        // context instead; nevertheless restore even pre-existing null values.
        $globals = array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason']));

        try {
            $transaction = function () use ($body): mixed {
                // Exactly one attempt. Laravel owns BEGIN/COMMIT/ROLLBACK and
                // transactionLevel; the domain's raw-BEGIN wrapper is NOT used.
                $this->connection->beginTransaction();
                $this->assertActive();
                $response = $body();
                $this->assertActive();
                $this->connection->commit();
                $this->assertIdle();

                return $response;
            };

            return $auditScope === null ? $transaction() : $auditScope($transaction);
        } catch (Throwable $error) {
            try {
                // Explicit begin/commit (not transaction()) leaves Laravel's
                // level intact on COMMIT failure. Rollback also releases a BUSY
                // commit's still-live SQLite transaction; never retry the body.
                if ($this->connection->getRawPdo() !== $this->pdo) {
                    $this->unusable = true;
                    // Never roll back/commit a replacement handle. The captured
                    // PDO is the only handle that may own our domain mutation.
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                } else {
                    $this->connection->rollBack(0);
                }
                $this->assertIdle();
            } catch (Throwable) {
                $this->unusable = true;
                // Preserve the primary failure. The caller must discard this
                // adapter/PDO, not continue or guess a successful commit.
            }
            throw $error;
        } finally {
            unset($GLOBALS['audit_actor'], $GLOBALS['audit_reason']);
            foreach ($globals as $key => $value) {
                $GLOBALS[$key] = $value;
            }
        }
    }

    private function assertIdle(): void
    {
        if ($this->unusable || $this->connection->getRawPdo() !== $this->pdo
            || $this->connection->transactionLevel() !== 0 || $this->pdo->inTransaction()) {
            throw new LogicException('The writer is not idle; nesting, savepoints and connection replacement are forbidden.');
        }
    }

    private function assertActive(): void
    {
        if ($this->connection->getRawPdo() !== $this->pdo
            || $this->connection->transactionLevel() !== 1 || ! $this->pdo->inTransaction()) {
            throw new LogicException('The single writer transaction was replaced or interrupted.');
        }
    }
}
