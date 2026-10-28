<?php

namespace App\LegacyDatabase;

use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Database\SQLiteConnection;
use PDO;
use Pdo\Sqlite;
use RuntimeException;
use Throwable;

/** NEW empty private files only; not an existing-data installer or web activation. */
final class FreshApplicationFiles
{
    /** No database target, schema, secret, account, callback or activation inputs. */
    public static function createNew(string $privateParent): array
    {
        if (PHP_SAPI !== 'cli' || ! function_exists('posix_geteuid') || ! function_exists('fsync')) {
            throw new RuntimeException('Fresh file preparation requires the local Unix CLI.');
        }
        $root = dirname(__DIR__, 3);
        $parent = self::directory($privateParent);
        foreach ([$root, $root.'/framework/public'] as $forbidden) {
            $forbidden = realpath($forbidden);
            if ($forbidden === false || $privateParent === $forbidden
                || str_starts_with($privateParent, $forbidden.'/')) {
                throw new RuntimeException('The existing private parent must be outside the checkout and webroots.');
            }
        }

        $oldMask = umask(0077);
        $directory = null;
        $directoryState = null;
        $created = [];
        $databaseHandle = $manifestHandle = null;
        $source = $pdo = $read = $connection = $reader = null;
        $complete = false;
        $phase = 'new private allocation';
        try {
            // No target choice or retry: mkdir and x+b reserve only NEW names.
            $directory = $privateParent.'/chrono-fresh-'.bin2hex(random_bytes(12));
            self::sameDirectory($privateParent, $parent);
            if (! @mkdir($directory, 0700)) {
                $directory = null;
                throw new RuntimeException;
            }
            $directoryState = self::directory($directory);
            foreach (['sessions', 'cache', 'locks', 'compiled'] as $child) {
                if (! @mkdir($directory.'/'.$child, 0700)) {
                    throw new RuntimeException;
                }
                $created[$child] = ['directory' => true, 'state' => self::directory($directory.'/'.$child)];
            }

            $path = $directory.'/application.sqlite';
            $databaseHandle = @fopen($path, 'x+b');
            if ($databaseHandle === false) {
                $databaseHandle = null;
                throw new RuntimeException;
            }
            $databaseState = self::file($path, $databaseHandle);
            $created['application.sqlite'] = ['directory' => false, 'state' => $databaseState];
            $guard = static function (PDO $opened) use ($path, $databaseHandle, $databaseState, $privateParent, $parent, $directory, $directoryState): void {
                self::sameDirectory($privateParent, $parent);
                self::sameDirectory($directory, $directoryState);
                self::sameFile($path, $databaseHandle, $databaseState);
                $databases = $opened->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
                $main = array_values(array_filter($databases, static fn (array $row): bool => $row['name'] === 'main'));
                if (count($main) !== 1 || $main[0]['file'] !== $path
                    || array_diff(array_column($databases, 'name'), ['main', 'temp']) !== []) {
                    throw new RuntimeException;
                }
                foreach ($databases as $database) {
                    if ($database['name'] === 'temp' && $database['file'] !== '') { throw new RuntimeException; }
                }
            };

            $phase = 'canonical empty memory source';
            $mapping = json_decode(file_get_contents($root.'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
            $references = IntakePolicyV4References::build($mapping);
            $source = (new SQLiteConnector)->connect(['database' => ':memory:', 'busy_timeout' => 5000]);
            $build = require $root.'/framework/database/fixtures/intake_policy_v4.php';
            $build($source, $mapping);
            $references->requireFresh($source);
            self::sameFile($path, $databaseHandle, $databaseState);
            // This source is a newly constructed EMPTY memory schema, never a
            // read-only runtime/business database or an operator-selected source.
            $phase = 'new empty file materialization';
            $source->exec('VACUUM INTO '.$source->quote($path));
            $source = null;
            self::sameFile($path, $databaseHandle, $databaseState);

            $configuration = [
                'database' => $path, 'prefix' => '', 'busy_timeout' => 5000,
                'transaction_mode' => 'IMMEDIATE', 'name' => 'chrono_fresh_intake_policy_v4',
                'options' => [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, Sqlite::ATTR_OPEN_FLAGS => Sqlite::OPEN_READWRITE],
            ];
            // No CREATE flag: only the just-reserved/materialized identity opens.
            $phase = 'new identity ledger registration';
            $pdo = (new SQLiteConnector)->connect($configuration);
            $guard($pdo);
            $connection = new SQLiteConnection($pdo, $path, '', $configuration);
            $registered = (new FreshIntakePolicyV4Installation($references))->registerFreshConnection($connection, $guard);
            if ($registered['registered'] !== true || $registered['already_registered'] !== false) {
                throw new RuntimeException;
            }
            $manifest = [
                'path' => $path, 'device' => $databaseState['dev'], 'inode' => $databaseState['ino'],
                'schema_version' => $registered['schema_version'],
                'schema_signature' => $registered['schema_signature'],
                'foreign_keys' => $pdo->query('PRAGMA foreign_keys')->fetchColumn(),
                'journal_mode' => $pdo->query('PRAGMA journal_mode')->fetchColumn(),
                'synchronous' => $pdo->query('PRAGMA synchronous')->fetchColumn(),
                'schema_profile' => 'intake-policy-v4',
                'base_schema_signature' => $registered['base_schema_signature'],
                'user_audit_schema_signature' => $registered['user_audit_schema_signature'],
            ];
            $guard($pdo);
            $connection->disconnect();
            $connection = $pdo = null;
            $phase = 'closed admitted read verification';
            $files = new OfflineFiles([$root, $root.'/framework/public']);
            $closed = $files->snapshot($path);
            $admission = RuntimeAdmission::fromConfiguration($manifest);
            $reader = (new RuntimeDatabase($admission, $files))->readConnection($admission->configuration(), 'chrono');
            $read = $reader->getPdo();
            $read->beginTransaction();
            $references->requireFresh($read);
            $read->rollBack();
            $reader->disconnect();
            $reader = $read = null;
            if ($files->snapshot($path) !== $closed) {
                throw new RuntimeException;
            }
            self::sameDirectory($privateParent, $parent);
            self::sameDirectory($directory, $directoryState);
            self::sameFile($path, $databaseHandle, $databaseState);

            // Nonsecret admission metadata is the LAST artifact. A partial or
            // absent file never activates anything and is not a resumable install.
            $phase = 'final private admission metadata';
            $manifestPath = $directory.'/runtime-admission.json';
            $manifestHandle = @fopen($manifestPath, 'x+b');
            if ($manifestHandle === false) {
                $manifestHandle = null;
                throw new RuntimeException;
            }
            $manifestState = self::file($manifestPath, $manifestHandle);
            $created['runtime-admission.json'] = ['directory' => false, 'state' => $manifestState];
            $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
            $offset = 0;
            while ($offset < strlen($json)) {
                $written = @fwrite($manifestHandle, substr($json, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException;
                }
                $offset += $written;
            }
            if (! @fflush($manifestHandle) || ! @fsync($manifestHandle)) {
                throw new RuntimeException;
            }
            self::sameFile($manifestPath, $manifestHandle, $manifestState);
            self::sameFile($path, $databaseHandle, $databaseState);
            self::sameDirectory($privateParent, $parent);
            self::sameDirectory($directory, $directoryState);
            if (! @fclose($manifestHandle)) { throw new RuntimeException; }
            $manifestHandle = null;
            if (! @fclose($databaseHandle)) { throw new RuntimeException; }
            $databaseHandle = null;
            $complete = true;

            return [
                'directory' => $directory, 'database' => $path, 'admission_manifest' => $manifestPath,
                'runtime_directories' => array_combine(['sessions', 'cache', 'locks', 'compiled'],
                    array_map(static fn (string $child): string => $directory.'/'.$child, ['sessions', 'cache', 'locks', 'compiled'])),
                'schema_profile' => 'intake-policy-v4', 'empty' => true,
                'activated' => false, 'credentials_created' => false, 'server_started' => false,
            ];
        } catch (Throwable) {
            // Never chain native DSNs, SQL, private paths or diagnostic messages.
            throw new RuntimeException('Fresh file preparation was not confirmed during '.$phase.'. Keep the private parent for operator inspection; no activation or automatic retry.');
        } finally {
            try { $reader?->disconnect(); } catch (Throwable) {}
            try { $connection?->disconnect(); } catch (Throwable) {}
            $reader = $connection = $source = $pdo = $read = null;
            foreach ([$manifestHandle, $databaseHandle] as $handle) {
                if (is_resource($handle)) { @fclose($handle); }
            }
            if (! $complete && $directory !== null && $directoryState !== null) {
                // A foreign/replaced object, unknown sidecar or nonempty child
                // stops cleanup. Never recurse or remove anything pre-existing.
                self::discardOwned($privateParent, $parent, $directory, $directoryState, $created);
            }
            umask($oldMask);
        }
    }

    private static function directory(string $path): array
    {
        if ($path === '' || $path[0] !== '/' || str_contains($path, "\0")
            || array_intersect(explode('/', substr($path, 1)), ['', '.', '..']) !== []) {
            throw new RuntimeException('A canonical existing private parent directory is required.');
        }
        // A private leaf alone is insufficient: an unrelated user able to
        // rename an ancestor could move/replace the complete private subtree.
        // Trust only root/current UID, with no group/other write unless sticky.
        $current = '';
        self::ancestor('/');
        foreach (explode('/', substr($path, 1)) as $part) {
            $current .= '/'.$part;
            $state = self::ancestor($current);
        }
        if (realpath($path) !== $path || ($state['mode'] & 07777) !== 0700 || $state['uid'] !== posix_geteuid()) {
            throw new RuntimeException('The existing parent must already be private to the current operator; permissions are never changed.');
        }

        return array_intersect_key($state, array_flip(['dev', 'ino', 'uid', 'mode']));
    }

    private static function ancestor(string $path): array
    {
        clearstatcache(true, $path);
        $state = @lstat($path);
        if ($state === false || ($state['mode'] & 0170000) !== 0040000
            || ! in_array($state['uid'], [0, posix_geteuid()], true)
            || (($state['mode'] & 0022) !== 0 && ($state['mode'] & 01000) === 0)) {
            throw new RuntimeException('Every ancestor must be unaliased, root/operator owned and protected against other-user rename.');
        }

        return $state;
    }

    private static function sameDirectory(string $path, array $expected): void
    {
        if (self::directory($path) !== $expected) { throw new RuntimeException; }
    }

    private static function file(string $path, mixed $handle): array
    {
        clearstatcache(true, $path);
        $state = @lstat($path);
        $opened = is_resource($handle) ? @fstat($handle) : false;
        if ($state === false || $opened === false || ($state['mode'] & 0170000) !== 0100000
            || ($state['mode'] & 07777) !== 0600 || $state['nlink'] !== 1 || $state['uid'] !== posix_geteuid()) {
            throw new RuntimeException;
        }
        $identity = array_intersect_key($state, array_flip(['dev', 'ino', 'uid', 'mode', 'nlink']));
        if ($identity !== array_intersect_key($opened, array_flip(['dev', 'ino', 'uid', 'mode', 'nlink']))) {
            throw new RuntimeException;
        }

        return $identity;
    }

    private static function sameFile(string $path, mixed $handle, array $expected): void
    {
        if (self::file($path, $handle) !== $expected) { throw new RuntimeException; }
    }

    private static function discardOwned(string $parent, array $parentState, string $directory, array $directoryState, array $created): void
    {
        try {
            self::sameDirectory($parent, $parentState);
            self::sameDirectory($directory, $directoryState);
            $entries = @scandir($directory);
            if ($entries === false || array_diff($entries, ['.', '..', ...array_keys($created)]) !== []) { return; }
            // Verify the entire cohort BEFORE any deletion. No unknown journal,
            // nested data, replacement, link or altered permissions are removed.
            foreach ($created as $name => $entry) {
                $path = $directory.'/'.$name;
                if ($entry['directory']) {
                    self::sameDirectory($path, $entry['state']);
                    if (@scandir($path) !== ['.', '..']) { return; }
                } else {
                    $handle = @fopen($path, 'rb');
                    if ($handle === false) { return; }
                    try { self::sameFile($path, $handle, $entry['state']); }
                    finally { fclose($handle); }
                }
            }
            foreach (array_reverse($created, true) as $name => $entry) {
                self::sameDirectory($parent, $parentState);
                self::sameDirectory($directory, $directoryState);
                $path = $directory.'/'.$name;
                if ($entry['directory']) {
                    self::sameDirectory($path, $entry['state']);
                    if (@scandir($path) !== ['.', '..'] || ! @rmdir($path)) { return; }
                } else {
                    $handle = @fopen($path, 'rb');
                    if ($handle === false) { return; }
                    try {
                        self::sameFile($path, $handle, $entry['state']);
                        if (! @unlink($path)) { return; }
                    } finally { fclose($handle); }
                }
            }
            self::sameDirectory($parent, $parentState);
            self::sameDirectory($directory, $directoryState);
            @rmdir($directory);
        } catch (Throwable) {
            // Leave the new private instance for operator inspection, no raw log.
        }
    }
}
