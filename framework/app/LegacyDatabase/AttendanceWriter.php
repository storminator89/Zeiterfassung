<?php

namespace App\LegacyDatabase;

use App\Terminal\AdmittedTerminalKeys;
use App\Terminal\LegacyTerminalRequest;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\SQLiteConnection;
use LogicException;
use PDO;
use Pdo\Sqlite;
use RuntimeException;
use Throwable;

/** Opt-in domain bridge, not a path opener, route, or production connection. */
final class AttendanceWriter
{
    use ManagedUserWriterOperations;
    use LdapSessionWriterOperations;
    use AccountLifecycleWriterOperations;
    use AccountLifecycleTerminalWriterOperations;
    use OwnPasswordWriterOperations;
    use ApiManagementWriterOperations;

    private readonly SQLiteConnection $connection;
    private bool $unusable = false;

    // The caller must first admit/preflight/adopt the known-v2 private file.
    // Own a bare stock connection: no shared manager, listeners, read replica,
    // transaction callbacks, or reconnect hooks can replace this writer's PDO.
    public function __construct(
        private readonly PDO $pdo,
        array $configuration,
        private readonly AuditContext $context,
        AuditFunctions $functions,
        private readonly bool $intakePolicyV4 = false,
        private readonly ?int $loginBudgetSchemaVersion = null,
        private readonly bool $accountLifecycleV5 = false,
    ) {
        if (! $pdo instanceof Sqlite || $pdo->inTransaction()
            || ($configuration['driver'] ?? null) !== 'sqlite'
            || ($configuration['transaction_mode'] ?? null) !== 'IMMEDIATE'
            || ($configuration['busy_timeout'] ?? null) !== 5000
            || $pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new LogicException('A dedicated admitted PHP 8.5 SQLite writer with IMMEDIATE/5000 is required.');
        }
        $databases = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
        if (count($databases) !== 1 || $databases[0]['name'] !== 'main'
            || $databases[0]['file'] !== ($configuration['database'] ?? null)
            || ! in_array($pdo->query('PRAGMA journal_mode')->fetchColumn(), ['delete', 'truncate', 'persist'], true)
            || (int) $pdo->query('PRAGMA busy_timeout')->fetchColumn() !== 5000
            || (int) $pdo->query('PRAGMA query_only')->fetchColumn() !== 0) {
            throw new LogicException('Only the admitted rollback-journal file and its existing settings are supported.');
        }

        $functions->register($pdo);
        $this->connection = new SQLiteConnection($pdo, $configuration['database'], '', $configuration);
        $this->connection->setReconnector(static fn () => throw new RuntimeException('A writer must never reconnect; admit a fresh operation scope instead.'));
        require_once dirname(__DIR__, 3).'/lib/attendance.php';
        $this->assertIntakeProfile();
    }

    /** Anonymous fixed quota operation; returns limited only AFTER confirmed COMMIT. */
    public function consumeLoginBudget(string $username, string $remoteAddress): bool
    {
        try {
            return $this->executeLocked(function () use ($username, $remoteAddress): bool {
                $this->assertIntakeProfileLocked(requireV4: true);
                $this->assertLoginBudgetSchemaCookie();
                $limited = (new \App\Api\SharedSqliteLoginBudgetStore)->consumeLocked(
                    $this->pdo, $username, $remoteAddress, time(),
                )->limited;
                $this->assertLoginBudgetSchemaCookie();
                $this->assertIntakeProfileLocked(requireV4: true);

                return $limited;
            }, lifecycleReady: $this->accountLifecycleV5);
        } catch (Throwable) {
            // Never a confirmed 429/allow result, auth failure, retry or fallback.
            throw new RuntimeException('Login budget commit was not confirmed.');
        }
    }

    private function assertLoginBudgetSchemaCookie(): void
    {
        // Only RuntimeDatabase supplies this immutable admitted cookie. The
        // active PDO's current value alone is not an admission capability.
        if ($this->loginBudgetSchemaVersion === null || $this->loginBudgetSchemaVersion < 1) {
            throw new LogicException('An admitted login-budget schema cookie is required.');
        }
        $statement = $this->pdo->query('PRAGMA main.schema_version');
        if ($statement === false) {
            throw new RuntimeException;
        }
        try {
            if ($statement->fetchColumn() !== $this->loginBudgetSchemaVersion
                || $statement->fetchColumn() !== false) {
                throw new RuntimeException;
            }
        } finally {
            if (! $statement->closeCursor()) {
                throw new RuntimeException;
            }
        }
    }

    public function transition(int $user, string $source, string $eventId, string $action, bool $confirm = false, ?int $expectedRecord = null, bool $requireExpectedRecord = false, ?string $expectedWebSource = null, #[\SensitiveParameter] ?string $expectedWebVersion = null, ?array $startMetadata = null): array
    {
        return $this->executeLocked(
            function () use ($user, $source, $eventId, $action, $confirm, $expectedRecord, $requireExpectedRecord, $expectedWebSource, $expectedWebVersion, $startMetadata): array {
                $this->assertExpectedWebPrincipal($user, $expectedWebSource, $expectedWebVersion);

                return attendance_transition_locked($this->pdo, $user, $source, $eventId, $action, $confirm, $expectedRecord, null, $requireExpectedRecord, $startMetadata);
            },
            fn (Closure $transaction): mixed => $this->context->run($source.':user:'.$user, 'Stempelereignis '.$action.' / '.$eventId, $transaction),
        );
    }

    /** Same captured PDO for the transport's pre-body check and its operation. */
    public function terminalRequireEnabled(): void
    {
        $this->assertIdle();
        require_once dirname(__DIR__, 3).'/lib/terminal_core.php';
        attendance_require_enabled($this->pdo);
        $this->assertIdle();
    }

    /** Native device/grant identity only; no web session, actor or SQL callback. */
    public function terminal(LegacyTerminalRequest $request, AdmittedTerminalKeys $keys): array
    {
        if ($this->accountLifecycleV5) {
            return $this->terminalV5($request, $keys);
        }
        // Match terminal_handle's repeated module check. Never wrap the entire
        // request: native nonce, limits and identify writes must autocommit first.
        $this->terminalRequireEnabled();
        $input = $request->input;
        if (($input['op'] ?? '') === 'pair') {
            terminal_limit($this->pdo, 'pair:'.$request->remoteAddress, 20);
            $device = $this->executeLocked(fn (): array => terminal_pair_locked($this->pdo, $input));

            // Pairing has committed. Key failure remains an unconfirmed failure,
            // not a rollback or retry. Do not imitate native invalid post-COMMIT
            // ROLLBACK masking; the transport still returns the generic 503 shape.
            $response = terminal_pair_response($device, fn (): string => $keys->deviceSecret());
            $this->assertIdle();

            return $response;
        }

        $device = terminal_auth_core($this->pdo, $request->body, [
            'HTTP_X_TERMINAL_ID' => $request->terminalId,
            'HTTP_X_TERMINAL_TIME' => $request->terminalTime,
            'HTTP_X_TERMINAL_NONCE' => $request->terminalNonce,
            'HTTP_X_TERMINAL_SIGNATURE' => $request->terminalSignature,
        ], fn (): string => $keys->deviceSecret());
        $this->assertIdle();
        if (($input['op'] ?? '') === 'identify') {
            $response = terminal_identify_core($this->pdo, $device, $input, fn (): string => $keys->cardSecret());
            $this->assertIdle();

            return $response;
        }

        return $this->executeLocked(fn (): array => terminal_dispatch_locked(
            $this->pdo, $device, $input,
            fn (string $actor, string $reason, Closure $operation): mixed => $this->context->run($actor, $reason, $operation),
        ));
    }

    /** Fixed owner-only terminal transition; uses the existing single-attempt lifecycle. */
    public function withdrawCorrection(int $user, string $role, #[\SensitiveParameter] string $version, mixed $id, mixed $reason, string $expectedWebSource = 'local'): void
    {
        require_once dirname(__DIR__, 3).'/lib/correction_withdrawal.php';
        $this->executeLocked(function () use ($user, $role, $version, $id, $reason, $expectedWebSource): void {
            $this->assertExpectedWebPrincipal($user, $expectedWebSource, $version, $role);
            correction_withdraw_locked($this->pdo, $user, $role, $version, $id, $reason,
                fn (string $auditReason, Closure $operation): mixed => $this->context->run('web:'.$user, $auditReason, $operation), $expectedWebSource);
        });
    }

    /** Trusted server identity/role only; never supplied by correction command data. */
    public function correction(int $user, string $role, array $command, ?string $expectedWebSource = null, #[\SensitiveParameter] ?string $expectedWebVersion = null): void
    {
        require_once dirname(__DIR__, 3).'/lib/corrections.php';

        $this->executeLocked(function () use ($user, $role, $command, $expectedWebSource, $expectedWebVersion): void {
            // New-profile correction privileges use the same-lock role, never a
            // pre-demotion admin role. Null/null retains the exact old path.
            $this->assertExpectedWebPrincipal($user, $expectedWebSource, $expectedWebVersion, $role);
            correction_apply_locked($this->pdo, $user, $role, $command,
                fn (string $reason, Closure $operation): mixed => $this->context->run('web:'.$user, $reason, $operation),
            );
        });
    }

    /** One fixed Admin identity check; never infer a V5 actor from current-active alone. */
    private function assertAdminOperationAuthorityLocked(int $user, ?\App\Accounts\AccountLifecycleAuthority $authority,
        ?string $role = null): void
    {
        if (! $this->accountLifecycleV5) {
            if ($authority !== null) { throw new LogicException('V5 Admin authority cannot target an older account profile.'); }
            return;
        }
        if ($authority === null || $authority->id !== $user || $authority->source !== 'local' || $authority->role !== 'admin'
            || ($role !== null && $role !== $authority->role)) {
            throw new \AttendanceError('Sitzung nicht mehr gültig. Bitte neu anmelden.', 401);
        }
        $this->assertLifecyclePrincipalLocked($authority, localAdmin: true);
    }

    /** Existing business administration only; security administration stays local. */
    private function assertBusinessAdminAuthorityLocked(int $user,
        ?\App\Accounts\AccountLifecycleAuthority $authority, ?array $webContext): void
    {
        if ($webContext !== null) {
            if (array_keys($webContext) !== [0, 1]
                || ! in_array($webContext[0], ['local', 'ldap'], true)
                || ! is_string($webContext[1]) || preg_match('/\A[a-f0-9]{64}\z/', $webContext[1]) !== 1) {
                throw new \AttendanceError('Sitzung nicht mehr gültig. Bitte neu anmelden.', 401);
            }
            // Only the verified existing-account provider supplies this source/hash.
            // Current role/source/hash and V5 generation are checked under this lock.
            $this->assertExpectedWebPrincipal($user, $webContext[0], $webContext[1], 'admin', $authority);
            return;
        }
        $this->assertAdminOperationAuthorityLocked($user, $authority);
        $actor = att_one($this->pdo, 'SELECT role,auth_source FROM users WHERE id=?', [$user]);
        if ($user < 1 || $actor === null || $actor['role'] !== 'admin' || $actor['auth_source'] !== 'local') {
            throw new AuthorizationException('Nur die aktuelle Verwaltung darf diese Facheinstellung ändern.');
        }
    }

    /** Trusted admin identity/role only; command user_id is the model target. */
    public function workModel(int $user, string $role, array $command,
        ?\App\Accounts\AccountLifecycleAuthority $lifecycleAuthority = null, ?array $webContext = null): void
    {
        if ($role !== 'admin') {
            throw new AuthorizationException('Nur die aktuelle Verwaltung darf Arbeitszeitmodelle ändern.');
        }
        require_once dirname(__DIR__, 3).'/lib/practice.php';

        $this->executeLocked(function () use ($user, $role, $command, $lifecycleAuthority, $webContext): void {
            $this->assertBusinessAdminAuthorityLocked($user, $lifecycleAuthority, $webContext);
            practice_work_model_apply_locked($this->pdo, $user, $role, $command,
                fn (string $reason, Closure $operation): mixed => $this->context->run('web:'.$user, $reason, $operation),
            );
        }, lifecycleReady: $this->accountLifecycleV5 && $lifecycleAuthority !== null);
    }

    /** One fixed Admin operation; no caller-supplied role, PDO or SQL callback. */
    public function pauseSettings(int $user, mixed $hoursThresholds, mixed $minimumPauses,
        ?\App\Accounts\AccountLifecycleAuthority $lifecycleAuthority = null, ?array $webContext = null): void
    {
        require_once dirname(__DIR__, 3).'/lib/pause_settings.php';
        // Exact native complete-list validation before any DELETE/INSERT or transaction.
        $rules = \validatePauseSettings($hoursThresholds, $minimumPauses);

        $this->executeLocked(function () use ($user, $rules, $lifecycleAuthority, $webContext): void {
            $this->assertBusinessAdminAuthorityLocked($user, $lifecycleAuthority, $webContext);
            // Re-authorize under the existing IMMEDIATE writer lock, not against
            // a request role or an earlier rendered/refreshed guard snapshot.
            $actor = att_one($this->pdo, 'SELECT role,auth_source FROM users WHERE id=?', [$user]);
            if ($user < 1 || $actor === null || $actor['role'] !== 'admin' || ! in_array($actor['auth_source'], ['local', 'ldap'], true)) {
                throw new AuthorizationException('Nur die Verwaltung darf Pauseneinstellungen ändern.');
            }

            $statement = $this->pdo->prepare('DELETE FROM pause_settings');
            $statement->execute();
            $statement->closeCursor();
            $statement = $this->pdo->prepare('INSERT INTO pause_settings (hours_threshold, minimum_pause) VALUES (?, ?)');
            foreach ($rules as $rule) {
                $statement->execute($rule);
                $statement->closeCursor();
            }
            // The existing transaction shell commits the complete list once.
            // This native table has no audit trigger; do not invent an audit claim.
        }, lifecycleReady: $this->accountLifecycleV5 && $lifecycleAuthority !== null);
    }

    /** Fixed department operations; native wrapper transactions must not nest here. */
    public function department(int $user, string $operation, mixed $departmentId, mixed $departmentName,
        ?\App\Accounts\AccountLifecycleAuthority $lifecycleAuthority = null, ?array $webContext = null): bool
    {
        require_once dirname(__DIR__, 3).'/lib/departments.php';
        if (! in_array($operation, ['create', 'rename', 'delete'], true)) {
            throw new \InvalidArgumentException('Unbekannte Abteilungsaktion.');
        }

        // Native validation remains before BEGIN; do not trim or coerce form values.
        if ($operation === 'create' && \department_create_name_missing($departmentName)) {
            return false;
        }
        $targetId = $operation === 'delete' ? \department_delete_id($departmentId) : $departmentId;

        return $this->executeLocked(function () use ($user, $operation, $targetId, $departmentName, $lifecycleAuthority, $webContext): bool {
            $this->assertBusinessAdminAuthorityLocked($user, $lifecycleAuthority, $webContext);
            $actor = att_one($this->pdo, 'SELECT role,auth_source FROM users WHERE id=?', [$user]);
            if ($user < 1 || $actor === null || $actor['role'] !== 'admin' || ! in_array($actor['auth_source'], ['local', 'ldap'], true)) {
                throw new AuthorizationException('Nur die Verwaltung darf Abteilungen ändern.');
            }

            match ($operation) {
                'create' => \department_create_core($this->pdo, $departmentName),
                'rename' => \department_rename_core($this->pdo, $targetId, $departmentName),
                'delete' => \department_delete_locked($this->pdo, $targetId),
            };

            return true;
        }, lifecycleReady: $this->accountLifecycleV5 && $lifecycleAuthority !== null);
    }

    /** Existing create only; no request actor, auth source, token or SQL callback. */
    public function createLocalUser(int $user, mixed $username, mixed $password, mixed $email, mixed $role,
        mixed $department, mixed $supervisor, mixed $regularHours, mixed $carryIn,
        ?int $expectedPolicyRevision = null, #[\SensitiveParameter] ?string $expectedWebVersion = null,
        ?\App\Accounts\AccountLifecycleAuthority $lifecycleAuthority = null): bool
    {
        // Exact native required-field semantics, without trim/default/coercion.
        if (empty($username) || empty($password) || empty($email) || empty($role)
            || empty($department) || empty($regularHours)) {
            return false;
        }
        // HTTP form fields are raw strings. Refuse malformed arrays/objects rather
        // than letting password_hash/PDO coerce them or emit technical diagnostics.
        foreach ([$username, $password, $email, $role, $department, $supervisor, $regularHours, $carryIn] as $value) {
            if ($value !== null && ! is_string($value)) {
                throw new \InvalidArgumentException('Ungültige Benutzereingabe.');
            }
        }
        require_once dirname(__DIR__, 3).'/lib/admin_users.php';

        return $this->executeLocked(function () use ($user, $username, $password, $email, $role,
            $department, $supervisor, $regularHours, $carryIn, $expectedPolicyRevision, $expectedWebVersion, $lifecycleAuthority): bool {
            $this->requireIntakeLocked(\App\Accounts\IntakeMethod::Manual, $expectedPolicyRevision, $user, $expectedWebVersion, $lifecycleAuthority);
            $actor = att_one($this->pdo, 'SELECT role,auth_source FROM users WHERE id=?', [$user]);
            if ($user < 1 || $actor === null || $actor['role'] !== 'admin' || $actor['auth_source'] !== 'local') {
                throw new AuthorizationException('Nur die Verwaltung darf Benutzer erstellen.');
            }
            \admin_validate_user_fields($username, $email, $regularHours, $carryIn);
            // Existing exact role/department/supervisor/chain validation on this
            // same locked PDO. Never call the native raw-BEGIN wrapper here.
            $assignment = \validateUserAssignment($this->pdo, $role, $supervisor, $department);
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $statement = $this->pdo->prepare($this->accountLifecycleV5
                ? "INSERT INTO users (username, password, email, role, department_id, supervisor_id, regelarbeitszeit, ueberstunden, account_status, auth_generation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 1)"
                : 'INSERT INTO users (username, password, email, role, department_id, supervisor_id, regelarbeitszeit, ueberstunden) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$username, $hashedPassword, $email, $role,
                $assignment['department_id'], $assignment['supervisor_id'], $regularHours, $carryIn]);
            $statement->closeCursor();
            // Existing initial-work-model and audit triggers run once in this
            // transaction; auth_source/token retain their native defaults.
            return true;
        }, fn (Closure $transaction): mixed => $this->context->run('web:'.$user, 'admin.php', $transaction),
            lifecycleReady: $this->accountLifecycleV5 && $lifecycleAuthority !== null);
    }

    /** Operator-authorized setup only; the empty-state decision is made under this lock. */
    public function firstLocalAdministrator(string $username, string $email, #[\SensitiveParameter] string $passwordHash): bool
    {
        if ((password_get_info($passwordHash)['algoName'] ?? 'unknown') !== 'bcrypt') {
            throw new \InvalidArgumentException('A configured bcrypt setup hash is required.');
        }
        if ($this->accountLifecycleV5 && ! \App\Accounts\AccountLifecycleAuthority::locallyLoginEligibleAdmin([
            'username' => $username, 'password' => $passwordHash, 'role' => 'admin', 'auth_source' => 'local',
            'account_status' => 'active', 'auth_generation' => 1,
        ])) {
            throw new \InvalidArgumentException('A locally login-eligible first administrator is required.');
        }
        return $this->executeLocked(function () use ($username, $email, $passwordHash): bool {
            if ($this->accountLifecycleV5) { $this->assertIntakeProfileLocked(true); }
            // ALL users, not only admins: never take over an existing installation.
            if ((int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
                return false;
            }
            $statement = $this->pdo->prepare($this->accountLifecycleV5
                ? "INSERT INTO users (username,password,email,role,auth_source,token,department_id,supervisor_id,regelarbeitszeit,ueberstunden,account_status,auth_generation) VALUES (?,?,?,'admin','local',NULL,NULL,NULL,8,0,'active',1)"
                : "INSERT INTO users (username,password,email,role,auth_source,token,department_id,supervisor_id,regelarbeitszeit,ueberstunden) VALUES (?,?,?,'admin','local',NULL,NULL,NULL,8,0)");
            $statement->execute([$username, $passwordHash, $email]);
            $statement->closeCursor();
            return true;
        }, fn (Closure $transaction): mixed => $this->context->run('setup:first-local-admin', 'First administrator setup', $transaction),
            lifecycleReady: $this->accountLifecycleV5);
    }

    /** Existing site-create only; never module settings, pairing, keys or credentials. */
    public function createTerminalSite(int $user, mixed $name,
        ?\App\Accounts\AccountLifecycleAuthority $lifecycleAuthority = null): void
    {
        $this->executeLocked(function () use ($user, $name, $lifecycleAuthority): void {
            $this->assertAdminOperationAuthorityLocked($user, $lifecycleAuthority);
            $actor = att_one($this->pdo, 'SELECT role,auth_source FROM users WHERE id=?', [$user]);
            if ($user < 1 || $actor === null || $actor['role'] !== 'admin' || $actor['auth_source'] !== 'local') {
                throw new AuthorizationException('Nur die Verwaltung darf Terminal-Standorte anlegen.');
            }
            // Browser form strings only: retain native trim and 100-byte bound,
            // but never coerce malformed arrays/objects into a stored site name.
            if (! is_string($name)) {
                throw new \AttendanceError('Standortnamen prüfen.', 400);
            }
            $name = trim($name);
            if ($name === '' || strlen($name) > 100) {
                throw new \AttendanceError('Standortnamen prüfen.', 400);
            }
            $statement = $this->pdo->prepare('INSERT INTO terminal_sites(name) VALUES(?)');
            $statement->execute([$name]);
            $statement->closeCursor();
        }, fn (Closure $transaction): mixed => $this->context->run('web:'.$user, 'terminals.php', $transaction),
            lifecycleReady: $this->accountLifecycleV5 && $lifecycleAuthority !== null);
    }

    /** Existing revoke_credential only; no secrets, device/module changes or grant rewrites. */
    public function revokeTerminalCredential(int $admin, #[\SensitiveParameter] string $expectedWebVersion,
        mixed $userId,
        ?\App\Accounts\AccountLifecycleAuthority $lifecycleAuthority = null): void
    {
        // A normal hidden form field is a canonical positive decimal ID. Refuse
        // arrays/coercions/overflow instead of native accidental casts to zero.
        if (! is_string($userId) || preg_match('/\A[1-9][0-9]*\z/', $userId) !== 1
            || ($target = filter_var($userId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) === false) {
            throw new \InvalidArgumentException('Ungültiger Terminal-Zugang.');
        }
        $this->executeLocked(function () use ($admin, $expectedWebVersion, $target, $lifecycleAuthority): void {
            // Reuse the existing lock-bound source/role/password-version guard.
            // It re-reads this Admin under the SAME IMMEDIATE mutation lock.
            $this->assertExpectedWebPrincipal($admin, 'local', $expectedWebVersion, 'admin', $lifecycleAuthority);
            $statement = $this->pdo->prepare('UPDATE employee_credentials SET enabled=0 WHERE user_id=?');
            $statement->execute([$target]);
            $statement->closeCursor();
            // Exact native UPDATE, including zero-row/already-disabled behavior.
            // The existing terminal protocol checks enabled on identify/grant use.
            // No PIN/card value, user field, work row or grant is read or changed.
        }, fn (Closure $transaction): mixed => $this->context->run('web:'.$admin, 'terminals.php', $transaction),
            lifecycleReady: $this->accountLifecycleV5 && $lifecycleAuthority !== null);
    }

    /** Existing revoke_device only; no key reads, pairing creation or credential/module changes. */
    public function revokeTerminalDevice(int $admin, #[\SensitiveParameter] string $expectedWebVersion,
        mixed $deviceId,
        ?\App\Accounts\AccountLifecycleAuthority $lifecycleAuthority = null): void
    {
        if (! is_string($deviceId) || preg_match('/\A[1-9][0-9]*\z/', $deviceId) !== 1
            || ($target = filter_var($deviceId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) === false) {
            throw new \InvalidArgumentException('Ungültiges Terminal-Gerät.');
        }
        $this->executeLocked(function () use ($admin, $expectedWebVersion, $target, $lifecycleAuthority): void {
            // The same IMMEDIATE lock protects fresh actor checks and mutation.
            $this->assertExpectedWebPrincipal($admin, 'local', $expectedWebVersion, 'admin', $lifecycleAuthority);
            $statement = $this->pdo->prepare('UPDATE terminals SET enabled=0,generation=?,pairing_hash=NULL,pairing_expires=NULL WHERE id=?');
            $statement->execute([bin2hex(random_bytes(16)), $target]);
            $statement->closeCursor();
            // Exact native replacement generation, including repeated/zero-row
            // behavior. No existing generation, secret or pairing hash is read.
        }, fn (Closure $transaction): mixed => $this->context->run('web:'.$admin, 'terminals.php', $transaction),
            lifecycleReady: $this->accountLifecycleV5 && $lifecycleAuthority !== null);
    }

    /** Native module toggle only; server permission is re-read, never written. */
    public function saveTerminalModuleSettings(int $admin, #[\SensitiveParameter] string $expectedWebVersion,
        bool $enabled,
        ?\App\Accounts\AccountLifecycleAuthority $lifecycleAuthority = null): void
    {
        $this->executeLocked(function () use ($admin, $expectedWebVersion, $enabled, $lifecycleAuthority): void {
            $this->assertExpectedWebPrincipal($admin, 'local', $expectedWebVersion, 'admin', $lifecycleAuthority);
            // Exact native gates on this same IMMEDIATE-locked PDO. Do not trust
            // the displayed status or permit changing APP_TERMINAL_ENABLED here.
            if ($enabled && getenv('APP_TERMINAL_ENABLED') !== '1') {
                throw new \AttendanceError('Zuerst APP_TERMINAL_ENABLED=1 auf dem Server setzen.');
            }
            if (! $enabled && att_one($this->pdo, 'SELECT id FROM work_sessions WHERE ended_at IS NULL LIMIT 1')) {
                throw new \AttendanceError('Zuerst offene Arbeitszeiten beenden.');
            }
            if ($enabled && ! attendance_enabled($this->pdo)
                && att_one($this->pdo, 'SELECT id FROM zeiterfassung WHERE endzeit IS NULL LIMIT 1')) {
                throw new \AttendanceError('Zuerst vorhandene offene Arbeitszeiten im bisherigen System beenden.');
            }
            $statement = $this->pdo->prepare('UPDATE terminal_settings SET enabled=? WHERE id=1');
            $statement->execute([$enabled ? 1 : 0]);
            $statement->closeCursor();
        }, fn (Closure $transaction): mixed => $this->context->run('web:'.$admin, 'terminals.php', $transaction),
            lifecycleReady: $this->accountLifecycleV5 && $lifecycleAuthority !== null);
    }

    /** Native new-device enrollment only; no signing-key source or protocol response. */
    public function createTerminalDevicePairing(int $admin, #[\SensitiveParameter] string $expectedWebVersion,
        mixed $name, mixed $siteId,
        ?\App\Accounts\AccountLifecycleAuthority $lifecycleAuthority = null): \App\Chrono\TerminalDevicePairing
    {
        if (! is_string($name) || trim($name) === '' || strlen(trim($name)) > 100
            || ! is_string($siteId) || preg_match('/\A[1-9][0-9]*\z/', $siteId) !== 1
            || ($site = filter_var($siteId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) === false) {
            throw new \InvalidArgumentException('Name und Standort prüfen.');
        }
        $name = trim($name);

        return $this->executeLocked(function () use ($admin, $expectedWebVersion, $name, $site, $lifecycleAuthority): \App\Chrono\TerminalDevicePairing {
            $this->assertExpectedWebPrincipal($admin, 'local', $expectedWebVersion, 'admin', $lifecycleAuthority);
            attendance_require_enabled($this->pdo);
            if (att_one($this->pdo, 'SELECT id FROM terminal_sites WHERE id=?', [$site]) === null) {
                throw new \AttendanceError('Name und Standort prüfen.', 400);
            }
            // Match native entropy/hash/600-second validity. These are enrollment
            // code and invalidation generation, not installation/device signing keys.
            $code = bin2hex(random_bytes(24));
            $expires = time() + 600;
            $statement = $this->pdo->prepare('INSERT INTO terminals(site_id,name,generation,pairing_hash,pairing_expires) VALUES(?,?,?,?,?)');
            $statement->execute([$site, $name, bin2hex(random_bytes(16)), hash('sha256', $code), $expires]);
            $statement->closeCursor();

            return new \App\Chrono\TerminalDevicePairing((int) $this->pdo->lastInsertId(), $code, $expires);
        }, fn (Closure $transaction): mixed => $this->context->run('web:'.$admin, 'terminals.php', $transaction),
            lifecycleReady: $this->accountLifecycleV5 && $lifecycleAuthority !== null);
    }

    /** Anonymous self-registration only: fixed ordinary role and no caller actor/privileges. */
    public function registerLocalUser(string $username, string $email, #[\SensitiveParameter] string $passwordHash,
        ?int $expectedPolicyRevision = null): bool
    {
        if ((password_get_info($passwordHash)['algoName'] ?? 'unknown') !== 'bcrypt') {
            throw new \InvalidArgumentException('A configured bcrypt registration hash is required.');
        }
        return $this->executeLocked(function () use ($username, $email, $passwordHash, $expectedPolicyRevision): bool {
            $this->requireIntakeLocked(\App\Accounts\IntakeMethod::Registration, $expectedPolicyRevision);
            // Do not strand first-admin bootstrap or trust an earlier page/count.
            if ($this->accountLifecycleV5 ? ! $this->hasV5LocalAdministrationLocked()
                : att_one($this->pdo, "SELECT id FROM users WHERE role='admin' AND auth_source='local' LIMIT 1") === null) {
                throw new AuthorizationException('Ordinary registration requires existing local administration.');
            }
            if (att_one($this->pdo, 'SELECT id FROM users WHERE username=? OR email=? LIMIT 1', [$username, $email]) !== null) {
                return false;
            }
            $statement = $this->pdo->prepare($this->accountLifecycleV5
                ? "INSERT INTO users (username,password,email,role,auth_source,token,department_id,supervisor_id,regelarbeitszeit,ueberstunden,account_status,auth_generation) VALUES (?,?,?,'user','local',NULL,NULL,NULL,8,0,'active',1)"
                : "INSERT INTO users (username,password,email,role,auth_source,token,department_id,supervisor_id,regelarbeitszeit,ueberstunden) VALUES (?,?,?,'user','local',NULL,NULL,NULL,8,0)");
            $statement->execute([$username, $passwordHash, $email]);
            $statement->closeCursor();
            // Existing model/audit triggers remain inside this one transaction.
            return true;
        }, fn (Closure $transaction): mixed => $this->context->run('web:registration', 'Self-registration', $transaction),
            lifecycleReady: $this->accountLifecycleV5);
    }

    /** Trusted server Admin ID and completed materialized stage, never request identity. */
    public function importLdapUsers(int $user, #[\SensitiveParameter] \App\Ldap\MaterializedLdapImport $stage): array
    {
        require_once dirname(__DIR__, 3).'/lib/ldap_import.php';

        return $this->executeLocked(function () use ($user, $stage): array {
            $this->assertIntakeProfileLocked();
            if ($this->intakePolicyV4) {
                throw new LogicException('V4 LDAP intake requires the policy-bound operation.');
            }
            $actor = att_one($this->pdo, 'SELECT role,auth_source FROM users WHERE id=?', [$user]);
            if ($user < 1 || $actor === null || $actor['role'] !== 'admin' || $actor['auth_source'] !== 'local') {
                throw new AuthorizationException('Only current local administration may import LDAP users.');
            }
            // Read the complete eligible stage under this same writer lock,
            // before even ldap_settings or departments can be changed.
            $stage->refuseLocalAccountCollisions($this->pdo);

            return \chrono_ldap_import_locked($this->pdo, $stage->entries, $stage->host, $stage->port,
                $stage->bindUser, $stage->ciphertext, $stage->baseDn);
        }, fn (Closure $transaction): mixed => $this->context->run('web:'.$user, 'LDAP synchronization', $transaction));
    }

    /** Guard-derived ID/hash context and snapshot revision only, never request identity. */
    public function importLdapUsersWithIntakePolicy(int $user, #[\SensitiveParameter] string $expectedWebVersion,
        int $expectedPolicyRevision, #[\SensitiveParameter] \App\Ldap\MaterializedLdapImport $stage,
        ?\App\Accounts\AccountLifecycleAuthority $lifecycleAuthority = null): array
    {
        require_once dirname(__DIR__, 3).'/lib/ldap_import.php';

        return $this->executeLocked(function () use ($user, $expectedWebVersion, $expectedPolicyRevision, $stage, $lifecycleAuthority): array {
            $this->assertIntakeProfileLocked(true);
            $this->assertExpectedWebPrincipal($user, 'local', $expectedWebVersion, 'admin', $lifecycleAuthority);
            (new \App\Accounts\IntakePolicyStore)->requireAllowedLocked($this->pdo,
                \App\Accounts\IntakeMethod::LdapSync, $expectedPolicyRevision, $this->accountLifecycleV5);
            $stage->refuseLocalAccountCollisions($this->pdo, $this->accountLifecycleV5);

            return \chrono_ldap_import_locked($this->pdo, $stage->entries, $stage->host, $stage->port,
                $stage->bindUser, $stage->ciphertext, $stage->baseDn, $this->accountLifecycleV5);
        }, fn (Closure $transaction): mixed => $this->context->run('web:'.$user, 'LDAP synchronization', $transaction),
            lifecycleReady: $this->accountLifecycleV5 && $lifecycleAuthority !== null);
    }

    /** Strict persisted V4 policy CAS only; not an intake path or arbitrary account command. */
    public function changeIntakePolicy(int $user, #[\SensitiveParameter] string $expectedWebVersion,
        \App\Accounts\IntakePolicyChange $change, ?\App\Accounts\AccountLifecycleAuthority $lifecycleAuthority = null): void
    {
        $this->executeLocked(function () use ($user, $expectedWebVersion, $change, $lifecycleAuthority): void {
            $this->assertIntakeProfileLocked(true);
            $this->assertExpectedWebPrincipal($user, 'local', $expectedWebVersion, 'admin', $lifecycleAuthority);
            (new \App\Accounts\IntakePolicyStore)->applyLocked($this->pdo, $user, $change, $this->accountLifecycleV5);
        }, fn (Closure $transaction): mixed => $this->context->run('web:'.$user, 'Aufnahmewege ändern', $transaction),
            lifecycleReady: $this->accountLifecycleV5 && $lifecycleAuthority !== null);
    }

    /** Own Admin identity only; no request target/role/reason or general account mutation. */
    public function ownCarryIn(int $user, mixed $raw, ?\App\Accounts\AccountLifecycleAuthority $lifecycleAuthority = null, ?array $webContext = null): string
    {
        require_once dirname(__DIR__, 3).'/lib/settings_carry_in.php';
        $parsed = \settings_carry_in_value($raw);

        return $this->executeLocked(function () use ($user, $parsed, $lifecycleAuthority, $webContext): string {
            $this->assertBusinessAdminAuthorityLocked($user, $lifecycleAuthority, $webContext);
            $actor = att_one($this->pdo, 'SELECT role,auth_source FROM users WHERE id=?', [$user]);
            if ($user < 1 || $actor === null || $actor['role'] !== 'admin' || ! in_array($actor['auth_source'], ['local', 'ldap'], true)) {
                throw new AuthorizationException('Nur die Verwaltung darf den eigenen Überstunden-Übertrag ändern.');
            }

            $statement = \settings_carry_in_prepare($this->pdo);
            // Never turn a validated float into PDO's precision-INI-dependent string.
            $statement->bindValue(':ueberstunden', $parsed['text'], PDO::PARAM_STR);
            $statement->bindValue(':id', $user, PDO::PARAM_INT);
            $statement->execute();
            $changed = $statement->rowCount();
            $statement->closeCursor();
            $stored = att_one($this->pdo, 'SELECT ueberstunden FROM users WHERE id=?', [$user]);
            if ($changed !== 1 || $stored === null
                || (! is_int($stored['ueberstunden']) && ! is_float($stored['ueberstunden']))
                || ! is_finite((float) $stored['ueberstunden'])
                || (float) $stored['ueberstunden'] !== $parsed['value']) {
                throw new \InvalidArgumentException('Der Wert konnte im vorhandenen REAL-Zahlenformat nicht unverändert gespeichert werden. Es wurde nichts am Übertrag gespeichert.');
            }

            return \settings_carry_in_float_text((float) $stored['ueberstunden']);
        }, fn (Closure $transaction): mixed => $this->context->run('web:'.$user, 'settings.php', $transaction),
            lifecycleReady: $this->accountLifecycleV5 && $lifecycleAuthority !== null);
    }

    /** Trusted own-user identity only; no caller-supplied audit actor/reason. */
    public function absence(int $user, string $start, string $end, string $kind, ?string $expectedWebSource = null, #[\SensitiveParameter] ?string $expectedWebVersion = null): array
    {
        require_once dirname(__DIR__, 3).'/lib/practice.php';

        // Match native date/type refusal before BEGIN. The core also validates
        // under lock through this same parser; no caller can bypass the rules.
        practice_absence_range($start, $end, $kind);

        return $this->executeLocked(function () use ($user, $start, $end, $kind, $expectedWebSource, $expectedWebVersion): array {
            $this->assertExpectedWebPrincipal($user, $expectedWebSource, $expectedWebVersion);

            return practice_absence_apply_locked(
                $this->pdo, $user, $start, $end, $kind,
                fn (string $reason, Closure $operation): mixed => $this->context->run('web:'.$user, $reason, $operation),
            );
        });
    }

    /** The explicit composition must agree with the actual database, never a request flag. */
    private function assertIntakeProfile(): void
    {
        $versions = $this->pdo->query('SELECT version FROM app_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
        $policyType = $this->pdo->query("SELECT type FROM sqlite_schema WHERE type IN ('table','view') AND lower(name)='user_intake_policy'")->fetchColumn();
        $matches = $this->accountLifecycleV5
            ? $this->intakePolicyV4 && $versions === [1, 2, 3, 4, 5] && $policyType === 'table'
            : ($this->intakePolicyV4
            ? $versions === [1, 2, 3, 4] && $policyType === 'table'
            : in_array($versions, [[1, 2], [1, 2, 3]], true) && $policyType === false);
        if (! $matches) {
            throw new LogicException('The admitted intake profile does not match the database.');
        }
    }

    private function assertIntakeProfileLocked(bool $requireV4 = false): void
    {
        $this->assertActive();
        $this->assertIntakeProfile();
        if ($requireV4 && ! $this->intakePolicyV4) {
            throw new LogicException('An explicit intake-policy-v4 writer is required.');
        }
    }

    /** Legacy profiles retain their contract; V4 never accepts an absent/stale server context. */
    private function requireIntakeLocked(\App\Accounts\IntakeMethod $method, ?int $revision,
        ?int $admin = null, #[\SensitiveParameter] ?string $version = null,
        ?\App\Accounts\AccountLifecycleAuthority $lifecycleAuthority = null): void
    {
        $this->assertIntakeProfileLocked();
        if (! $this->intakePolicyV4) {
            if ($revision !== null || $version !== null || $lifecycleAuthority !== null) {
                throw new LogicException('A V4 intake context cannot target a legacy profile.');
            }
            return;
        }
        if ($revision === null || $revision < 1) {
            throw new LogicException('A fresh server-derived V4 intake revision is required.');
        }
        if ($method === \App\Accounts\IntakeMethod::Manual) {
            if ($admin === null || $version === null) {
                throw new LogicException('A fresh local Admin intake context is required.');
            }
            $this->assertExpectedWebPrincipal($admin, 'local', $version, 'admin', $lifecycleAuthority);
        }
        (new \App\Accounts\IntakePolicyStore)->requireAllowedLocked($this->pdo, $method, $revision, $this->accountLifecycleV5);
    }

    /** Anonymous registration still needs an active usable local recovery Admin. */
    private function hasV5LocalAdministrationLocked(): bool
    {
        $this->assertIntakeProfileLocked(true);
        $statement = $this->pdo->query("SELECT username,password,role,auth_source,account_status,auth_generation FROM users WHERE role='admin' AND auth_source='local' AND account_status='active'");
        try {
            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (\App\Accounts\AccountLifecycleAuthority::locallyLoginEligibleAdmin($row)) { return true; }
            }
            return false;
        } finally {
            $statement->closeCursor();
        }
    }

    /** Trusted guard-derived pair only; never accepted from command/request data. */
    private function assertExpectedWebPrincipal(int $user, ?string $source, #[\SensitiveParameter] ?string $version,
        ?string $role = null, ?\App\Accounts\AccountLifecycleAuthority $lifecycleAuthority = null): void
    {
        if ($this->accountLifecycleV5) {
            if ($lifecycleAuthority === null || ! $lifecycleAuthority->binds($user, $source, $version, $role)) {
                throw new \AttendanceError('Sitzung nicht mehr gültig. Bitte neu anmelden.', 401);
            }
            $this->assertLifecyclePrincipalLocked($lifecycleAuthority,
                localAdmin: $source === 'local' && $role === 'admin');

            return;
        }
        if ($lifecycleAuthority !== null) {
            throw new LogicException('A V5 authority cannot target an older account profile.');
        }
        if ($source === null && $version === null) {
            return; // Existing local/API/terminal methods do not gain new rules.
        }
        if (! $this->pdo->inTransaction() || ! in_array($source, ['local', 'ldap'], true)
            || ! is_string($version) || preg_match('/\A[a-f0-9]{64}\z/', $version) !== 1) {
            throw new \AttendanceError('Sitzung nicht mehr gültig. Bitte neu anmelden.', 401);
        }
        $actor = att_one($this->pdo, 'SELECT role,auth_source,password FROM users WHERE id=?', [$user]);
        if ($user < 1 || $actor === null || $actor['auth_source'] !== $source
            || ! in_array($actor['role'], ['user', 'supervisor', 'admin'], true)
            || ($role !== null && $actor['role'] !== $role)
            || ! is_string($actor['password']) || ! $actor['password']
            || ! hash_equals($version, hash('sha256', $actor['password']))) {
            throw new \AttendanceError('Sitzung nicht mehr gültig. Bitte neu anmelden.', 401);
        }
    }

    // The single existing #75 transaction/guard/failure lifecycle, shared only
    // internally. No public arbitrary-write API or second transaction manager.
    private function executeLocked(Closure $body, ?Closure $auditScope = null, bool $lifecycleReady = false): mixed
    {
        $this->assertIdle();
        // V5 is not globally active. Until every facade/channel carries a
        // captured generation, old operations (including API null/null) must
        // refuse V5 before BEGIN, rather than silently applying V4 rules.
        if ($this->accountLifecycleV5 && ! $lifecycleReady) {
            throw new LogicException('This operation is not yet bound to V5 account authority.');
        }
        // The legacy operation still sets globals. Framework UDFs use scoped
        // context instead; nevertheless restore even pre-existing null values.
        $globals = array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason']));
        $commitAttempted = false;

        try {
            $transaction = function () use ($body, &$commitAttempted): mixed {
                // Exactly one attempt. Laravel owns BEGIN/COMMIT/ROLLBACK and
                // transactionLevel; the domain's raw-BEGIN wrapper is NOT used.
                $this->connection->beginTransaction();
                $this->assertActive();
                $response = $body();
                $this->assertActive();
                $commitAttempted = true;
                $this->connection->commit();
                $this->assertIdle();

                return $response;
            };

            return $auditScope === null ? $transaction() : $auditScope($transaction);
        } catch (Throwable $error) {
            try {
                // Explicit begin/commit (not transaction()) leaves Laravel's
                // level intact on COMMIT failure. Rollback also releases a BUSY
                // commit's still-live SQLite transaction; never retry the body.
                if ($this->connection->getRawPdo() !== $this->pdo) {
                    $this->unusable = true;
                    // Never roll back/commit a replacement handle. The captured
                    // PDO is the only handle that may own our domain mutation.
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                } else {
                    $this->connection->rollBack(0);
                }
                $this->assertIdle();
            } catch (Throwable) {
                $this->unusable = true;
                // A known domain refusal is no longer a confirmed outcome when
                // cleanup/idle state is unknown. Never expose either failure or
                // let callers treat it as safe validation/replay feedback.
                throw new RuntimeException('Writer outcome could not be confirmed. Do not retry automatically.');
            }
            if ($commitAttempted) {
                // COMMIT may have succeeded before its acknowledgement/callback
                // failed. An idle handle cannot prove the mutation rolled back.
                $this->unusable = true;
                throw new RuntimeException('Writer outcome could not be confirmed. Do not retry automatically.');
            }
            throw $error;
        } finally {
            unset($GLOBALS['audit_actor'], $GLOBALS['audit_reason']);
            foreach ($globals as $key => $value) {
                $GLOBALS[$key] = $value;
            }
        }
    }

    private function assertIdle(): void
    {
        if ($this->unusable || $this->connection->getRawPdo() !== $this->pdo
            || $this->connection->transactionLevel() !== 0 || $this->pdo->inTransaction()) {
            throw new LogicException('The writer is not idle; nesting, savepoints and connection replacement are forbidden.');
        }
    }

    private function assertActive(): void
    {
        if ($this->connection->getRawPdo() !== $this->pdo
            || $this->connection->transactionLevel() !== 1 || ! $this->pdo->inTransaction()) {
            throw new LogicException('The single writer transaction was replaced or interrupted.');
        }
    }
}
