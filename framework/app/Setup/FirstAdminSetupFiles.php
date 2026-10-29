<?php

namespace App\Setup;

use App\LegacyDatabase\AccountLifecycleV5References;
use App\LegacyDatabase\IntakePolicyV4References;
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\RuntimeAdmission;
use App\LegacyDatabase\RuntimeDatabase;
use PDO;
use RuntimeException;
use Throwable;

/** Local operator capability files only; no web provisioning or account creation. */
final class FirstAdminSetupFiles
{
    public const PROFILE = 'setup-profile.json';
    public const CLOSED = 'setup-closed';
    private const TOKEN = 'operator-setup-token.txt';
    private const LOCK = 'setup-operator.lock';

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
            foreach ([self::CLOSED, self::PROFILE, self::TOKEN] as $name) {
                clearstatcache(true, $instance.'/'.$name);
                if (@lstat($instance.'/'.$name) !== false) {
                    throw new RuntimeException;
                }
            }
            [$manifest, $manifestHash, $manifestState] = self::json($instance.'/runtime-admission.json');
            $admission = RuntimeAdmission::fromConfiguration($manifest);
            if (! in_array($admission->schemaProfile, ['intake-policy-v4', 'account-lifecycle-v5'], true)
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
            if ($admission->schemaProfile === 'account-lifecycle-v5') {
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
