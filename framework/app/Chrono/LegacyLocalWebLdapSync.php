<?php

namespace App\Chrono;

use App\Accounts\IntakeMethod;
use App\Accounts\IntakePolicyStore;
use App\Auth\LegacyUser;
use App\Ldap\AdmittedLdapSettingsCipher;
use App\Ldap\LdapSyncTransport;
use App\Ldap\MaterializedLdapImport;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\SQLiteConnection;
use InvalidArgumentException;
use LogicException;
use PDO;
use RuntimeException;
use Throwable;

/** No default binding/route. All sources are trusted lazy operation composition. */
final readonly class LegacyLocalWebLdapSync implements LocalWebLdapSync
{
    /**
     * @param Closure(): LdapSyncTransport $transport
     * @param Closure(): AdmittedLdapSettingsCipher $cipher
     * @param Closure(): AttendanceWriter $writer
     */
    public function __construct(
        private Factory $auth,
        private DatabaseManager $database,
        private IntakePolicyStore $policies,
        private Closure $transport,
        private Closure $cipher,
        private Closure $writer,
    ) {}

    public function synchronize(#[\SensitiveParameter] array $command): array
    {
        try {
            $authenticated = $this->auth->guard('web')->user();
        } catch (Throwable) {
            throw new RuntimeException('LDAP synchronization identity could not be confirmed.');
        }
        if (! $authenticated instanceof LegacyUser || (int) $authenticated->getKey() < 1
            || $authenticated->auth_source !== 'local' || ! is_string($authenticated->getAuthPassword())
            || ! $authenticated->getAuthPassword()) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($authenticated->role !== 'admin') {
            throw new AuthorizationException('Only local administration may synchronize LDAP.');
        }
        if (count($command) !== count(self::COMMAND_FIELDS)
            || array_diff(array_keys($command), self::COMMAND_FIELDS) !== []
            || ($command['sync_ldap'] ?? null) !== '1') {
            throw new InvalidArgumentException('Invalid LDAP synchronization command.');
        }
        foreach (self::COMMAND_FIELDS as $field) {
            if (! is_string($command[$field])) {
                throw new InvalidArgumentException('Invalid LDAP synchronization command.');
            }
        }
        if ($command['ldap_user'] === '' || trim($command['ldap_user']) !== $command['ldap_user']
            || str_contains($command['ldap_user'], "\0") || strlen($command['ldap_user']) > 4096
            || $command['ldap_pass'] === '' || strlen($command['ldap_pass']) > 65536) {
            throw new InvalidArgumentException('Invalid LDAP synchronization command.');
        }

        try {
            [$user, $version, $revision] = $this->authorizeSnapshot($authenticated);
            // The read transaction is CLOSED before factories, network or keys.
            $transport = $this->resolve($this->transport, LdapSyncTransport::class);
            try {
                $target = $transport->target();
            } catch (Throwable) {
                throw new RuntimeException('LDAP synchronization target admission refused.');
            }
            if ($command['ldap_host'] !== $target->uri || $command['ldap_port'] !== $target->port
                || $command['ldap_base_dn'] !== $target->baseDn) {
                // Fields may repeat the displayed target, never select another.
                throw new InvalidArgumentException('The submitted LDAP target is not admitted.');
            }
            try {
                $entries = $transport->collect($command['ldap_user'], $command['ldap_pass']);
            } catch (Throwable) {
                throw new RuntimeException('LDAP synchronization collection was not confirmed.');
            }
            // Successful collection includes result release/unbind; only now keys.
            $cipher = $this->resolve($this->cipher, AdmittedLdapSettingsCipher::class);
            $stage = MaterializedLdapImport::fromArray([
                'entries' => $entries, 'host' => $target->uri, 'port' => $target->port,
                'bind_user' => $command['ldap_user'], 'ciphertext' => $cipher->encrypt($command['ldap_pass']),
                'base_dn' => $target->baseDn,
            ]);
            unset($command['ldap_pass'], $entries);
            $writer = $this->resolve($this->writer, AttendanceWriter::class);

            return $writer->importLdapUsersWithIntakePolicy($user, $version, $revision, $stage);
        } catch (AuthenticationException|AuthorizationException|InvalidArgumentException|DomainException $error) {
            throw $error;
        } catch (\AttendanceError $error) {
            // Existing fixed 401 principal/version refusal, not a new session engine.
            throw $error;
        } catch (Throwable) {
            // No SQL, target, staff data, root-source error or previous exception.
            throw new RuntimeException('LDAP synchronization result could not be confirmed.');
        }
    }

    /** Fixed read snapshot, never a public PDO/callback or transaction around LDAP. */
    private function authorizeSnapshot(#[\SensitiveParameter] LegacyUser $authenticated): array
    {
        try {
            $connection = $this->database->connection('chrono');
            if (! $connection instanceof SQLiteConnection || $connection->transactionLevel() !== 0) {
                throw new LogicException;
            }
            $pdo = $connection->getPdo();
            if ($pdo->inTransaction()) {
                throw new LogicException;
            }
        } catch (Throwable) {
            throw new RuntimeException('LDAP synchronization read admission refused.');
        }
        $user = (int) $authenticated->getKey();
        $version = hash('sha256', $authenticated->getAuthPassword());

        return $connection->transaction(function () use ($connection, $pdo, $user, $version): array {
            if ($connection->getRawPdo() !== $pdo) {
                throw new LogicException;
            }
            $statement = $pdo->prepare('SELECT role,auth_source,password FROM users WHERE id=?');
            $statement->execute([$user]);
            $actor = $statement->fetch(PDO::FETCH_ASSOC);
            $statement->closeCursor();
            if ($actor === false || $actor['auth_source'] !== 'local' || ! is_string($actor['password'])
                || ! $actor['password'] || ! hash_equals($version, hash('sha256', $actor['password']))) {
                throw new AuthenticationException('Unauthenticated.', ['web']);
            }
            if ($actor['role'] !== 'admin') {
                throw new AuthorizationException('Only current local administration may synchronize LDAP.');
            }
            $policy = $this->policies->read($pdo);
            if (! $policy->allows(IntakeMethod::LdapSync)) {
                throw new AuthorizationException('LDAP synchronization intake is disabled.');
            }
            if ($connection->getRawPdo() !== $pdo) {
                throw new LogicException;
            }

            return [$user, $version, $policy->revision];
        }, 1);
    }

    /** Fixed internal factories only; no source diagnostics, even expected-error subclasses. */
    private function resolve(#[\SensitiveParameter] Closure $source, string $expected): object
    {
        try {
            $object = $source();
            if (! $object instanceof $expected) {
                throw new LogicException;
            }

            return $object;
        } catch (Throwable) {
            throw new RuntimeException('LDAP synchronization operation source admission refused.');
        }
    }

    public function __debugInfo(): array
    {
        return ['service' => 'explicit local Admin LDAP sync operation'];
    }

    public function __serialize(): array
    {
        throw new LogicException('LDAP sync operation sources must not be serialized.');
    }
}
