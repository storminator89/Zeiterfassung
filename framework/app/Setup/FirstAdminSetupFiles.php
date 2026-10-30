<?php

namespace App\Setup;

use App\Auth\FirstAdminSetupFields;
use App\Auth\FirstAdminSetupInputRejected;
use App\LegacyDatabase\AccountLifecycleV5References;
use App\LegacyDatabase\VacationWorkflowV7References;
use App\LegacyDatabase\AuditContext;
use App\LegacyDatabase\AuditFunctions;
use App\LegacyDatabase\IntakePolicyV4References;
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\RuntimeAdmission;
use App\LegacyDatabase\RuntimeDatabase;
use Closure;
use Illuminate\Contracts\Hashing\Hasher;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/** Explicit local operator setup only; no public provisioning or existing-account reset. */
final class FirstAdminSetupFiles
{
    public const PROFILE = 'setup-profile.json';
    public const CLOSED = 'setup-closed';
    public const LOCAL_BROWSER_PROFILE = 'local-first-user-setup.json';
    private const TOKEN = 'operator-setup-token.txt';
    private const LOCK = 'setup-operator.lock';

    /**
     * Interactive stopped-only NEW-V5 alternative to browser/token setup.
     * The collector receives no file/DB handle and runs only after private fresh-state admission.
     * @param Closure(): array $credentials Local hidden-terminal collection, never request/env/arguments.
     */
    public static function createLocalAdministrator(string $instance, Hasher $hasher, Closure $credentials): array
    {
        self::cli();
        return self::createAdministrator($instance, $hasher, $credentials);
    }

    /** First successful browser registration only, under the chosen NEW local-install policy. */
    public static function createBrowserAdministrator(string $instance, string $origin, Hasher $hasher, #[\SensitiveParameter] array $form): array
    {
        if (! self::localOrigin($origin) || ! function_exists('posix_geteuid') || ! function_exists('fsync')) {
            throw new RuntimeException('The local browser setup profile is not admitted.');
        }
        return self::createAdministrator($instance, $hasher, static fn (): array => $form, $origin);
    }

    private static function createAdministrator(string $instance, Hasher $hasher, Closure $credentials, ?string $browserOrigin = null): array
    {
        $state = self::instance($instance);
        $lock = self::lock($instance, $state);
        $form = $password = $hash = $writer = null;
        try {
            self::requireNoSetup($instance);
            [$manifest, $manifestHash, $manifestState] = self::json($instance.'/runtime-admission.json');
            $admission = RuntimeAdmission::fromConfiguration($manifest);
            if (!$admission->isGenerationBoundAccountProfile() || $admission->path !== $instance.'/application.sqlite') {
                throw new RuntimeException;
            }
            if ($browserOrigin !== null) { self::requireLocalBrowserProfile($instance, $manifestHash); }
            // App-key preparation requires empty state, so it must precede this operation.
            // Check its private canonical format without activating it or exposing any bytes.
            $keyState = self::applicationKeyState($instance);
            $root = dirname(__DIR__, 3);
            $files = new OfflineFiles([$root, $root.'/framework/public']);
            $before = $files->snapshot($admission->path);
            if ($browserOrigin === null) {
                self::assertEmptyReadSnapshot($admission, $files, $root);
            } else {
                // Fresh provenance was certified before this nonsecret profile was
                // created. Normal anonymous login-budget rows do not revoke it.
                self::assertNoUsersReadSnapshot($admission, $files);
            }
            if ($files->snapshot($admission->path) !== $before) { throw new RuntimeException; }

            $form = $credentials();
            try {
                [$username, $email, $password] = FirstAdminSetupFields::validate($form);
            } catch (InvalidArgumentException) {
                throw new FirstAdminSetupInputRejected;
            }
            $form = null;
            self::sameInstance($instance, $state);
            self::sameJson($instance.'/runtime-admission.json', $manifestHash, $manifestState);
            self::requireNoSetup($instance);
            if ($browserOrigin !== null) { self::requireLocalBrowserProfile($instance, $manifestHash); }
            if (self::applicationKeyState($instance) !== $keyState || $files->snapshot($admission->path) !== $before) {
                throw new RuntimeException;
            }

            $hash = $hasher->make($password);
            $password = null;
            $context = new AuditContext;
            $writer = (new RuntimeDatabase($admission, $files))->writer($context, new AuditFunctions(static fn () => $context));
            // The existing single transaction rechecks ALL users=0 under its lock.
            // A false/nonempty result is never an account update or successful receipt here.
            if ($writer->firstLocalAdministrator($username, $email, $hash) !== true) { throw new RuntimeException; }
            $hash = $writer = null;
            self::sameInstance($instance, $state);
            self::sameJson($instance.'/runtime-admission.json', $manifestHash, $manifestState);
            self::requireNoSetup($instance);
            if ($browserOrigin !== null) { self::requireLocalBrowserProfile($instance, $manifestHash); }
            if (self::applicationKeyState($instance) !== $keyState) { throw new RuntimeException; }
            // Only AFTER confirmed account COMMIT. Never remove even a partial marker.
            // Closure failure is unconfirmed, never proof of rollback. CLI callers
            // keep web stopped; an already-running browser profile instead retains
            // the original all-users-zero guard and must be inspected/closed safely.
            self::writeNew($instance.'/'.self::CLOSED, "closed\n");
            self::sameInstance($instance, $state);

            return ['account_created' => true, 'setup_closed' => true, 'setup_token_created' => false,
                'server_started' => false, 'next_step' => $browserOrigin === null
                    ? 'Start the separately authorized closed-setup web profile, then use normal password login.'
                    : 'Use normal password login; first-user setup is now closed.'];
        } catch (FirstAdminSetupInputRejected) {
            throw new FirstAdminSetupInputRejected('Enter valid username/email and matching chosen passwords of 12-72 bytes. No account was created by this input refusal.');
        } catch (Throwable) {
            // A late error may follow a confirmed or uncertain COMMIT: never retry/reset,
            // reopen setup, remove its marker, log a raw error, or claim rollback.
            throw new RuntimeException('Local first-administrator setup not confirmed. Keep web workers stopped and inspect locally; no automatic retry.');
        } finally {
            unset($form, $password, $hash, $writer);
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    /** Selected only by the NEW local installation command; no token, key, account or listener. */
    public static function prepareLocalBrowserNew(string $instance): array
    {
        self::cli();
        $state = self::instance($instance);
        $lock = self::lock($instance, $state);
        try {
            self::requireNoSetup($instance);
            if (@lstat($instance.'/'.self::LOCAL_BROWSER_PROFILE) !== false) { throw new RuntimeException; }
            [$manifest, $hash, $manifestState] = self::json($instance.'/runtime-admission.json');
            $admission = RuntimeAdmission::fromConfiguration($manifest);
            if (!$admission->isGenerationBoundAccountProfile() || $admission->path !== $instance.'/application.sqlite') {
                throw new RuntimeException;
            }
            $root = dirname(__DIR__, 3);
            $files = new OfflineFiles([$root, $root.'/framework/public']);
            $before = $files->snapshot($admission->path);
            self::assertEmptyReadSnapshot($admission, $files, $root);
            if ($files->snapshot($admission->path) !== $before) { throw new RuntimeException; }
            self::sameInstance($instance, $state);
            self::sameJson($instance.'/runtime-admission.json', $hash, $manifestState);
            self::requireNoSetup($instance);
            self::writeNew($instance.'/'.self::LOCAL_BROWSER_PROFILE, json_encode([
                'version' => 1, 'mode' => 'local-first-user-admin', 'runtime_admission_sha256' => $hash,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
            self::sameInstance($instance, $state);

            return ['local_browser_first_user_admin' => true, 'account_created' => false,
                'setup_token_created' => false, 'server_started' => false];
        } catch (Throwable) {
            throw new RuntimeException('NEW local browser setup preparation not confirmed. Keep the new instance private; no automatic retry.');
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    /** Per-request private metadata only. Local reachability is a policy boundary, NOT user identity. */
    public static function localBrowserConfiguration(string $instance, string $origin): array
    {
        if (! self::localOrigin($origin)) { throw new RuntimeException; }
        self::instance($instance);
        self::requireNoSetup($instance);
        [$manifest, $hash] = self::json($instance.'/runtime-admission.json');
        $admission = RuntimeAdmission::fromConfiguration($manifest);
        if (!$admission->isGenerationBoundAccountProfile() || $admission->path !== $instance.'/application.sqlite') {
            throw new RuntimeException;
        }
        self::requireLocalBrowserProfile($instance, $hash);

        return ['enabled' => true, 'token_sha256' => null, 'local_first_user' => true,
            'instance_directory' => $instance, 'origin' => $origin];
    }

    private static function requireLocalBrowserProfile(string $instance, string $manifestHash): void
    {
        [$profile] = self::json($instance.'/'.self::LOCAL_BROWSER_PROFILE);
        if ($profile !== ['version' => 1, 'mode' => 'local-first-user-admin', 'runtime_admission_sha256' => $manifestHash]) {
            throw new RuntimeException;
        }
    }

    /** Canonical HTTPS loopback/RFC1918 origins only; never an authentication claim. */
    private static function localOrigin(string $origin): bool
    {
        try { $parts = @parse_url($origin); } catch (Throwable) { return false; }
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || isset($parts['path'])
            || ! is_string($parts['host'] ?? null)
            || (isset($parts['port']) && (! is_int($parts['port']) || $parts['port'] < 1 || $parts['port'] > 65535))) { return false; }
        $host = $parts['host'];
        if (preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\z/D', $host) !== 1 || str_contains($host, '..')) { return false; }
        foreach (explode('.', $host) as $label) {
            if (preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/D', $label) !== 1) { return false; }
        }
        $canonical = 'https://'.$host.(isset($parts['port']) && $parts['port'] !== 443 ? ':'.$parts['port'] : '');
        if ($host !== strtolower($host) || $origin !== $canonical) { return false; }
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) { return true; }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) { return false; }
        $octets = array_map('intval', explode('.', $host));

        return $octets[0] === 127 || $octets[0] === 10
            || ($octets[0] === 172 && $octets[1] >= 16 && $octets[1] <= 31)
            || ($octets[0] === 192 && $octets[1] === 168);
    }

    private static function assertNoUsersReadSnapshot(RuntimeAdmission $admission, OfflineFiles $files): void
    {
        $reader = (new RuntimeDatabase($admission, $files))->readConnection($admission->configuration(), 'chrono');
        $pdo = $statement = null;
        try {
            $pdo = $reader->getPdo();
            $pdo->beginTransaction();
            $statement = $pdo->query('SELECT COUNT(*) FROM users');
            if ($statement->fetchColumn() !== 0) { throw new RuntimeException; }
            $statement->closeCursor();
            $statement = null;
            if (! $pdo->rollBack()) { throw new RuntimeException; }
        } finally {
            try {
                $statement?->closeCursor();
                if ($pdo instanceof PDO && $pdo->inTransaction() && ! $pdo->rollBack()) { throw new RuntimeException; }
            } finally {
                $reader->disconnect();
                $pdo = $statement = $reader = null;
            }
        }
    }

    private static function requireNoSetup(string $instance): void
    {
        foreach ([self::CLOSED, self::PROFILE, self::TOKEN] as $name) {
            clearstatcache(true, $instance.'/'.$name);
            if (@lstat($instance.'/'.$name) !== false) { throw new RuntimeException; }
        }
    }

    /** Internal identity only; no key/digest is returned to the operator or activated. */
    private static function applicationKeyState(string $instance): array
    {
        $path = $instance.'/framework-app-key.env';
        $handle = self::openPrivate($path);
        try {
            $state = self::file($path, $handle);
            $length = strlen('FRAMEWORK_APP_KEY=base64:') + 44 + 1;
            if ($state['size'] !== $length) { throw new RuntimeException; }
            $bytes = stream_get_contents($handle, $length + 1);
            if (! is_string($bytes) || strlen($bytes) !== $length || self::file($path, $handle) !== $state
                || preg_match('/\AFRAMEWORK_APP_KEY=base64:([A-Za-z0-9+\/]{43}=)\n\z/D', $bytes, $matches) !== 1
                || ($decoded = base64_decode($matches[1], true)) === false || strlen($decoded) !== 32
                || base64_encode($decoded) !== $matches[1]) { throw new RuntimeException; }

            return [$state, hash('sha256', $bytes)];
        } finally {
            unset($bytes, $matches, $decoded);
            if (! @fclose($handle)) { throw new RuntimeException; }
        }
    }

    /** Pure trusted-profile projection; web composition must recheck closure per request. */
    public static function configuration(array $profile, string $admissionSha256, bool $closed, int $now): array
    {
        $disabled = ['enabled' => false, 'token_sha256' => null];
        // Even an incomplete/absent profile must never override explicit closure.
        if ($closed) { return $disabled; }
        $keys = ['version', 'runtime_admission_sha256', 'token_sha256', 'expires_at'];
        if (count($profile) !== count($keys) || array_diff($keys, array_keys($profile)) !== []
            || ($profile['version'] ?? null) !== 1 || ! is_int($profile['expires_at'] ?? null)
            || $profile['expires_at'] < 1 || $now < 0
            || ! self::digest($admissionSha256) || ! self::digest($profile['runtime_admission_sha256'] ?? null)
            || ! self::digest($profile['token_sha256'] ?? null)
            || ! hash_equals($admissionSha256, $profile['runtime_admission_sha256'])) {
            throw new RuntimeException('The private setup profile is not admitted.');
        }

        return $profile['expires_at'] <= $now ? $disabled : ['enabled' => true, 'token_sha256' => $profile['token_sha256']];
    }

    /** Explicit CLI only. Returns file metadata, NEVER the generated token/digest. */
    public static function prepareNew(string $instance): array
    {
        self::cli();
        $state = self::instance($instance);
        $lock = self::lock($instance, $state);
        $created = [];
        try {
            self::sameInstance($instance, $state);
            foreach ([self::CLOSED, self::PROFILE, self::TOKEN, self::LOCAL_BROWSER_PROFILE] as $name) {
                clearstatcache(true, $instance.'/'.$name);
                if (@lstat($instance.'/'.$name) !== false) {
                    throw new RuntimeException;
                }
            }
            [$manifest, $manifestHash, $manifestState] = self::json($instance.'/runtime-admission.json');
            $admission = RuntimeAdmission::fromConfiguration($manifest);
            if (! in_array($admission->schemaProfile, ['intake-policy-v4', 'account-lifecycle-v5', 'vacation-requests-v7'], true)
                || $admission->path !== $instance.'/application.sqlite') {
                throw new RuntimeException;
            }
            $root = dirname(__DIR__, 3);
            $files = new OfflineFiles([$root, $root.'/framework/public']);
            $before = $files->snapshot($admission->path);
            self::assertEmptyReadSnapshot($admission, $files, $root);
            // No reader/statement escapes the completed void helper scope into
            // the stopped-file comparison or later token/profile generation.
            if ($files->snapshot($admission->path) !== $before) { throw new RuntimeException; }
            self::sameInstance($instance, $state);
            self::sameJson($instance.'/runtime-admission.json', $manifestHash, $manifestState);

            // Deliberately invoked only by an explicitly authorized local operator.
            // No argument/environment/default token, stdout, URL or web endpoint.
            $token = bin2hex(random_bytes(32));
            try {
                $created[self::TOKEN] = self::writeNew($instance.'/'.self::TOKEN, $token."\n");
                $profile = ['version' => 1, 'runtime_admission_sha256' => $manifestHash,
                    'token_sha256' => hash('sha256', $token), 'expires_at' => time() + 3600];
                self::configuration($profile, $manifestHash, false, time());
                self::sameInstance($instance, $state);
                self::sameJson($instance.'/runtime-admission.json', $manifestHash, $manifestState);
                if (@lstat($instance.'/'.self::CLOSED) !== false) { throw new RuntimeException; }
                // Digest-only profile LAST: partial preparation does not enable web setup.
                $created[self::PROFILE] = self::writeNew($instance.'/'.self::PROFILE,
                    json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
            } finally { unset($token); }
            self::sameInstance($instance, $state);
            self::sameJson($instance.'/runtime-admission.json', $manifestHash, $manifestState);

            return ['prepared' => true, 'profile' => $instance.'/'.self::PROFILE,
                'operator_token_file' => $instance.'/'.self::TOKEN, 'validity_seconds' => 3600,
                'web_activated' => false, 'account_created' => false];
        } catch (Throwable) {
            // Partial own files may remain private; no overwrite/retry or raw exception.
            self::discardNew($instance, $state, $created);
            throw new RuntimeException('Setup provisioning not confirmed. Keep the instance private and inspect locally; no automatic retry.');
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    /** Monotone revoke only: no DB read, role change, reset, rotate or reopen. */
    public static function close(string $instance): array
    {
        self::cli();
        $state = self::instance($instance);
        $lock = self::lock($instance, $state);
        try {
            self::sameInstance($instance, $state);
            clearstatcache(true, $instance.'/'.self::CLOSED);
            if (@lstat($instance.'/'.self::CLOSED) === false) {
                // NEVER remove this marker, including a partial-write failure.
                // Any existence is a denial at the web boundary, not an enable bit.
                self::writeNew($instance.'/'.self::CLOSED, "closed\n");
            } else {
                $handle = self::openPrivate($instance.'/'.self::CLOSED);
                fclose($handle);
            }
            self::sameInstance($instance, $state);
            clearstatcache(true, $instance.'/'.self::TOKEN);
            if (@lstat($instance.'/'.self::TOKEN) !== false) {
                $handle = self::openPrivate($instance.'/'.self::TOKEN);
                try {
                    $tokenState = self::file($instance.'/'.self::TOKEN, $handle);
                    self::sameInstance($instance, $state);
                    if (self::file($instance.'/'.self::TOKEN, $handle) !== $tokenState || ! @unlink($instance.'/'.self::TOKEN)) {
                        throw new RuntimeException;
                    }
                } finally { fclose($handle); }
            }

            return ['closed' => true, 'operator_token_file_removed' => true, 'account_changed' => false,
                'web_processes_must_remain_stopped_until_setup_opt_in_removed' => true];
        } catch (Throwable) {
            throw new RuntimeException('Setup close not fully confirmed. Keep web processes stopped and inspect locally; never remove the closure marker.');
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    /** All admitted reader/statement lifetimes end BEFORE any raw cold-file hash. */
    private static function assertEmptyReadSnapshot(RuntimeAdmission $admission, OfflineFiles $files, string $root): void
    {
        $reader = $pdo = $statement = null;
        try {
            $reader = (new RuntimeDatabase($admission, $files))->readConnection($admission->configuration(), 'chrono');
            $pdo = $reader->getPdo();
            $pdo->beginTransaction();
            $mapping = json_decode(file_get_contents($root.'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
            // Same fixed inventory/count policy; never process populated
            // account/credential values or retain a statement outside this scope.
            foreach ($mapping['tables'] as $table) {
                $name = $table['name'];
                if (in_array($name, ['app_migrations', 'pause_settings', 'terminal_settings', 'terminal_sites'], true)) { continue; }
                $statement = $pdo->prepare("SELECT 1 FROM sqlite_schema WHERE type='table' AND name=?");
                try {
                    $statement->execute([$name]);
                    $exists = $statement->fetchColumn() !== false;
                } finally {
                    try { if (! $statement->closeCursor()) { throw new RuntimeException; } }
                    finally { $statement = null; }
                }
                if ($exists) {
                    $statement = $pdo->query('SELECT COUNT(*) FROM "'.$name.'"');
                    try {
                        if ((int) $statement->fetchColumn() !== 0) { throw new RuntimeException; }
                    } finally {
                        try { if (! $statement->closeCursor()) { throw new RuntimeException; } }
                        finally { $statement = null; }
                    }
                }
            }
            // Nested preflight returns metadata only, never the target handle.
            if ($admission->schemaProfile === 'vacation-requests-v7') {
                VacationWorkflowV7References::build($mapping)->requireFreshLoginBudget($pdo);
            } elseif ($admission->schemaProfile === 'account-lifecycle-v5') {
                // Future admitted V5 must be canonical and physically budget-ready.
                // The unchanged Runtime hold above still refuses before this branch.
                AccountLifecycleV5References::build($mapping)->requireFreshLoginBudget($pdo);
            } else {
            IntakePolicyV4References::build($mapping)->requireFresh($pdo);
            }
            if (! $pdo->rollBack() || $pdo->inTransaction()) { throw new RuntimeException; }
        } finally {
            // Cleanup failure propagates, so the caller cannot continue to cold
            // hashing, exclusive reservation or entropy after uncertain cleanup.
            try {
                if ($pdo instanceof PDO && $pdo->inTransaction() && ! $pdo->rollBack()) { throw new RuntimeException; }
            } finally {
                try { $reader?->disconnect(); }
                finally { $statement = $pdo = $reader = null; }
            }
        }
    }

    private static function cli(): void
    {
        if (PHP_SAPI !== 'cli' || ! function_exists('posix_geteuid') || ! function_exists('fsync')) {
            throw new RuntimeException('Setup provisioning requires the local Unix CLI.');
        }
    }

    private static function digest(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1;
    }

    private static function instance(string $path): array
    {
        if ($path === '' || $path[0] !== '/' || str_contains($path, "\0")
            || array_intersect(explode('/', substr($path, 1)), ['', '.', '..']) !== []
            || preg_match('/^chrono-fresh-[a-f0-9]{24}$/D', basename($path)) !== 1) { throw new RuntimeException; }
        $current = '';
        self::ancestor('/');
        foreach (explode('/', substr($path, 1)) as $part) {
            $current .= '/'.$part;
            $stat = self::ancestor($current);
        }
        if (realpath($path) !== $path || ($stat['mode'] & 07777) !== 0700 || $stat['uid'] !== posix_geteuid()) { throw new RuntimeException; }
        $root = dirname(__DIR__, 3);
        foreach ([$root, $root.'/framework/public'] as $forbidden) {
            $forbidden = realpath($forbidden);
            if ($forbidden === false || $path === $forbidden || str_starts_with($path, $forbidden.'/')) { throw new RuntimeException; }
        }

        return array_intersect_key($stat, array_flip(['dev', 'ino', 'uid', 'mode']));
    }

    private static function ancestor(string $path): array
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || ($stat['mode'] & 0170000) !== 0040000
            || ! in_array($stat['uid'], [0, posix_geteuid()], true)
            || (($stat['mode'] & 0022) !== 0 && ($stat['mode'] & 01000) === 0)) { throw new RuntimeException; }

        return $stat;
    }

    private static function sameInstance(string $path, array $state): void
    {
        if (self::instance($path) !== $state) { throw new RuntimeException; }
    }

    private static function file(string $path, mixed $handle): array
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        $opened = is_resource($handle) ? @fstat($handle) : false;
        $keys = array_flip(['dev', 'ino', 'uid', 'mode', 'nlink', 'size', 'mtime']);
        if ($stat === false || $opened === false || ($stat['mode'] & 0170000) !== 0100000
            || ($stat['mode'] & 07777) !== 0600 || $stat['uid'] !== posix_geteuid() || $stat['nlink'] !== 1
            || array_intersect_key($stat, $keys) !== array_intersect_key($opened, $keys)) { throw new RuntimeException; }

        return array_intersect_key($stat, $keys);
    }

    private static function openPrivate(string $path): mixed
    {
        // lstat BEFORE fopen: no accepted symlink/hardlink or mode relaxation.
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || ($stat['mode'] & 0170000) !== 0100000 || ($stat['mode'] & 07777) !== 0600
            || $stat['uid'] !== posix_geteuid() || $stat['nlink'] !== 1) { throw new RuntimeException; }
        $handle = @fopen($path, 'rb');
        if ($handle === false) { throw new RuntimeException; }
        try {
            $opened = self::file($path, $handle);
            if ($opened['dev'] !== $stat['dev'] || $opened['ino'] !== $stat['ino']) { throw new RuntimeException; }
            return $handle;
        } catch (Throwable $error) { fclose($handle); throw $error; }
    }

    private static function json(string $path): array
    {
        $handle = self::openPrivate($path);
        try {
            $before = self::file($path, $handle);
            if ($before['size'] < 1 || $before['size'] > 4096) { throw new RuntimeException; }
            $bytes = stream_get_contents($handle, 4097);
            if (! is_string($bytes) || strlen($bytes) !== $before['size'] || self::file($path, $handle) !== $before) { throw new RuntimeException; }
            $decoded = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) { throw new RuntimeException; }
            return [$decoded, hash('sha256', $bytes), $before];
        } finally { fclose($handle); }
    }

    private static function sameJson(string $path, string $hash, array $state): void
    {
        [, $currentHash, $currentState] = self::json($path);
        if ($currentState !== $state || ! hash_equals($hash, $currentHash)) { throw new RuntimeException; }
    }

    private static function writeNew(string $path, #[\SensitiveParameter] string $bytes): array
    {
        $mask = umask(0077);
        $handle = null;
        try {
            $handle = @fopen($path, 'x+b');
            if ($handle === false) { $handle = null; throw new RuntimeException; }
            self::file($path, $handle);
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = @fwrite($handle, substr($bytes, $offset));
                if ($written === false || $written === 0) { throw new RuntimeException; }
                $offset += $written;
            }
            if (! @fflush($handle) || ! @fsync($handle)) { throw new RuntimeException; }
            $state = self::file($path, $handle);
            if (! @fclose($handle)) { throw new RuntimeException; }
            $handle = null;
            return $state;
        } finally {
            if (is_resource($handle)) { @fclose($handle); }
            umask($mask);
        }
    }

    private static function lock(string $instance, array $state): mixed
    {
        self::sameInstance($instance, $state);
        $path = $instance.'/'.self::LOCK;
        $mask = umask(0077);
        try { $handle = @fopen($path, 'x+b'); } finally { umask($mask); }
        if ($handle === false) { $handle = self::openPrivate($path); }
        try {
            if (self::file($path, $handle)['size'] !== 0 || ! @flock($handle, LOCK_EX | LOCK_NB)) { throw new RuntimeException; }
            self::sameInstance($instance, $state);
            self::file($path, $handle);
            return $handle;
        } catch (Throwable $error) { fclose($handle); throw $error; }
    }

    private static function discardNew(string $instance, array $state, array $created): void
    {
        try {
            foreach (array_reverse($created, true) as $name => $expected) {
                self::sameInstance($instance, $state);
                $handle = self::openPrivate($instance.'/'.$name);
                try {
                    if (self::file($instance.'/'.$name, $handle) !== $expected || ! @unlink($instance.'/'.$name)) { return; }
                } finally { fclose($handle); }
            }
        } catch (Throwable) { /* Retain private partial files; never remove closure/lock. */ }
    }
}
