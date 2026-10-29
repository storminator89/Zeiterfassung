<?php

namespace App\LegacyDatabase;

use LogicException;
use RuntimeException;
use Throwable;

/** Fixed NEW Laravel namespace only; no opener, adoption, credential or bypass flag. */
final class AccountLifecycleRuntimeCohort
{
    /** Private identity metadata only; Runtime rechecks it before/after source opens. */
    public static function requireCurrent(RuntimeAdmission $admission, OfflineFiles $files): array
    {
        try {
            if ($admission->schemaProfile !== 'account-lifecycle-v5' || ! function_exists('posix_geteuid')) {
                throw new RuntimeException;
            }
            $database = $files->validate($admission->path);
            $directory = dirname($database);
            if (basename($database) !== 'application.sqlite'
                || preg_match('/\Achrono-fresh-[a-f0-9]{24}\z/D', basename($directory)) !== 1) {
                throw new RuntimeException;
            }
            $directoryState = self::directory($directory);
            $databaseState = self::file($database);
            $manifestPath = $directory.'/runtime-admission.json';
            $files->validate($manifestPath);
            $manifestState = self::file($manifestPath);
            $handle = @fopen($manifestPath, 'rb');
            if ($handle === false) { throw new RuntimeException; }
            try {
                if (self::file($manifestPath, $handle) !== $manifestState) { throw new RuntimeException; }
                $before = fstat($handle);
                if ($before === false || $before['size'] < 1 || $before['size'] > 4096) { throw new RuntimeException; }
                $bytes = stream_get_contents($handle, 4097);
                $after = fstat($handle);
                if (! is_string($bytes) || strlen($bytes) !== $before['size'] || $after === false
                    || $after['size'] !== $before['size'] || $after['mtime'] !== $before['mtime']
                    || $after['ctime'] !== $before['ctime'] || self::file($manifestPath, $handle) !== $manifestState) {
                    throw new RuntimeException;
                }
                $manifest = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($manifest)
                    || $bytes !== json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n"
                    || (array) RuntimeAdmission::fromConfiguration($manifest) !== (array) $admission) {
                    throw new RuntimeException;
                }
                foreach (['device', 'inode', 'schema_version', 'foreign_keys', 'synchronous'] as $field) {
                    if (! is_int($manifest[$field] ?? null)) { throw new RuntimeException; }
                }
            } finally {
                if (! @fclose($handle)) { throw new RuntimeException; }
            }
            if (self::directory($directory) !== $directoryState || self::file($database) !== $databaseState
                || self::file($manifestPath) !== $manifestState) { throw new RuntimeException; }
            $files->validate($database);
            $files->validate($manifestPath);

            // Ordinary DB writes and key/setup file creation do not change these
            // identities. Neither source data nor secret files are read here.
            return ['directory' => $directoryState, 'database' => $databaseState,
                'manifest' => $manifestState, 'manifest_sha256' => hash('sha256', $bytes)];
        } catch (Throwable) {
            throw new LogicException('The canonical NEW V5 runtime cohort could not be confirmed.');
        }
    }

    private static function directory(string $path): array
    {
        $current = '';
        foreach (['/', ...array_filter(explode('/', $path), static fn (string $part): bool => $part !== '')] as $part) {
            $current = $part === '/' ? '/' : rtrim($current, '/').'/'.$part;
            clearstatcache(true, $current);
            $state = @lstat($current);
            if ($state === false || ($state['mode'] & 0170000) !== 0040000
                || ! in_array($state['uid'], [0, posix_geteuid()], true)
                || (($state['mode'] & 0022) !== 0 && ($state['mode'] & 01000) === 0)) {
                throw new RuntimeException;
            }
        }
        if (realpath($path) !== $path || ($state['mode'] & 07777) !== 0700 || $state['uid'] !== posix_geteuid()) {
            throw new RuntimeException;
        }

        return array_intersect_key($state, array_flip(['dev', 'ino', 'uid', 'mode']));
    }

    private static function file(string $path, mixed $handle = null): array
    {
        clearstatcache(true, $path);
        $state = @lstat($path);
        $keys = array_flip(['dev', 'ino', 'uid', 'mode', 'nlink']);
        if ($state === false || ($state['mode'] & 0170000) !== 0100000 || ($state['mode'] & 07777) !== 0600
            || $state['uid'] !== posix_geteuid() || $state['nlink'] !== 1 || realpath($path) !== $path) {
            throw new RuntimeException;
        }
        if ($handle !== null) {
            $opened = @fstat($handle);
            if ($opened === false || array_intersect_key($opened, $keys) !== array_intersect_key($state, $keys)) {
                throw new RuntimeException;
            }
        }

        return array_intersect_key($state, $keys);
    }
}
