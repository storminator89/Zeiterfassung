<?php

namespace App\LegacyDatabase;

use App\Accounts\AccountLifecycleAuthority;
use App\Chrono\AdminUserMutationResult;
use Closure;
use InvalidArgumentException;

/**
 * Fixed operations for AttendanceWriter only, not a standalone writer or PDO API.
 * Its existing executeLocked/Principal/AuditContext lifecycle remains the owner.
 */
trait ManagedUserWriterOperations
{
    public function editManagedUser(int $admin, #[\SensitiveParameter] string $expectedWebVersion,
        mixed $userId, mixed $username, mixed $email, mixed $role, #[\SensitiveParameter] mixed $password,
        mixed $department, mixed $supervisor, mixed $regularHours, mixed $carryIn,
        ?AccountLifecycleAuthority $lifecycleAuthority = null): AdminUserMutationResult
    {
        require_once dirname(__DIR__, 3).'/lib/admin_users.php';
        foreach ([$userId, $username, $email, $role, $password, $department, $supervisor, $regularHours, $carryIn] as $value) {
            if (! is_string($value)) {
                throw new InvalidArgumentException('Ungültige Benutzereingabe.');
            }
        }
        // Preserve native target/role validation before BEGIN; the write-sensitive
        // target, role count and assignment are checked under the one writer lock.
        \admin_assignment_role($role);
        $targetId = \admin_existing_user_id($userId);

        return $this->executeLocked(function () use ($admin, $expectedWebVersion, $targetId, $username,
            $email, $role, $password, $department, $supervisor, $regularHours, $carryIn, $lifecycleAuthority): AdminUserMutationResult {
            $this->assertExpectedWebPrincipal($admin, 'local', $expectedWebVersion, 'admin', $lifecycleAuthority);
            \admin_validate_user_fields($username, $email, $regularHours, $carryIn);
            $hash = $this->accountLifecycleV5 && ! empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;
            $this->requireManagedUserTargetLocked($targetId, $role, $hash, $username);
            $assignment = \validateUserAssignment($this->pdo, $role, $supervisor, $department, $targetId);

            // The original form treats an empty password (including string "0")
            // as unchanged. Never echo, persist in session, trim or return it.
            if (! empty($password)) {
                $hash ??= password_hash($password, PASSWORD_DEFAULT);
                $statement = $this->pdo->prepare('UPDATE users SET username = ?, email = ?, role = ?, password = ?, department_id = ?, supervisor_id = ?, regelarbeitszeit = ?, ueberstunden = ? WHERE id = ?');
                $statement->execute([$username, $email, $role, $hash, $assignment['department_id'],
                    $assignment['supervisor_id'], $regularHours, $carryIn, $targetId]);
            } else {
                $statement = $this->pdo->prepare('UPDATE users SET username = ?, email = ?, role = ?, department_id = ?, supervisor_id = ?, regelarbeitszeit = ?, ueberstunden = ? WHERE id = ?');
                $statement->execute([$username, $email, $role, $assignment['department_id'],
                    $assignment['supervisor_id'], $regularHours, $carryIn, $targetId]);
            }
            $statement->closeCursor();
            // No auth_source/token mutation, work-model replacement, audit insert
            // or extra commit. Existing audit triggers fire in this transaction.
            return new AdminUserMutationResult($targetId === $admin && ($role !== 'admin' || ! empty($password)));
        }, fn (Closure $transaction): mixed => $this->context->run('web:'.$admin, 'admin.php', $transaction), lifecycleReady: true);
    }

    public function deleteManagedUser(int $admin, #[\SensitiveParameter] string $expectedWebVersion,
        mixed $userId, ?AccountLifecycleAuthority $lifecycleAuthority = null): AdminUserMutationResult
    {
        require_once dirname(__DIR__, 3).'/lib/admin_users.php';
        if (! is_string($userId)) {
            throw new InvalidArgumentException('Ungültige Benutzereingabe.');
        }
        $targetId = \admin_existing_user_id($userId);

        return $this->executeLocked(function () use ($admin, $expectedWebVersion, $targetId, $lifecycleAuthority): AdminUserMutationResult {
            $this->assertExpectedWebPrincipal($admin, 'local', $expectedWebVersion, 'admin', $lifecycleAuthority);
            $this->requireManagedUserTargetLocked($targetId, null);
            // EXACT native retention predicate: any owned audit, including user
            // or work-model history/baselines, not only time-entry mutations.
            if (att_one($this->pdo, 'SELECT id FROM audit_log WHERE user_id=?', [$targetId])) {
                throw new \AttendanceError('Konten mit Änderungshistorie bleiben erhalten. Eine zentrale Kontodeaktivierung ist noch nicht verbunden; ein lokaler Passwortwechsel sperrt LDAP-Anmeldungen und Terminal-Zugänge nicht.', 409);
            }
            $statement = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
            $statement->execute([$targetId]);
            $statement->closeCursor();

            return new AdminUserMutationResult($targetId === $admin);
        }, fn (Closure $transaction): mixed => $this->context->run('web:'.$admin, 'admin.php', $transaction), lifecycleReady: true);
    }

    /** Root-approved Laravel recovery restrictions, in addition to unchanged native safeguards. */
    private function requireManagedUserTargetLocked(int $targetId, ?string $nextRole,
        #[\SensitiveParameter] ?string $nextPasswordHash = null, ?string $nextUsername = null): void
    {
        \admin_existing_user_guard_locked($this->pdo, $targetId, $nextRole);
        $target = att_one($this->pdo, 'SELECT role,auth_source FROM users WHERE id=?', [$targetId]);
        if ($target === null) {
            throw new InvalidArgumentException('Ungültiger Benutzer.');
        }
        // Roles are still assigned only by the existing explicit local-Admin
        // operation. Directory sync never promotes a role or converts its source;
        // a directory Admin never substitutes for the retained local recovery Admin.
        if ($this->accountLifecycleV5) {
            $replacement = null;
            if ($nextRole !== null) {
                $replacement = att_one($this->pdo, 'SELECT username,role,auth_source,password,account_status,auth_generation FROM users WHERE id=?', [$targetId]);
                $replacement['role'] = $nextRole;
                if ($nextUsername !== null) {
                    $replacement['username'] = $nextUsername;
                }
                if ($nextPasswordHash !== null) {
                    $replacement['password'] = $nextPasswordHash;
                }
            }
            $this->assertLocalRecoveryRetainedLocked($targetId, $replacement);

            return;
        }
        if ($target['role'] === 'admin' && $target['auth_source'] === 'local' && $nextRole !== 'admin') {
            $statement = $this->pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND auth_source='local'");
            $count = (int) $statement->fetchColumn();
            $statement->closeCursor();
            if ($count <= 1) {
                throw new InvalidArgumentException('Der letzte lokale Administrator kann nicht herabgestuft oder gelöscht werden.');
            }
        }
    }
}
