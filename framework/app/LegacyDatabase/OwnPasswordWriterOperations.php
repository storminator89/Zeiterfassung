<?php

namespace App\LegacyDatabase;

use App\Accounts\AccountLifecycleAuthority;
use LogicException;
use PDO;
use RuntimeException;

/** Fixed own-account password replacement; no target, source or SQL callback from a form. */
trait OwnPasswordWriterOperations
{
    public function replaceOwnLocalPassword(int $actor, string $expectedRole,
        #[\SensitiveParameter] string $expectedVersion, #[\SensitiveParameter] string $nextHash): void
    {
        $this->replaceOwnLocalPasswordOperation($actor, $expectedRole, $expectedVersion, $nextHash,
            'settings.php: own local password change');
    }

    /** Same own CAS/authority as password replacement, but a fixed distinct audit purpose. */
    public function revokeOwnLocalWebApiSessions(int $actor, string $expectedRole,
        #[\SensitiveParameter] string $expectedVersion, #[\SensitiveParameter] string $nextHash): void
    {
        $this->replaceOwnLocalPasswordOperation($actor, $expectedRole, $expectedVersion, $nextHash,
            'settings.php: revoke own local Web/API sessions');
    }

    private function replaceOwnLocalPasswordOperation(int $actor, string $expectedRole,
        #[\SensitiveParameter] string $expectedVersion, #[\SensitiveParameter] string $nextHash, string $auditReason): void
    {
        // This old-profile operation must never turn into a V5 compatibility bypass.
        if ($this->hasGenerationBoundAccountAuthority()) { throw new LogicException('Own password changes are not admitted for V5.'); }
        if (! in_array($expectedRole, ['user', 'supervisor', 'admin'], true)
            || preg_match('/\A[a-f0-9]{64}\z/', $expectedVersion) !== 1
            || (password_get_info($nextHash)['algoName'] ?? 'unknown') !== 'bcrypt') {
            throw new LogicException('Trusted own-password authority and a bcrypt hash are required.');
        }
        $this->executeLocked(function () use ($actor, $expectedRole, $expectedVersion, $nextHash): void {
            $this->assertExpectedWebPrincipal($actor, 'local', $expectedVersion, $expectedRole);
            $current = att_one($this->pdo, 'SELECT password FROM users WHERE id=?', [$actor])['password'] ?? null;
            if (! is_string($current) || ! hash_equals($expectedVersion, hash('sha256', $current))) {
                throw new \AttendanceError('Sitzung nicht mehr gültig. Bitte neu anmelden.', 401);
            }
            $statement = $this->pdo->prepare("UPDATE users SET password=? WHERE id=? AND auth_source='local' AND role=? AND password=?");
            try {
                $statement->bindValue(1, $nextHash, PDO::PARAM_STR);
                $statement->bindValue(2, $actor, PDO::PARAM_INT);
                $statement->bindValue(3, $expectedRole, PDO::PARAM_STR);
                $statement->bindValue(4, $current, PDO::PARAM_STR);
                if (! $statement->execute() || $statement->rowCount() !== 1) { throw new RuntimeException('Own password update was not confirmed.'); }
            } finally { $statement->closeCursor(); }
        }, fn (\Closure $transaction): mixed => $this->context->run('web:'.$actor, $auditReason, $transaction));
    }

    /** Explicit V5 operation; the legacy method above still refuses this profile. */
    public function replaceOwnLocalPasswordForAccountLifecycle(AccountLifecycleAuthority $actor,
        #[\SensitiveParameter] string $nextHash): void
    {
        $this->replaceOwnLocalPasswordForAccountLifecycleOperation($actor, $nextHash,
            'settings.php: own local password change');
    }

    public function revokeOwnLocalWebApiSessionsForAccountLifecycle(AccountLifecycleAuthority $actor,
        #[\SensitiveParameter] string $nextHash): void
    {
        $this->replaceOwnLocalPasswordForAccountLifecycleOperation($actor, $nextHash,
            'settings.php: revoke own local Web/API sessions');
    }

    private function replaceOwnLocalPasswordForAccountLifecycleOperation(AccountLifecycleAuthority $actor,
        #[\SensitiveParameter] string $nextHash, string $auditReason): void
    {
        if (! $this->hasGenerationBoundAccountAuthority() || $actor->source !== 'local'
            || ! in_array($actor->role, ['user', 'supervisor', 'admin'], true)
            || (password_get_info($nextHash)['algoName'] ?? 'unknown') !== 'bcrypt') {
            throw new LogicException('An admitted V5 own-local authority and bcrypt hash are required.');
        }
        $this->executeLocked(function () use ($actor, $nextHash): void {
            $this->assertLifecyclePrincipalLocked($actor);
            $current = att_one($this->pdo,
                'SELECT id,username,role,auth_source,password,account_status,auth_generation FROM users WHERE id=?', [$actor->id]);
            if ($current === null || ! $actor->matches($current)) {
                throw new \AttendanceError('Sitzung nicht mehr gültig. Bitte neu anmelden.', 401);
            }
            $replacement = $current;
            $replacement['password'] = $nextHash;
            $this->assertLocalRecoveryRetainedLocked($actor->id, $replacement);
            $statement = $this->pdo->prepare("UPDATE users SET password=? WHERE id=? AND auth_source='local' AND role=? AND password=? AND account_status='active' AND auth_generation=?");
            try {
                $statement->bindValue(1, $nextHash, PDO::PARAM_STR);
                $statement->bindValue(2, $actor->id, PDO::PARAM_INT);
                $statement->bindValue(3, $actor->role, PDO::PARAM_STR);
                $statement->bindValue(4, $current['password'], PDO::PARAM_STR);
                $statement->bindValue(5, $actor->generation, PDO::PARAM_INT);
                if (! $statement->execute() || $statement->rowCount() !== 1) {
                    throw new RuntimeException('Own password update was not confirmed.');
                }
            } finally { $statement->closeCursor(); }
        }, fn (\Closure $transaction): mixed => $this->context->run('web:'.$actor->id,
            $auditReason, $transaction), lifecycleReady: true);
    }

}
