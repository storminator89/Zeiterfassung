<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\Auth\AccountLifecycleWebIdentity;
use App\Accounts\AccountLifecycleAuthority;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\SQLiteConnection;
use InvalidArgumentException;
use LogicException;
use PDO;
use Throwable;

/** Existing framework encryption and writer lifecycle; no directory or account-status operation. */
final readonly class LegacyLocalWebLdapSessionRevocations implements LocalWebLdapSessionRevocations
{
    private const PURPOSE = 'chrono.ldap-session-revocation.v1';
    private const V5_PURPOSE = 'chrono.ldap-session-revocation.account-lifecycle-v5';
    private const LIFETIME = 600;

    /** @param Closure(): AttendanceWriter $writer Trusted operation-scoped resolver only. */
    public function __construct(private Factory $auth, private DatabaseManager $database,
        private Encrypter $encrypter, private Closure $writer,
        private ?AccountLifecycleWebIdentity $lifecycle = null) {}

    public function confirmations(array $identifiers): array
    {
        $actor = $this->localAdmin();
        $authority = $this->lifecycle?->capture($actor);
        if (! array_is_list($identifiers) || count($identifiers) > 10) {
            throw new InvalidArgumentException('Ungültige Verzeichnisauswahl.');
        }
        foreach ($identifiers as $identifier) {
            if (! is_int($identifier) || $identifier < 1) {
                throw new InvalidArgumentException('Ungültige Verzeichnisauswahl.');
            }
        }
        $connection = $this->database->connection('chrono');
        if (! $connection instanceof SQLiteConnection || $connection->transactionLevel() !== 0) {
            throw new LogicException('An idle admitted read connection is required.');
        }
        $pdo = $connection->getPdo();
        if ($pdo->inTransaction()) { throw new LogicException('An idle admitted read connection is required.'); }
        $actorId = (int) $actor->getKey();
        $actorVersion = hash('sha256', $actor->getAuthPassword());
        $targets = $connection->transaction(function () use ($connection, $pdo, $actorId, $actorVersion, $identifiers, $authority): array {
            if ($connection->getRawPdo() !== $pdo) { throw new LogicException('The read handle changed.'); }
            $current = $this->row($pdo, $actorId, $authority !== null);
            if ($current === false || ($authority !== null
                && (! $authority->matches($current) || ! AccountLifecycleAuthority::locallyLoginEligibleAdmin($current)))
                || $current['auth_source'] !== 'local' || ! is_string($current['password'])
                || $current['password'] === '' || ! hash_equals($actorVersion, hash('sha256', $current['password']))) {
                throw new AuthenticationException('Unauthenticated.', ['web']);
            }
            if ($current['role'] !== 'admin') {
                throw new AuthorizationException('Nur die lokale Verwaltung darf Verzeichnissitzungen widerrufen.');
            }
            $targets = [];
            foreach (array_unique($identifiers) as $identifier) {
                $target = $this->row($pdo, $identifier, $authority !== null);
                if ($target !== false && $target['auth_source'] === 'ldap' && $target['role'] === 'user'
                    && is_string($target['password']) && $target['password'] !== '') {
                    if ($authority !== null && (! in_array($target['account_status'], ['active', 'inactive', 'departed'], true)
                        || ! is_int($target['auth_generation']) || $target['auth_generation'] < 1)) {
                        throw new LogicException('Invalid retained account lifecycle.');
                    }
                    $targets[$identifier] = ['version' => hash('sha256', $target['password'])];
                    if ($authority !== null) {
                        $targets[$identifier] += ['target_status' => $target['account_status'],
                            'target_generation' => $target['auth_generation']];
                    }
                }
            }
            if ($connection->getRawPdo() !== $pdo) { throw new LogicException('The read handle changed.'); }
            return $targets;
        }, 1);
        // The snapshot is closed before ordinary Laravel encryption. No hashes
        // or fingerprint fields are exposed to the template, logs or session.
        $confirmations = [];
        foreach ($targets as $identifier => $target) {
            $ticket = [
                'purpose' => $authority === null ? self::PURPOSE : self::V5_PURPOSE,
                'actor' => $actorId, 'actor_version' => $actorVersion,
                'target' => $identifier, 'target_version' => $target['version'], 'expires_at' => time() + self::LIFETIME,
            ];
            if ($authority !== null) {
                $ticket += ['actor_generation' => $authority->generation,
                    'target_status' => $target['target_status'], 'target_generation' => $target['target_generation']];
            }
            $confirmations[$identifier] = $this->encrypter->encrypt(json_encode($ticket, JSON_THROW_ON_ERROR), false);
        }
        return $confirmations;
    }

    public function revoke(#[\SensitiveParameter] array $command): void
    {
        $actor = $this->localAdmin();
        $authority = $this->lifecycle?->capture($actor);
        if (count($command) !== count(self::COMMAND_FIELDS)
            || array_diff(array_keys($command), self::COMMAND_FIELDS) !== []
            || ($command['revoke_ldap_sessions'] ?? null) !== '1' || ($command['confirm'] ?? null) !== '1'
            || ! is_string($command['user_id'] ?? null)
            || preg_match('/\A[1-9][0-9]*\z/', $command['user_id']) !== 1
            || ($target = filter_var($command['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) === false
            || ! is_string($command['confirmation'] ?? null) || strlen($command['confirmation']) > 4096) {
            throw new InvalidArgumentException('Ungültige Verzeichnissitzungsaktion.');
        }
        $version = hash('sha256', $actor->getAuthPassword());
        try {
            $ticket = json_decode($this->encrypter->decrypt($command['confirmation'], false), true, 16, JSON_THROW_ON_ERROR);
            $fields = ['purpose', 'actor', 'actor_version', 'target', 'target_version', 'expires_at'];
            if ($authority !== null) {
                $fields = [...$fields, 'actor_generation', 'target_status', 'target_generation'];
            }
            if (! is_array($ticket) || count($ticket) !== count($fields)
                || array_diff(array_keys($ticket), $fields) !== []
                || $ticket['purpose'] !== ($authority === null ? self::PURPOSE : self::V5_PURPOSE)
                || ($authority !== null && ($ticket['actor_generation'] !== $authority->generation
                    || ! in_array($ticket['target_status'], ['active', 'inactive', 'departed'], true)
                    || ! is_int($ticket['target_generation']) || $ticket['target_generation'] < 1)) || $ticket['actor'] !== (int) $actor->getKey() || $ticket['target'] !== $target
                || ! is_string($ticket['actor_version']) || ! hash_equals($version, $ticket['actor_version'])
                || ! is_string($ticket['target_version']) || preg_match('/\A[a-f0-9]{64}\z/', $ticket['target_version']) !== 1
                || ! is_int($ticket['expires_at']) || $ticket['expires_at'] < time()
                || $ticket['expires_at'] > time() + self::LIFETIME) {
                throw new LogicException;
            }
        } catch (Throwable) {
            throw new InvalidArgumentException('Bestätigung ungültig oder abgelaufen. Bitte neu laden und bewusst erneut auswählen.');
        }
        // Actor/version and target-version CAS are rechecked inside the writer's
        // sole IMMEDIATE transaction. Replay after a committed rotation is stale.
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('An admitted operation-scoped LDAP session writer is required.');
        }
        try {
            $writer->revokeExistingLdapSessions((int) $actor->getKey(), $version,
                $command['user_id'], $ticket['target_version'], $authority,
                $ticket['target_status'] ?? null, $ticket['target_generation'] ?? null);
        } catch (\AttendanceError $error) {
            if ($error->status === 401) { throw new AuthenticationException('Unauthenticated.', ['web']); }
            throw $error;
        }
    }

    private function localAdmin(): LegacyUser
    {
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1 || $actor->auth_source !== 'local'
            || ! is_string($actor->getAuthPassword()) || $actor->getAuthPassword() === '') {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Nur die lokale Verwaltung darf Verzeichnissitzungen widerrufen.');
        }
        return $actor;
    }

    private function row(PDO $pdo, int $identifier, bool $accountLifecycleV5 = false): array|false
    {
        $statement = $pdo->prepare($accountLifecycleV5
            ? 'SELECT id,username,role,auth_source,password,account_status,auth_generation FROM users WHERE id=?'
            : 'SELECT role,auth_source,password FROM users WHERE id=?');
        $statement->execute([$identifier]);
        try { return $statement->fetch(PDO::FETCH_ASSOC); }
        finally { $statement->closeCursor(); }
    }
}
