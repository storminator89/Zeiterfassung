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
