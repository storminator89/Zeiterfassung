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

/** First installation ONLY. Never reads/replaces an existing key or activates it. */
final class FreshApplicationKeyFiles
{
    private const FILE = 'framework-app-key.env';

    public static function createNew(string $instance): array
    {
        if (PHP_SAPI !== 'cli' || ! function_exists('posix_geteuid') || ! function_exists('fsync')) {
            throw new RuntimeException('New application-key preparation requires the local Unix CLI.');
        }
        $state = self::instance($instance);
        $handle = null;
        $fileState = null;
        $complete = false;
        $mask = umask(0077);
        try {
            $path = $instance.'/'.self::FILE;
            clearstatcache(true, $path);
            if (@lstat($path) !== false) { throw new RuntimeException; }
            [$manifest, $hash, $manifestState] = self::manifest($instance);
            $admission = RuntimeAdmission::fromConfiguration($manifest);
            if (! in_array($admission->schemaProfile, ['intake-policy-v4', 'account-lifecycle-v5'], true)
                || $admission->path !== $instance.'/application.sqlite') {
                throw new RuntimeException;
            }
            $root = dirname(__DIR__, 3);
            $files = new OfflineFiles([$root, $root.'/framework/public']);
            $before = $files->snapshot($admission->path);
            self::assertEmptyReadSnapshot($admission, $files, $root);
            // The void helper's entire scope has ended: no PDO/statement can
            // escape into this stopped-file snapshot or subsequent allocation.
            if ($files->snapshot($admission->path) !== $before || self::instance($instance) !== $state) { throw new RuntimeException; }
            [, $currentHash, $currentState] = self::manifest($instance);
            if ($currentState !== $manifestState || ! hash_equals($hash, $currentHash)) { throw new RuntimeException; }

            // Reserve before CSPRNG: any existing file/link/racing creator fails
            // without reading its content, generating a replacement or truncation.
            $handle = @fopen($path, 'x+b');
            if ($handle === false) { $handle = null; throw new RuntimeException; }
            $fileState = self::file($path, $handle);
            if (self::instance($instance) !== $state) { throw new RuntimeException; }
            $key = random_bytes(32); // AES-256-CBC; independent of all native/token keys.
            try {
                $line = 'FRAMEWORK_APP_KEY=base64:'.base64_encode($key)."\n";
                self::write($handle, $line);
            } finally { unset($key, $line); }
            if (self::file($path, $handle) !== $fileState || self::instance($instance) !== $state) { throw new RuntimeException; }
            [, $currentHash, $currentState] = self::manifest($instance);
            if ($currentState !== $manifestState || ! hash_equals($hash, $currentHash)) { throw new RuntimeException; }
            if (! @fclose($handle)) { throw new RuntimeException; }
            $handle = null;
            $complete = true;

            return ['file_created' => true, 'environment_file' => $path, 'environment_variable' => 'FRAMEWORK_APP_KEY',
                'runtime_configured' => false, 'server_started' => false, 'account_created' => false];
        } catch (Throwable) {
            throw new RuntimeException('New application-key preparation not confirmed. Keep the instance private and inspect locally; no automatic retry.');
        } finally {
            if (! $complete && is_resource($handle) && $fileState !== null) {
                try {
                    if (self::instance($instance) === $state && self::file($instance.'/'.self::FILE, $handle) === $fileState) {
                        @unlink($instance.'/'.self::FILE); // Only the held exclusively NEW identity.
                    }
                } catch (Throwable) { /* Retain private uncertain files; no raw logs. */ }
            }
            if (is_resource($handle)) { @fclose($handle); }
            umask($mask);
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

    private static function write(mixed $handle, #[\SensitiveParameter] string $bytes): void
    {
        $offset = 0;
        while ($offset < strlen($bytes)) {
            $written = @fwrite($handle, substr($bytes, $offset));
            if ($written === false || $written === 0) { throw new RuntimeException; }
            $offset += $written;
        }
        // Never read the generated key back or return a value/fingerprint.
        if (! @fflush($handle) || ! @fsync($handle)) { throw new RuntimeException; }
        $written = @fstat($handle);
        if ($written === false || $written['size'] !== strlen($bytes)) { throw new RuntimeException; }
    }

    private static function instance(string $path): array
    {
        if ($path === '' || $path[0] !== '/' || str_contains($path, "\0")
            || array_intersect(explode('/', substr($path, 1)), ['', '.', '..']) !== []
            || preg_match('/^chrono-fresh-[a-f0-9]{24}$/D', basename($path)) !== 1) { throw new RuntimeException; }
        $current = '';
        self::ancestor('/');
        foreach (explode('/', substr($path, 1)) as $part) { $current .= '/'.$part; $stat = self::ancestor($current); }
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
        if ($stat === false || ($stat['mode'] & 0170000) !== 0040000 || ! in_array($stat['uid'], [0, posix_geteuid()], true)
            || (($stat['mode'] & 0022) !== 0 && ($stat['mode'] & 01000) === 0)) { throw new RuntimeException; }
        return $stat;
    }

    /** Identity deliberately excludes legitimate size/time changes during NEW write. */
    private static function file(string $path, mixed $handle): array
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        $opened = is_resource($handle) ? @fstat($handle) : false;
        $keys = array_flip(['dev', 'ino', 'uid', 'mode', 'nlink']);
        if ($stat === false || $opened === false || ($stat['mode'] & 0170000) !== 0100000
            || ($stat['mode'] & 07777) !== 0600 || $stat['uid'] !== posix_geteuid() || $stat['nlink'] !== 1
            || realpath($path) !== $path || array_intersect_key($stat, $keys) !== array_intersect_key($opened, $keys)) { throw new RuntimeException; }
        return array_intersect_key($stat, $keys);
    }

    private static function manifest(string $instance): array
    {
        $path = $instance.'/runtime-admission.json'; // Fixed NONSECRET metadata, never keyfile.
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || ($stat['mode'] & 0170000) !== 0100000 || ($stat['mode'] & 07777) !== 0600
            || $stat['uid'] !== posix_geteuid() || $stat['nlink'] !== 1) { throw new RuntimeException; }
        $handle = @fopen($path, 'rb');
        if ($handle === false) { throw new RuntimeException; }
        try {
            $identity = self::file($path, $handle);
            $before = fstat($handle);
            if ($before['dev'] !== $stat['dev'] || $before['ino'] !== $stat['ino'] || $before['size'] < 1 || $before['size'] > 4096) { throw new RuntimeException; }
            $bytes = stream_get_contents($handle, 4097);
            $after = fstat($handle);
            if (! is_string($bytes) || strlen($bytes) !== $before['size'] || $before['size'] !== $after['size']
                || $before['mtime'] !== $after['mtime'] || $before['ctime'] !== $after['ctime'] || self::file($path, $handle) !== $identity) { throw new RuntimeException; }
            $decoded = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($decoded) || $bytes !== json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n") { throw new RuntimeException; }
            return [$decoded, hash('sha256', $bytes), $identity];
        } finally { fclose($handle); }
    }
}
