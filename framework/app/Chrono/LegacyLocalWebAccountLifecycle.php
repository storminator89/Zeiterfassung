<?php

namespace App\Chrono;

use App\Accounts\AccountLifecycleAuthority;
use App\Accounts\AccountLifecycleChange;
use App\Accounts\AccountLifecycleTransitionResult;
use App\Auth\AccountLifecycleWebIdentity;
use App\Auth\LegacyUser;
use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\RuntimeDatabase;
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

/** Explicit component composition only; never opens a Runtime source or activates HTTP. */
final readonly class LegacyLocalWebAccountLifecycle implements LocalWebAccountLifecycle
{
    private const PURPOSE = 'chrono.account-lifecycle.v5';
    private const LIFETIME = 600;

    /** @param Closure(): AttendanceWriter $writer Fresh operation-scoped resolver. */
    public function __construct(private Factory $auth, private DatabaseManager $database,
        private Encrypter $encrypter, private Closure $writer,
        private AccountLifecycleWebIdentity $identity, private RuntimeDatabase $runtime) {}

    public function forUsers(array $identifiers): array
    {
        [$actor, $authority] = $this->localAdmin();
        if (! array_is_list($identifiers) || count($identifiers) > 10) {
            throw new InvalidArgumentException('Ungültige Kontoauswahl.');
        }
        foreach ($identifiers as $identifier) {
            if (! is_int($identifier) || $identifier < 1) { throw new InvalidArgumentException('Ungültige Kontoauswahl.'); }
        }
        $connection = $this->database->connection('chrono');
        if (! $connection instanceof SQLiteConnection || $connection->transactionLevel() !== 0) {
            throw new LogicException('An idle admitted read connection is required.');
        }
        $pdo = $connection->getPdo();
        if ($pdo->inTransaction()) { throw new LogicException('An idle admitted read connection is required.'); }
        $targets = $connection->transaction(function () use ($connection, $pdo, $authority, $identifiers): array {
            if ($connection->getRawPdo() !== $pdo) { throw new LogicException('The read handle changed.'); }
            $current = $this->row($pdo, $authority->id);
            if ($current === false || ! $authority->matches($current)
                || ! AccountLifecycleAuthority::locallyLoginEligibleAdmin($current)) {
                throw new AuthenticationException('Unauthenticated.', ['web']);
            }
            $targets = [];
            foreach (array_unique($identifiers) as $identifier) {
                $target = $this->row($pdo, $identifier);
                if ($target === false) { continue; }
                if (! in_array($target['account_status'], ['active', 'inactive', 'departed'], true)
                    || ! is_int($target['auth_generation']) || $target['auth_generation'] < 1) {
                    throw new LogicException('Invalid retained account lifecycle.');
                }
                // Explicit display projection only; no password/source/hash/model escapes.
                $targets[$identifier] = ['username' => $target['username'],
                    'status' => $target['account_status'], 'generation' => $target['auth_generation']];
            }
            if ($connection->getRawPdo() !== $pdo) { throw new LogicException('The read handle changed.'); }
            return $targets;
        }, 1);
        $version = hash('sha256', $actor->getAuthPassword());
        foreach ($targets as $identifier => &$target) {
            $target['confirmations'] = [];
            if ($target['generation'] === PHP_INT_MAX) { continue; }
            foreach (['active', 'inactive', 'departed'] as $next) {
                if ($next === $target['status']) { continue; }
                $ticket = ['purpose' => self::PURPOSE, 'actor' => $authority->id,
                    'actor_version' => $version, 'actor_generation' => $authority->generation,
                    'target' => $identifier, 'expected_status' => $target['status'],
                    'expected_generation' => $target['generation'], 'next_status' => $next,
                    'expires_at' => time() + self::LIFETIME];
                $target['confirmations'][$next] = $this->encrypter->encrypt(json_encode($ticket, JSON_THROW_ON_ERROR), false);
            }
        }
        unset($target);
        return $targets;
    }

    public function change(#[\SensitiveParameter] array $command): AccountLifecycleTransitionResult
    {
        [$actor, $authority] = $this->localAdmin();
        if (count($command) !== count(self::COMMAND_FIELDS)
            || array_diff(array_keys($command), self::COMMAND_FIELDS) !== []
            || ($command['change_account_lifecycle'] ?? null) !== '1'
            || ! is_string($command['user_id'] ?? null)
            || preg_match('/\A[1-9][0-9]*\z/', $command['user_id']) !== 1
            || ($target = filter_var($command['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) === false
            || ! is_string($command['confirmation'] ?? null) || strlen($command['confirmation']) > 4096
            || ! is_string($command['reason'] ?? null)) {
            throw new InvalidArgumentException('Ungültige Kontostatusaktion.');
        }
        try {
            $ticket = json_decode($this->encrypter->decrypt($command['confirmation'], false), true, 16, JSON_THROW_ON_ERROR);
            $fields = ['purpose', 'actor', 'actor_version', 'actor_generation', 'target',
                'expected_status', 'expected_generation', 'next_status', 'expires_at'];
            $now = time();
            if (! is_array($ticket) || count($ticket) !== count($fields)
                || array_diff(array_keys($ticket), $fields) !== [] || $ticket['purpose'] !== self::PURPOSE
                || $ticket['actor'] !== $authority->id || $ticket['actor_generation'] !== $authority->generation
                || ! is_string($ticket['actor_version'])
                || ! hash_equals(hash('sha256', $actor->getAuthPassword()), $ticket['actor_version'])
                || $ticket['target'] !== $target || ! is_int($ticket['expected_generation'])
                || ! is_string($ticket['expected_status']) || ! is_string($ticket['next_status'])
                || ! is_int($ticket['expires_at']) || $ticket['expires_at'] <= $now
                || $ticket['expires_at'] > $now + self::LIFETIME) { throw new LogicException; }
        } catch (Throwable) {
            throw new InvalidArgumentException('Bestätigung ungültig oder abgelaufen. Bitte neu laden und bewusst erneut auswählen.');
        }
        // The existing value object validates states, monotone range and reason
        // before resolving a writer. Target revision and actor are checked again
        // inside the existing sole lock; no read-to-write transaction upgrade.
        $change = new AccountLifecycleChange($target, $ticket['expected_status'],
            $ticket['expected_generation'], $ticket['next_status'], $command['reason']);
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) { throw new LogicException('An admitted lifecycle writer is required.'); }
        try { return $writer->changeAccountLifecycle($authority, $change); }
        catch (\AttendanceError $error) {
            if ($error->status === 401) { throw new AuthenticationException('Unauthenticated.', ['web']); }
            throw $error;
        }
    }

    private function localAdmin(): array
    {
        if (! $this->runtime->isAccountLifecycleV5() || ! $this->identity->requiresGeneration()) {
            throw new LogicException('The separate V5 account lifecycle profile is required.');
        }
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1 || $actor->auth_source !== 'local'
            || ! is_string($actor->getAuthPassword()) || $actor->getAuthPassword() === '') {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($actor->role !== 'admin') { throw new AuthorizationException('Nur die lokale Verwaltung darf den Kontostatus ändern.'); }
        $authority = $this->identity->capture($actor);
        if ($authority === null || ! AccountLifecycleAuthority::locallyLoginEligibleAdmin($actor->getAttributes())) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        return [$actor, $authority];
    }

    private function row(PDO $pdo, int $identifier): array|false
    {
        $statement = $pdo->prepare('SELECT id,username,role,auth_source,password,account_status,auth_generation FROM users WHERE id=?');
        try { $statement->execute([$identifier]); return $statement->fetch(PDO::FETCH_ASSOC); }
        finally { $statement->closeCursor(); }
    }
}
