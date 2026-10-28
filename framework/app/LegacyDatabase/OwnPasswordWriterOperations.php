<?php

namespace App\LegacyDatabase;

use LogicException;
use PDO;
use RuntimeException;

/** Fixed own-account password replacement; no target, source or SQL callback from a form. */
trait OwnPasswordWriterOperations
{
    public function replaceOwnLocalPassword(int $actor, string $expectedRole,
        #[\SensitiveParameter] string $expectedVersion, #[\SensitiveParameter] string $nextHash): void
    {
        // This old-profile operation must never turn into a V5 compatibility bypass.
        if ($this->accountLifecycleV5) { throw new LogicException('Own password changes are not admitted for V5.'); }
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
        }, fn (\Closure $transaction): mixed => $this->context->run('web:'.$actor, 'settings.php: own local password change', $transaction));
    }
}
