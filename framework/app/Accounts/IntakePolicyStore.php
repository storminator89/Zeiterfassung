<?php

namespace App\Accounts;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use LogicException;
use PDO;
use UnexpectedValueException;

/**
 * Fixed policy operations on the caller's admitted PDO. Never opens a path,
 * begins/commits/retries a transaction, resolves a guard or provisions a user.
 * Locked methods require the existing trusted IMMEDIATE writer/audit scope;
 * PDO::inTransaction alone is not proof that the caller took that writer lock.
 */
final class IntakePolicyStore
{
    public function read(PDO $pdo, bool $accountLifecycleV5 = false): IntakePolicy
    {
        $this->requireTransaction($pdo);
        // Trusted operation composition only, never infer V5 from optional columns.
        $versions = $accountLifecycleV5 ? [1, 2, 3, 4, 5] : [1, 2, 3, 4];
        if ($pdo->query('SELECT version FROM app_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN) !== $versions) {
            throw new UnexpectedValueException('The explicit intake policy profile is required.');
        }
        $rows = $pdo->query('SELECT id,manual_enabled,ldap_sync_enabled,registration_enabled,revision,updated_at,updated_by FROM user_intake_policy ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1) {
            throw new UnexpectedValueException('The persisted singleton intake policy is required.');
        }

        return IntakePolicy::fromRow($rows[0]);
    }

    /** Recheck after slow transport; a changed revision also refuses still-enabled intake. */
    public function requireAllowedLocked(PDO $pdo, IntakeMethod $method, ?int $expectedRevision = null,
        bool $accountLifecycleV5 = false): IntakePolicy
    {
        if ($expectedRevision !== null && $expectedRevision < 1) {
            throw new InvalidArgumentException('Ungültige Richtlinienversion.');
        }
        $policy = $this->read($pdo, $accountLifecycleV5);
        if ($expectedRevision !== null && $policy->revision !== $expectedRevision) {
            throw new DomainException('Die Aufnahmewege wurden geändert. Bitte neu laden und erneut bewusst wählen.');
        }
        if (! $policy->allows($method)) {
            throw new AuthorizationException('Dieser Aufnahmeweg ist deaktiviert.');
        }

        return $policy;
    }

    /** Trusted actor ID only; the outer service/CSRF gate is still mandatory. */
    public function applyLocked(PDO $pdo, int $admin, IntakePolicyChange $change): IntakePolicy
    {
        $this->requireTransaction($pdo);
        $statement = $pdo->prepare('SELECT role,auth_source FROM users WHERE id=?');
        $statement->execute([$admin]);
        $actor = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        if ($admin < 1 || $actor === false || $actor['role'] !== 'admin' || $actor['auth_source'] !== 'local') {
            throw new AuthorizationException('Nur die lokale Verwaltung darf Aufnahmewege ändern.');
        }
        $policy = $this->read($pdo);
        if ($policy->revision !== $change->expectedRevision) {
            throw new DomainException('Die Aufnahmewege wurden geändert. Bitte neu laden und erneut bewusst wählen.');
        }
        if ($policy->manualEnabled === $change->manualEnabled
            && $policy->ldapSyncEnabled === $change->ldapSyncEnabled
            && $policy->registrationEnabled === $change->registrationEnabled) {
            return $policy;
        }
        if ($policy->revision === PHP_INT_MAX) {
            throw new DomainException('Die Richtlinienversion kann nicht weiter erhöht werden.');
        }
        $statement = $pdo->prepare('UPDATE user_intake_policy SET manual_enabled=?,ldap_sync_enabled=?,registration_enabled=?,revision=revision+1,updated_at=unixepoch(),updated_by=? WHERE id=1 AND revision=?');
        $statement->execute([(int) $change->manualEnabled, (int) $change->ldapSyncEnabled,
            (int) $change->registrationEnabled, $admin, $change->expectedRevision]);
        $changed = $statement->rowCount();
        $statement->closeCursor();
        if ($changed !== 1) {
            throw new DomainException('Die Änderung der Aufnahmewege wurde nicht bestätigt.');
        }

        return $this->read($pdo);
    }

    private function requireTransaction(PDO $pdo): void
    {
        if (! $pdo->inTransaction()) {
            throw new LogicException('The caller-owned admitted policy snapshot or writer transaction is required.');
        }
    }
}
