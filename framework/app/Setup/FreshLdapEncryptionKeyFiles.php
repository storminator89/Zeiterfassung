<?php

namespace App\Setup;

use App\LegacyDatabase\IntakePolicyV4References;
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\RuntimeAdmission;
use App\LegacyDatabase\RuntimeDatabase;
use PDO;
use RuntimeException;
use Throwable;

/** NEW empty installation only. No existing root read/reset/repair, Native bootstrap or activation. */
final class FreshLdapEncryptionKeyFiles
{
    private const ROOT = 'encryption.key';
    private const METADATA = 'ldap-settings-root-admission.json';
    // Same persistent lock identity as FirstAdminSetupFiles; never unlinked.
    private const LOCK = 'setup-operator.lock';

    /** Explicit trusted CLI composition. NEVER returns key bytes or their fingerprint. */
    public static function createNew(string $instance, array $forbiddenRoots): array
    {
        if (PHP_SAPI !== 'cli' || ! function_exists('posix_geteuid') || ! function_exists('fsync')) {
            throw new RuntimeException('New LDAP root preparation requires the local Unix CLI.');
        }
        if ($forbiddenRoots === []) { throw new RuntimeException; }
        foreach ($forbiddenRoots as $path) {
            if (! is_string($path) || $path === '' || $path[0] !== '/' || str_contains($path, "\0")
                || array_intersect(explode('/', substr($path, 1)), ['', '.', '..']) !== []) { throw new RuntimeException; }
        }
        $state = self::instance($instance, $forbiddenRoots);
        $lock = self::lock($instance, $state, $forbiddenRoots);
        $reader = $pdo = $rootHandle = $metadataHandle = null;
        $mask = umask(0077);
        try {
            self::sameInstance($instance, $state, $forbiddenRoots);
            self::requireUnstarted($instance); // Includes any link/type/partial artifact, without reading it.
            [$manifest, $hash, $manifestState] = self::manifest($instance);
            $admission = RuntimeAdmission::fromConfiguration($manifest);
            if ($admission->schemaProfile !== 'intake-policy-v4' || $admission->path !== $instance.'/application.sqlite') {
                throw new RuntimeException;
            }
            $root = dirname(__DIR__, 3);
            $files = new OfflineFiles([$root, $root.'/framework/public', ...$forbiddenRoots]);
            $before = $files->snapshot($admission->path);
            $reader = (new RuntimeDatabase($admission, $files))->readConnection($admission->configuration(), 'chrono');
            $pdo = $reader->getPdo();
            $pdo->beginTransaction(); // Short read-only fresh-state check; no raw snapshot while this handle lives.
            $mapping = json_decode(file_get_contents($root.'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($mapping['tables'] as $table) {
                $name = $table['name']; // Trusted fixed source table inventory, never CLI input.
                if (in_array($name, ['app_migrations', 'pause_settings', 'terminal_settings', 'terminal_sites'], true)) { continue; }
                $exists = $pdo->prepare("SELECT 1 FROM sqlite_schema WHERE type='table' AND name=?");
                $exists->execute([$name]);
                $present = $exists->fetchColumn() !== false;
                $exists->closeCursor();
                $exists = null; // Release the statement's PDO reference before cold file inspection.
                if ($present && (int) $pdo->query('SELECT COUNT(*) FROM "'.$name.'"')->fetchColumn() !== 0) {
                    throw new RuntimeException;
                }
            }
            // Exact canonical empty V4 and default policy/seeds, not merely zero users.
            IntakePolicyV4References::build($mapping)->requireFresh($pdo);
            $pdo->rollBack();
            $reader->disconnect();
            $pdo = $reader = null;
            // OfflineFiles opens/closes the DB as a raw file. POSIX locks can be
            // dropped by ANY same-process close on that inode, so it is used
            // ONLY after the complete PDO/reader/statement lifetime has ended.
            // Filesystem/DB stability from here relies on the stopped-only contract.
            if ($files->snapshot($admission->path) !== $before) { throw new RuntimeException; }
            self::sameInstance($instance, $state, $forbiddenRoots);
            self::sameManifest($instance, $hash, $manifestState);
            self::requireUnstarted($instance);
            $path = $instance.'/'.self::ROOT;
            $rootHandle = @fopen($path, 'x+b'); // Reserve NEW identity BEFORE drawing entropy.
            if ($rootHandle === false) { $rootHandle = null; throw new RuntimeException; }
            $rootState = self::file($path, $rootHandle);
            self::sameInstance($instance, $state, $forbiddenRoots);
            $key = bin2hex(random_bytes(32)); // Independent native-format root, never APP_KEY/JWT/bind password.
            try {
                self::write($path, $rootHandle, $rootState, $key);
                $digest = hash('sha256', $key); // Private metadata file only, never result/stdout/log.
            } finally { unset($key); }
            self::sameInstance($instance, $state, $forbiddenRoots);
            self::sameManifest($instance, $hash, $manifestState);
            self::requireNoSetup($instance);
            $metadata = ['version' => 1, 'database_profile_sha256' => self::databaseProfileDigest($admission),
                'settings_root' => ['path' => $path, 'device' => $rootState['dev'], 'inode' => $rootState['ino'],
                    'owner' => $rootState['uid'], 'mode' => 0600, 'parent_device' => $state['dev'],
                    'parent_inode' => $state['ino'], 'sha256' => $digest]];
            unset($digest);
            $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
            unset($metadata);
            if (strlen($json) > 4096 || self::file($path, $rootHandle) !== $rootState) { throw new RuntimeException; }
            $metadataPath = $instance.'/'.self::METADATA;
            $metadataHandle = @fopen($metadataPath, 'x+b'); // Private nonsecret admission LAST, never overwrite.
            if ($metadataHandle === false) { $metadataHandle = null; throw new RuntimeException; }
            $metadataState = self::file($metadataPath, $metadataHandle);
            try { self::write($metadataPath, $metadataHandle, $metadataState, $json); }
            finally { unset($json); }
            self::sameInstance($instance, $state, $forbiddenRoots);
            self::sameManifest($instance, $hash, $manifestState);
            self::requireNoSetup($instance);
            $rootStatus = @fstat($rootHandle);
            if ($rootStatus === false || $rootStatus['size'] !== 64 || self::file($path, $rootHandle) !== $rootState
                || self::file($metadataPath, $metadataHandle) !== $metadataState
                || $files->snapshot($admission->path) !== $before) { throw new RuntimeException; }
            if (! @fclose($metadataHandle)) { throw new RuntimeException; }
            $metadataHandle = null;
            if (! @fclose($rootHandle)) { throw new RuntimeException; }
            $rootHandle = null;
            return ['prepared' => true, 'root_file' => $path, 'admission_metadata_file' => $metadataPath,
                'runtime_configured' => false, 'ldap_connected' => false, 'account_created' => false];
        } catch (Throwable) {
            // Even an exclusively created partial root is NEVER deleted/regenerated.
            // Keep the instance private; no guessed rollback or automatic retry.
            throw new RuntimeException('New LDAP root preparation not confirmed. Keep workers stopped and inspect the private instance locally; no automatic retry.');
        } finally {
            try { if ($pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); } $reader?->disconnect(); } catch (Throwable) {}
            $pdo = $reader = null;
            foreach ([$metadataHandle, $rootHandle] as $handle) { if (is_resource($handle)) { @fclose($handle); } }
            @flock($lock, LOCK_UN);
            @fclose($lock);
            umask($mask);
        }
    }

    /** Pure PUBLIC DB metadata digest, identical to the separate Sync reader contract. */
    public static function databaseProfileDigest(RuntimeAdmission $database): string
    {
        return hash('sha256', json_encode([
            'version' => 1, 'path' => $database->path, 'device' => $database->device, 'inode' => $database->inode,
            'schema_version' => $database->schemaVersion, 'schema_signature' => $database->schemaSignature,
            'foreign_keys' => $database->foreignKeys, 'journal_mode' => $database->journalMode, 'synchronous' => $database->synchronous,
            'schema_profile' => $database->schemaProfile, 'base_schema_signature' => $database->baseSchemaSignature,
            'user_audit_schema_signature' => $database->userAuditSchemaSignature,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function requireUnstarted(string $instance): void
    {
        self::requireNoSetup($instance);
        foreach ([self::ROOT, self::METADATA] as $name) {
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

    private static function instance(string $path, array $forbiddenRoots): array
    {
        if ($path === '' || $path[0] !== '/' || str_contains($path, "\0")
            || array_intersect(explode('/', substr($path, 1)), ['', '.', '..']) !== []
            || preg_match('/^chrono-fresh-[a-f0-9]{24}$/D', basename($path)) !== 1) { throw new RuntimeException; }
        $current = '';
        self::ancestor('/');
        foreach (explode('/', substr($path, 1)) as $part) { $current .= '/'.$part; $stat = self::ancestor($current); }
        if (realpath($path) !== $path || ($stat['mode'] & 07777) !== 0700 || $stat['uid'] !== posix_geteuid()) { throw new RuntimeException; }
        $root = dirname(__DIR__, 3);
        foreach ([$root, $root.'/framework/public', ...$forbiddenRoots] as $forbidden) {
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

    private static function sameInstance(string $path, array $state, array $forbiddenRoots): void
    {
        if (self::instance($path, $forbiddenRoots) !== $state) { throw new RuntimeException; }
    }

    /** Identity excludes legitimate size/time changes during exclusive NEW writes. */
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
        } catch (Throwable $error) { fclose($handle); throw $error; }
    }

    /** Fixed nonsecret manifest ONLY; never opens encryption.key or operator token. */
    private static function manifest(string $instance): array
    {
        $path = $instance.'/runtime-admission.json';
        $handle = self::openPrivate($path);
        try {
            $identity = self::file($path, $handle);
            $before = fstat($handle);
            if ($before['size'] < 1 || $before['size'] > 4096) { throw new RuntimeException; }
            $bytes = stream_get_contents($handle, 4097);
            $after = fstat($handle);
            if (! is_string($bytes) || strlen($bytes) !== $before['size'] || $before['size'] !== $after['size']
                || $before['mtime'] !== $after['mtime'] || $before['ctime'] !== $after['ctime']
                || self::file($path, $handle) !== $identity) { throw new RuntimeException; }
            $decoded = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($decoded) || $bytes !== json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n") {
                throw new RuntimeException;
            }
            return [$decoded, hash('sha256', $bytes), $identity];
        } finally { fclose($handle); }
    }

    private static function sameManifest(string $instance, string $hash, array $state): void
    {
        [, $currentHash, $currentState] = self::manifest($instance);
        if ($currentState !== $state || ! hash_equals($hash, $currentHash)) { throw new RuntimeException; }
    }

    private static function lock(string $instance, array $state, array $forbiddenRoots): mixed
    {
        self::sameInstance($instance, $state, $forbiddenRoots);
        $path = $instance.'/'.self::LOCK;
        $mask = umask(0077);
        try { $handle = @fopen($path, 'x+b'); } finally { umask($mask); }
        if ($handle === false) { $handle = self::openPrivate($path); }
        try {
            if (self::file($path, $handle) === [] || fstat($handle)['size'] !== 0
                || ! @flock($handle, LOCK_EX | LOCK_NB)) { throw new RuntimeException; }
            self::sameInstance($instance, $state, $forbiddenRoots);
            self::file($path, $handle);
            return $handle;
        } catch (Throwable $error) { fclose($handle); throw $error; }
    }

    private static function write(string $path, mixed $handle, array $identity, #[\SensitiveParameter] string $bytes): void
    {
        if (self::file($path, $handle) !== $identity || fstat($handle)['size'] !== 0) { throw new RuntimeException; }
        $offset = 0;
        while ($offset < strlen($bytes)) {
            $written = @fwrite($handle, substr($bytes, $offset));
            if ($written === false || $written === 0) { throw new RuntimeException; }
            $offset += $written;
        }
        // Never read the generated key back, truncate, chmod or remove a partial artifact.
        if (! @fflush($handle) || ! @fsync($handle) || fstat($handle)['size'] !== strlen($bytes)
            || self::file($path, $handle) !== $identity) { throw new RuntimeException; }
    }
}
