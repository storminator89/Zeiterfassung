<?php

namespace App\LegacyDatabase;

use Closure;
use InvalidArgumentException;
use RuntimeException;

/** Fixed opt-in operation; AttendanceWriter owns admission, lock, audit and commit. */
trait LdapSessionWriterOperations
{
    public function revokeExistingLdapSessions(int $admin, #[\SensitiveParameter] string $expectedWebVersion,
        mixed $userId, #[\SensitiveParameter] string $expectedTargetVersion,
        ?\App\Accounts\AccountLifecycleAuthority $lifecycleAuthority = null,
        ?string $expectedTargetStatus = null, ?int $expectedTargetGeneration = null): void
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $expectedTargetVersion) !== 1 || ! is_string($userId) || preg_match('/\A[1-9][0-9]*\z/', $userId) !== 1
            || ($target = filter_var($userId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) === false) {
            throw new InvalidArgumentException('Ungültiges Verzeichniskonto.');
        }

        if ($this->accountLifecycleV5
            ? ($lifecycleAuthority === null || ! in_array($expectedTargetStatus, ['active', 'inactive', 'departed'], true)
                || $expectedTargetGeneration === null || $expectedTargetGeneration < 1)
            : ($lifecycleAuthority !== null || $expectedTargetStatus !== null || $expectedTargetGeneration !== null)) {
            throw new InvalidArgumentException('The explicit LDAP session lifecycle context is required.');
        }
        $this->executeLocked(function () use ($admin, $expectedWebVersion, $target, $expectedTargetVersion,
            $lifecycleAuthority, $expectedTargetStatus, $expectedTargetGeneration): void {
            $this->assertExpectedWebPrincipal($admin, 'local', $expectedWebVersion, 'admin', $lifecycleAuthority);
            $user = \att_one($this->pdo, $this->accountLifecycleV5
                ? 'SELECT auth_source,role,password,account_status,auth_generation FROM users WHERE id=?'
                : 'SELECT auth_source,role,password FROM users WHERE id=?', [$target]);
            if ($user === null || $user['auth_source'] !== 'ldap' || $user['role'] !== 'user'
                || ! is_string($user['password']) || $user['password'] === '') {
                throw new InvalidArgumentException('Nur bestehende gewöhnliche Verzeichniskonten sind zulässig.');
            }

            if (! hash_equals($expectedTargetVersion, hash('sha256', $user['password']))
                || ($this->accountLifecycleV5 && ($user['account_status'] !== $expectedTargetStatus
                    || $user['auth_generation'] !== $expectedTargetGeneration))) {
                throw new \AttendanceError('Der Sitzungsstand wurde geändert. Bitte neu laden; diese Aktion nicht wiederholen.', 409);
            }

            // Deliberate local version rotation, NOT an LDAP password change,
            // account disablement or terminal revocation. Login itself stays read-only.
            $placeholder = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            if (! is_string($placeholder) || $placeholder === '' || hash_equals($user['password'], $placeholder)) {
                throw new RuntimeException('Die Sitzungsänderung wurde nicht bestätigt.');
            }
            $statement = $this->pdo->prepare("UPDATE users SET password=? WHERE id=? AND auth_source='ldap' AND role='user'");
            $statement->execute([$placeholder, $target]);
            $changed = $statement->rowCount();
            $statement->closeCursor();
            if ($changed !== 1) {
                throw new RuntimeException('Die Sitzungsänderung wurde nicht bestätigt.');
            }
            // Existing user audit trigger records actor/reason without credentials.
            // No session files, issued JWTs, directory settings/grants/history are read.
        }, fn (Closure $transaction): mixed => $this->context->run('web:'.$admin,
            'Explicit LDAP session revocation', $transaction),
            lifecycleReady: $this->accountLifecycleV5 && $lifecycleAuthority !== null);
    }
}
