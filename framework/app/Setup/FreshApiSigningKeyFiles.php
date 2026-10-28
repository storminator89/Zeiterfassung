<?php

namespace App\Setup;

use App\Api\ExistingApiKeyAdmission;
use App\LegacyDatabase\IntakePolicyV4References;
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\RuntimeAdmission;
use App\LegacyDatabase\RuntimeDatabase;
use PDO;
use RuntimeException;
use Throwable;

/** NEW empty installation only; never reads/adopts/replaces an existing API key. */
final class FreshApiSigningKeyFiles
{
    private const KEY = 'jwt.key';
    private const ENVIRONMENT = 'framework-api-key.env';
    // Shared with first-admin and LDAP preparation. Persistent, never unlinked.
    private const LOCK = 'setup-operator.lock';

    /** Local stopped-only operator action. No key, fingerprint, profile or path in its result. */
    public static function createNew(#[\SensitiveParameter] string $instance): array
    {
        if (PHP_SAPI !== 'cli' || ! function_exists('posix_geteuid') || ! function_exists('fsync')) {
            throw new RuntimeException('New API-key preparation requires the local Unix CLI.');
        }
        $state = self::instance($instance);
        $lock = self::lock($instance, $state);
        $keyHandle = $environmentHandle = null;
        $mask = umask(0077);
        try {
            self::requireUnstarted($instance);
            [$manifest, $manifestHash, $manifestState] = self::manifest($instance);
            $admission = RuntimeAdmission::fromConfiguration($manifest);
            if ($admission->schemaProfile !== 'intake-policy-v4' || $admission->path !== $instance.'/application.sqlite') {
                throw new RuntimeException;
            }
            $root = dirname(__DIR__, 3);
            $files = new OfflineFiles([$root, $root.'/framework/public']);
            $before = $files->snapshot($admission->path);
            self::requireEmptyReadSnapshot($admission, $files, $root);
            // The complete reader/statement scope has ended before cold hashing.
            if ($files->snapshot($admission->path) !== $before) { throw new RuntimeException; }
            self::sameInstance($instance, $state);
            self::sameManifest($instance, $manifestHash, $manifestState);
            self::requireUnstarted($instance);

            // Reserve BOTH new identities before entropy. Never read/truncate an
            // existing object, and never erase partial/uncertain output on failure.
            $keyPath = $instance.'/'.self::KEY;
            $keyHandle = @fopen($keyPath, 'x+b');
            if ($keyHandle === false) { $keyHandle = null; throw new RuntimeException; }
            $keyState = self::file($keyPath, $keyHandle);
            $environmentPath = $instance.'/'.self::ENVIRONMENT;
            $environmentHandle = @fopen($environmentPath, 'x+b');
            if ($environmentHandle === false) { $environmentHandle = null; throw new RuntimeException; }
            $environmentState = self::file($environmentPath, $environmentHandle);
            self::sameInstance($instance, $state);
            self::sameManifest($instance, $manifestHash, $manifestState);
            self::requireNoSetup($instance);

            $key = bin2hex(random_bytes(32)); // Independent original API ASCII64 format.
            try {
                self::write($keyPath, $keyHandle, $keyState, $key);
                $metadata = [
                    'version' => 1, 'path' => $keyPath, 'device' => $keyState['dev'], 'inode' => $keyState['ino'],
                    'owner' => $keyState['uid'], 'mode' => 0600, 'parent_device' => $state['dev'],
                    'parent_inode' => $state['ino'], 'sha256' => hash('sha256', $key),
                    'database_profile_sha256' => ExistingApiKeyAdmission::databaseProfileDigest($admission),
                ];
            } finally { unset($key); }
            // Reuse the existing ten-field validator and lazy read-only reader;
            // this adds no token, signature, metadata-adoption or budget engine.
            ExistingApiKeyAdmission::fromConfiguration($metadata, $admission);
            try {
                $json = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $encoded = base64_encode($json);
                if (strlen($encoded) > 8192) { throw new RuntimeException; }
                $line = 'FRAMEWORK_FRESH_API_KEY_ADMISSION='.$encoded."\n";
                // Base64 is transport, NOT encryption. This private file contains
                // metadata/fingerprint only, never the signing key's bytes.
                self::write($environmentPath, $environmentHandle, $environmentState, $line);
            } finally { unset($metadata, $json, $encoded, $line); }
            self::sameInstance($instance, $state);
            self::sameManifest($instance, $manifestHash, $manifestState);
            self::requireNoSetup($instance);
            $writtenKey = @fstat($keyHandle);
            if ($writtenKey === false || self::file($keyPath, $keyHandle) !== $keyState
                || self::file($environmentPath, $environmentHandle) !== $environmentState
                || $writtenKey['size'] !== 64) { throw new RuntimeException; }
            if (! @fclose($keyHandle)) { throw new RuntimeException; }
            $keyHandle = null;
            if (! @fclose($environmentHandle)) { throw new RuntimeException; }
            $environmentHandle = null;
            return ['api_key_created' => true, 'metadata_environment_created' => true,
                'runtime_configured' => false, 'server_started' => false, 'account_created' => false];
        } catch (Throwable) {
            throw new RuntimeException('New API-key preparation not confirmed. Keep workers stopped and inspect privately; no automatic retry.');
        } finally {
            if (is_resource($keyHandle)) { @fclose($keyHandle); }
            if (is_resource($environmentHandle)) { @fclose($environmentHandle); }
            @flock($lock, LOCK_UN);
            @fclose($lock);
            umask($mask);
        }
    }

    /** No reader, statement, PDO or anonymous reference escapes into cold hashing. */
    private static function requireEmptyReadSnapshot(RuntimeAdmission $admission, OfflineFiles $files, string $root): void
    {
        $reader = $pdo = $statement = null;
        try {
            $reader = (new RuntimeDatabase($admission, $files))->readConnection($admission->configuration(), 'chrono');
            $pdo = $reader->getPdo();
            if (! $pdo->beginTransaction()) { throw new RuntimeException; }
            $mapping = json_decode(file_get_contents($root.'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($mapping['tables'] as $table) {
                $name = $table['name']; // Fixed source inventory, never operator/request SQL.
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
                        if ($statement->fetchColumn() !== 0) { throw new RuntimeException; }
                    } finally {
                        try { if (! $statement->closeCursor()) { throw new RuntimeException; } }
                        finally { $statement = null; }
                    }
                }
            }
            // Physical canonical countertable + EMPTY counters, not optional-table
            // signature normalization or merely an empty users table.
            IntakePolicyV4References::build($mapping)->requireFreshLoginBudget($pdo);
            if (! $pdo->rollBack() || $pdo->inTransaction()) { throw new RuntimeException; }
        } finally {
            try {
                if ($pdo instanceof PDO && $pdo->inTransaction() && ! $pdo->rollBack()) { throw new RuntimeException; }
            } finally {
                try { $reader?->disconnect(); }
                finally { $statement = $pdo = $reader = null; }
            }
        }
    }

    private static function requireUnstarted(string $instance): void
    {
        self::requireNoSetup($instance);
        foreach ([self::KEY, self::ENVIRONMENT] as $name) {
            clearstatcache(true, $instance.'/'.$name);
            if (@lstat($instance.'/'.$name) !== false) { throw new RuntimeException; }
        }
    }

    private static function requireNoSetup(string $instance): void
    {
        foreach (['setup-closed', 'setup-profile.json', 'operator-setup-token.txt'] as $name) {
            clearstatcache(true, $instance.'/'.$name);
            if (@lstat($instance.'/'.$name) !== false) { throw new RuntimeException; }
        }
    }

    private static function instance(string $path): array
    {
        if ($path === '' || $path[0] !== '/' || str_contains($path, "\0")
            || array_intersect(explode('/', substr($path, 1)), ['', '.', '..']) !== []
            || preg_match('/^chrono-fresh-[a-f0-9]{24}$/D', basename($path)) !== 1) { throw new RuntimeException; }
        $current = '';
        self::ancestor('/');
        foreach (explode('/', substr($path, 1)) as $part) { $current .= '/'.$part; $stat = self::ancestor($current); }
        if (@realpath($path) !== $path || ($stat['mode'] & 07777) !== 0700 || $stat['uid'] !== posix_geteuid()) { throw new RuntimeException; }
        $root = dirname(__DIR__, 3);
        foreach ([$root, $root.'/framework/public'] as $forbidden) {
            $forbidden = @realpath($forbidden);
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

    private static function sameInstance(string $path, array $state): void
    {
        if (self::instance($path) !== $state) { throw new RuntimeException; }
    }

    /** Excludes legitimate size/time changes during exclusive NEW writes. */
    private static function file(string $path, mixed $handle): array
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        $opened = is_resource($handle) ? @fstat($handle) : false;
        $keys = array_flip(['dev', 'ino', 'uid', 'mode', 'nlink']);
        if ($stat === false || $opened === false || ($stat['mode'] & 0170000) !== 0100000
            || ($stat['mode'] & 07777) !== 0600 || $stat['uid'] !== posix_geteuid() || $stat['nlink'] !== 1
            || @realpath($path) !== $path || array_intersect_key($stat, $keys) !== array_intersect_key($opened, $keys)) { throw new RuntimeException; }
        return array_intersect_key($stat, $keys);
    }

    private static function openPrivate(string $path): mixed
    {
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
        } catch (Throwable $error) { @fclose($handle); throw $error; }
    }

    /** Fixed admission JSON only, never jwt.key or another key/token file. */
    private static function manifest(string $instance): array
    {
        $path = $instance.'/runtime-admission.json';
        $handle = self::openPrivate($path);
        try {
            $identity = self::file($path, $handle);
            $before = fstat($handle);
            if ($before === false || $before['size'] < 1 || $before['size'] > 4096) { throw new RuntimeException; }
            $bytes = stream_get_contents($handle, 4097);
            $after = fstat($handle);
            if ($after === false || ! is_string($bytes) || strlen($bytes) !== $before['size'] || $before['size'] !== $after['size']
                || $before['mtime'] !== $after['mtime'] || $before['ctime'] !== $after['ctime']
                || self::file($path, $handle) !== $identity) { throw new RuntimeException; }
            $decoded = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($decoded) || $bytes !== json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n") {
                throw new RuntimeException;
            }
            return [$decoded, hash('sha256', $bytes), $identity];
        } finally { if (! @fclose($handle)) { throw new RuntimeException; } }
    }

    private static function sameManifest(string $instance, string $hash, array $state): void
    {
        [, $currentHash, $currentState] = self::manifest($instance);
        if ($currentState !== $state || ! hash_equals($hash, $currentHash)) { throw new RuntimeException; }
    }

    private static function lock(string $instance, array $state): mixed
    {
        self::sameInstance($instance, $state);
        $path = $instance.'/'.self::LOCK;
        $mask = umask(0077);
        try { $handle = @fopen($path, 'x+b'); } finally { umask($mask); }
        if ($handle === false) { $handle = self::openPrivate($path); }
        try {
            self::file($path, $handle);
            $opened = @fstat($handle);
            if ($opened === false || $opened['size'] !== 0 || ! @flock($handle, LOCK_EX | LOCK_NB)) { throw new RuntimeException; }
            self::sameInstance($instance, $state);
            self::file($path, $handle);
            return $handle;
        } catch (Throwable $error) { @fclose($handle); throw $error; }
    }

    private static function write(string $path, mixed $handle, array $identity, #[\SensitiveParameter] string $bytes): void
    {
        $offset = 0;
        while ($offset < strlen($bytes)) {
            $written = @fwrite($handle, substr($bytes, $offset));
            if ($written === false || $written === 0) { throw new RuntimeException; }
            $offset += $written;
        }
        if (! @fflush($handle) || ! @fsync($handle) || self::file($path, $handle) !== $identity) { throw new RuntimeException; }
        $written = @fstat($handle);
        if ($written === false || $written['size'] !== strlen($bytes)) { throw new RuntimeException; }
    }
}
