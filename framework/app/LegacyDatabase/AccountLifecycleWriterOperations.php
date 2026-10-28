<?php

namespace App\LegacyDatabase;

use App\Accounts\AccountLifecycleAuthority;
use App\Accounts\AccountLifecycleChange;
use App\Accounts\AccountLifecycleTransitionResult;
use Closure;
use InvalidArgumentException;
use LogicException;
use PDO;

/** Fixed V5 operations only; AttendanceWriter owns the sole lock and audit UDFs. */
trait AccountLifecycleWriterOperations
{
    /** Fixed Web actor/namespace; generation is checked before replay and DML. */
    public function transitionWeb(AccountLifecycleAuthority $actor, string $eventId, string $action,
        bool $confirm = false, ?int $expectedRecord = null): array
    {
        $this->requireLifecycleWebProfile();
        $source = 'web:'.$actor->id;

        return $this->executeLocked(function () use ($actor, $source, $eventId, $action, $confirm, $expectedRecord): array {
            $this->assertLifecycleWebPrincipalLocked($actor);

            return attendance_transition_locked($this->pdo, $actor->id, $source, $eventId, $action,
                $confirm, $expectedRecord, null, true);
        }, fn (Closure $transaction): mixed => $this->context->run($source.':user:'.$actor->id,
            'Stempelereignis '.$action.' / '.$eventId, $transaction), lifecycleReady: true);
    }

    public function absenceWeb(AccountLifecycleAuthority $actor, string $start, string $end, string $kind): array
    {
        $this->requireLifecycleWebProfile();
        require_once dirname(__DIR__, 3).'/lib/practice.php';
        practice_absence_range($start, $end, $kind);

        return $this->executeLocked(function () use ($actor, $start, $end, $kind): array {
            $this->assertLifecycleWebPrincipalLocked($actor);

            return practice_absence_apply_locked($this->pdo, $actor->id, $start, $end, $kind,
                fn (string $reason, Closure $operation): mixed => $this->context->run('web:'.$actor->id, $reason, $operation));
        }, lifecycleReady: true);
    }

    public function correctionWeb(AccountLifecycleAuthority $actor, array $command): void
    {
        $this->requireLifecycleWebProfile();
        require_once dirname(__DIR__, 3).'/lib/corrections.php';
        $this->executeLocked(function () use ($actor, $command): void {
            $this->assertLifecycleWebPrincipalLocked($actor);
            if ($actor->source === 'ldap' && ($command['action'] ?? null) !== 'request') {
                throw new \AttendanceError('Keine Freigabeberechtigung.', 403);
            }
            correction_apply_locked($this->pdo, $actor->id, $actor->role, $command,
                fn (string $reason, Closure $operation): mixed => $this->context->run('web:'.$actor->id, $reason, $operation));
        }, lifecycleReady: true);
    }

    public function withdrawCorrectionWeb(AccountLifecycleAuthority $actor, #[\SensitiveParameter] string $version,
        mixed $id, mixed $reason): void
    {
        $this->requireLifecycleWebProfile();
        require_once dirname(__DIR__, 3).'/lib/correction_withdrawal.php';
        $this->executeLocked(function () use ($actor, $version, $id, $reason): void {
            $this->assertLifecycleWebPrincipalLocked($actor);
            if (! $actor->binds($actor->id, 'local', $version, $actor->role)) {
                throw new \AttendanceError('Sitzung nicht mehr gültig. Bitte neu anmelden.', 401);
            }
            correction_withdraw_locked($this->pdo, $actor->id, $actor->role, $version, $id, $reason,
                fn (string $auditReason, Closure $operation): mixed => $this->context->run('web:'.$actor->id, $auditReason, $operation));
        }, lifecycleReady: true);
    }

    private function requireLifecycleWebProfile(): void
    {
        if (! $this->accountLifecycleV5) {
            throw new LogicException('V5 Web authority cannot target an older account profile.');
        }
    }

    private function assertLifecycleWebPrincipalLocked(AccountLifecycleAuthority $actor): void
    {
        $this->assertLifecyclePrincipalLocked($actor);
        // Keep the existing Web profile's LDAP eligibility: ordinary users only.
        if ($actor->source === 'ldap' && $actor->role !== 'user') {
            throw new \AttendanceError('Sitzung nicht mehr gültig. Bitte neu anmelden.', 401);
        }
    }

    /** Fixed API actor/namespace; no target/source/generation supplied by command data. */
    public function transitionApi(AccountLifecycleAuthority $actor, string $eventId, string $action,
        bool $confirm = false, ?int $expectedRecord = null): array
    {
        if (! $this->accountLifecycleV5) {
            throw new LogicException('V5 API authority cannot target an older account profile.');
        }
        $source = 'api:'.$actor->id;

        return $this->executeLocked(function () use ($actor, $source, $eventId, $action, $confirm, $expectedRecord): array {
            $this->assertLifecyclePrincipalLocked($actor);

            return attendance_transition_locked($this->pdo, $actor->id, $source, $eventId, $action, $confirm, $expectedRecord);
        }, fn (Closure $transaction): mixed => $this->context->run($source.':user:'.$actor->id,
            'Stempelereignis '.$action.' / '.$eventId, $transaction), lifecycleReady: true);
    }

    public function changeAccountLifecycle(AccountLifecycleAuthority $actor,
        AccountLifecycleChange $change): AccountLifecycleTransitionResult
    {
        if (! $this->accountLifecycleV5) {
            throw new LogicException('Lifecycle transitions require the separate V5 profile.');
        }

        return $this->executeLocked(function () use ($actor, $change): AccountLifecycleTransitionResult {
            $this->assertLifecyclePrincipalLocked($actor, localAdmin: true);
            $target = att_one($this->pdo, 'SELECT id,username,role,auth_source,password,account_status,auth_generation FROM users WHERE id=?', [$change->target]);
            if ($target === null || $target['account_status'] !== $change->expectedStatus
                || $target['auth_generation'] !== $change->expectedGeneration) {
                throw new \AttendanceError('Kontostatus wurde inzwischen geändert. Bitte neu laden.', 409);
            }
            $next = $target;
            $next['account_status'] = $change->nextStatus;
            $next['auth_generation'] = $change->expectedGeneration + 1;
            $this->assertLocalRecoveryRetainedLocked($change->target, $next);

            // One retained row only. No attendance, grant, session, device or
            // credential mutation. The V5 redacted trigger owns the one audit.
            $statement = $this->pdo->prepare('UPDATE users SET account_status=?,auth_generation=? WHERE id=? AND account_status=? AND auth_generation=?');
            try {
                $statement->execute([$next['account_status'], $next['auth_generation'], $change->target,
                    $change->expectedStatus, $change->expectedGeneration]);
                if ($statement->rowCount() !== 1) {
                    throw new \AttendanceError('Kontostatus wurde inzwischen geändert. Bitte neu laden.', 409);
                }
            } finally {
                $statement->closeCursor();
            }

            return new AccountLifecycleTransitionResult($change->target, $next['account_status'], $next['auth_generation']);
        }, fn (Closure $transaction): mixed => $this->context->run('web:'.$actor->id,
            'Account lifecycle: '.$change->reason, $transaction), lifecycleReady: true);
    }

    private function assertLifecyclePrincipalLocked(AccountLifecycleAuthority $actor, bool $localAdmin = false): void
    {
        $this->assertActive();
        if (! $this->accountLifecycleV5) {
            throw new LogicException('A V5 authority cannot target an older account profile.');
        }
        $this->assertIntakeProfile();
        $row = att_one($this->pdo, 'SELECT id,username,role,auth_source,password,account_status,auth_generation FROM users WHERE id=?', [$actor->id]);
        if ($row === null || ! $actor->matches($row)
            || ($localAdmin && ! AccountLifecycleAuthority::locallyLoginEligibleAdmin($row))) {
            throw new \AttendanceError('Sitzung nicht mehr gültig. Bitte neu anmelden.', 401);
        }
    }

    /**
     * Shared invariant for status/role/password/source changes and deletion.
     * A null replacement denotes deletion. Never count LDAP/inactive/broken
     * hashes as local recovery. This deliberately cannot prove human knowledge.
     */
    private function assertLocalRecoveryRetainedLocked(int $target, #[\SensitiveParameter] ?array $replacement): void
    {
        $this->assertActive();
        if (! $this->accountLifecycleV5) {
            throw new LogicException('V5 local recovery checks require V5.');
        }
        if ($replacement !== null && AccountLifecycleAuthority::locallyLoginEligibleAdmin($replacement)) {
            return;
        }
        $statement = $this->pdo->prepare("SELECT username,role,auth_source,password,account_status,auth_generation FROM users WHERE id<>? AND role='admin' AND auth_source='local' AND account_status='active'");
        try {
            $statement->execute([$target]);
            while (($candidate = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (AccountLifecycleAuthority::locallyLoginEligibleAdmin($candidate)) {
                    return;
                }
            }
        } finally {
            $statement->closeCursor();
        }
        throw new InvalidArgumentException('Der letzte aktive lokal anmeldbare Administrator muss erhalten bleiben.');
    }
}
