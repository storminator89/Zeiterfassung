<?php

namespace App\Fixtures;

use App\Http\LocalWebRoutes;
use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\AuditContext;
use App\LegacyDatabase\AuditFunctions;
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\V2Adoption;
use App\LegacyDatabase\V2Preflight;
use App\View\ExistingUi\Interactions;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Application;
use LogicException;
use PDO;
use RuntimeException;

/** Explicit synthetic composition only. Never registered by a provider or route. */
final class LocalWebFixture
{
    public const PORT = 18090;
    public const PASSWORD = 'Synthetic-local-preview-only-2026!';
    private const KIND = 'chrono-local-web-fixture-v1';

    private function __construct(private readonly string $root, private readonly string $directory, private readonly array $manifest) {}

    /** No existing database, credential, key, directory or port arguments. */
    public static function prepare(Application $app): array
    {
        if (PHP_SAPI !== 'cli' || $app->make('config')->has('database.connections.chrono')
            || $app->make('db')->getConnections() !== [] || $app->bound(AttendanceWriter::class)) {
            throw new LogicException('Synthetic preparation requires the inactive application.');
        }
        umask(0077);
        $root = dirname($app->basePath());
        $support = self::support($root);
        if (! is_dir($support) && ! mkdir($support, 0700)) {
            throw new RuntimeException('Synthetic support directory could not be created.');
        }
        if (is_link($support) || realpath($support) !== $support) {
            throw new RuntimeException('Synthetic support directory must be canonical and unaliased.');
        }
        $instance = bin2hex(random_bytes(12));
        $directory = $support.'/local-web-fixture-'.$instance;
        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('A new private synthetic fixture directory is required.');
        }
        foreach (['public', 'sessions', 'cache', 'locks', 'compiled'] as $child) {
            if (! mkdir($directory.'/'.$child, 0700)) {
                throw new RuntimeException('Private synthetic runtime directories could not be created.');
            }
        }
        $mapping = self::mapping($root);
        $source = (new SQLiteConnector)->connect(['database' => ':memory:', 'busy_timeout' => 5000]);
        $build = require $root.'/framework/database/fixtures/legacy_v2.php';
        $build($source, $mapping, true);
        $app->make(AuditFunctions::class)->register($source);
        $app->make(AuditContext::class)->run('fixture:local-web', 'Synthetic local browser accounts', function () use ($source): void {
            $statement = $source->prepare('UPDATE users SET username=?,email=?,password=? WHERE id=?');
            $hash = password_hash(self::PASSWORD, PASSWORD_BCRYPT);
            foreach ([1 => 'fixture.member', 2 => 'fixture.admin'] as $identifier => $username) {
                $statement->execute([$username, $username.'@example.invalid', $hash, $identifier]);
            }
            $statement->closeCursor();
        });
        $source->exec('VACUUM INTO '.$source->quote($directory.'/original.sqlite'));
        $source = null;
        if (! copy($directory.'/original.sqlite', $directory.'/target.sqlite')
            || ! copy($directory.'/original.sqlite', $directory.'/backup.sqlite')
            || $app->make(Kernel::class)->call('chrono:legacy-v2', [
                'action' => 'adopt', '--target' => $directory.'/target.sqlite',
                '--backup' => $directory.'/backup.sqlite', '--offline' => true,
            ]) !== 0) {
            throw new RuntimeException('Synthetic cold-copy adoption failed.');
        }
        $manifest = [
            'kind' => self::KIND, 'checkout' => $root, 'instance' => $instance,
            'original_sha256' => hash_file('sha256', $directory.'/original.sqlite'),
            'app_key' => 'base64:'.base64_encode(random_bytes(32)),
            'cookie' => 'chrono_local_web_'.$instance, 'port' => self::PORT,
        ];
        $path = $directory.'/manifest.json';
        if (file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException('Private synthetic manifest could not be written.');
        }

        return [
            'manifest' => $path, 'document_root' => $directory.'/public',
            'username' => 'fixture.member', 'admin_username' => 'fixture.admin',
            'password' => self::PASSWORD, 'url' => 'http://localhost:'.self::PORT.'/login.php',
            'launch' => 'CHRONO_LOCAL_WEB_FIXTURE_MANIFEST='.escapeshellarg($path)
                .' php -S 127.0.0.1:'.self::PORT.' -t '.escapeshellarg($directory.'/public')
                .' '.escapeshellarg($root.'/framework/tools/local-web-fixture-router.php'),
            'synthetic_only' => true, 'server_started' => false,
        ];
    }

    /** Accept only this checkout's newly generated manifest, never a DB/key path. */
    public static function fromManifest(string $path, string $root): self
    {
        $prefix = preg_quote(self::support($root), '~');
        if (! preg_match('~^'.$prefix.'/local-web-fixture-([a-f0-9]{24})/manifest\.json$~D', $path, $match)) {
            throw new RuntimeException('Only a generated synthetic local-web manifest is supported.');
        }
        $files = new OfflineFiles([$root, $root.'/framework/public']);
        $files->validate($path);
        if (filesize($path) > 4096) {
            throw new RuntimeException('Unexpected synthetic manifest size.');
        }
        $contents = @file_get_contents($path);
        if (! is_string($contents)) {
            throw new RuntimeException('The private synthetic manifest is not readable.');
        }
        $manifest = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($manifest)) {
            throw new RuntimeException('A synthetic manifest object is required.');
        }
        $key = is_string($manifest['app_key'] ?? null) && str_starts_with($manifest['app_key'], 'base64:')
            ? base64_decode(substr($manifest['app_key'], 7), true) : false;
        if (($manifest['kind'] ?? null) !== self::KIND || ($manifest['checkout'] ?? null) !== $root
            || ($manifest['instance'] ?? null) !== $match[1] || ($manifest['port'] ?? null) !== self::PORT
            || ($manifest['cookie'] ?? null) !== 'chrono_local_web_'.$match[1]
            || ! is_string($key) || strlen($key) !== 32
            || ! is_string($manifest['original_sha256'] ?? null)
            || ! preg_match('/^[a-f0-9]{64}$/D', $manifest['original_sha256'])) {
            throw new RuntimeException('Synthetic manifest identity is invalid.');
        }
        $directory = dirname($path);
        foreach (['public', 'sessions', 'cache', 'locks', 'compiled'] as $child) {
            $stat = @lstat($directory.'/'.$child);
            if ($stat === false || ($stat['mode'] & 0170000) !== 0040000 || ($stat['mode'] & 0777) !== 0700) {
                throw new RuntimeException('Synthetic runtime directories must remain private and unaliased.');
            }
        }
        $backup = $files->verifyBackup($directory.'/original.sqlite', $directory.'/backup.sqlite');
        if (! hash_equals($manifest['original_sha256'], $backup['target']['sha256'])) {
            throw new RuntimeException('The pristine synthetic source changed.');
        }

        return new self($root, $directory, $manifest);
    }

    /** Called only by the private loopback front controller after explicit boot. */
    public function configure(Application $app): void
    {
        $config = $app->make('config');
        $database = $app->make('db');
        if (PHP_SAPI !== 'cli-server' || $config->has('database.connections.chrono')
            || $database->getConnections() !== [] || $app->bound(AttendanceWriter::class)
            || $app->resolved('encrypter') || $app->resolved('session.store')) {
            throw new LogicException('Synthetic runtime cannot replace an activated application.');
        }
        $files = new OfflineFiles([$this->root, $this->root.'/framework/public']);
        $path = $this->directory.'/target.sqlite';
        $read = $files->openReadOnly($path);
        $mapping = self::mapping($this->root);
        $reference = (new SQLiteConnector)->connect(['database' => ':memory:', 'busy_timeout' => 5000]);
        $build = require $this->root.'/framework/database/fixtures/legacy_v2.php';
        $build($reference, $mapping, false);
        $tables = array_column($mapping['tables'], null, 'name');
        $reference->exec($tables['login_attempts']['create_sql']);
        $connection = new SQLiteConnection($reference, ':memory:', '', ['name' => 'chrono_fixture_reference']);
        $repository = new DatabaseMigrationRepository(new ConnectionResolver(['chrono_fixture_reference' => $connection]), V2Preflight::LEDGER);
        $repository->setSource('chrono_fixture_reference');
        $repository->createRepository();
        $checked = (new V2Preflight($mapping))->inspect($read, $reference);
        if (V2Adoption::ledgerState($read, $checked['schema_signature']) !== 'owned'
            || $read->query('SELECT id,username,email FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) !== [
                ['id' => 1, 'username' => 'fixture.member', 'email' => 'fixture.member@example.invalid'],
                ['id' => 2, 'username' => 'fixture.admin', 'email' => 'fixture.admin@example.invalid'],
            ]) {
            throw new RuntimeException('Only the adopted synthetic browser fixture is supported.');
        }
        $reference = $connection = $repository = null;
        $config->set([
            'app.key' => $this->manifest['app_key'], 'app.debug' => false,
            'app.url' => 'http://localhost:'.self::PORT,
            'session.driver' => 'file', 'session.files' => $this->directory.'/sessions',
            'session.cookie' => $this->manifest['cookie'], 'session.secure' => true,
            'session.http_only' => true, 'session.same_site' => 'lax',
            'session.domain' => null, 'session.path' => '/', 'session.encrypt' => true,
            'session.block' => true, 'session.block_store' => 'file',
            'session.block_lock_seconds' => 60, 'session.block_wait_seconds' => 10,
            'view.compiled' => $this->directory.'/compiled',
            'cache.default' => 'file', 'cache.stores.file.path' => $this->directory.'/cache',
            'cache.stores.file.lock_path' => $this->directory.'/locks',
            'cache.prefix' => $this->manifest['cookie'].'-',
            'database.connections.chrono' => [
                'driver' => 'sqlite', 'database' => $path, 'prefix' => '',
                'busy_timeout' => 5000, 'transaction_mode' => 'DEFERRED',
            ],
        ]);
        // Name-specific stock Laravel extension: scratch/default untouched.
        $database->extend('chrono', static function (array $configuration, string $name) use ($read, $path): SQLiteConnection {
            $configuration['name'] = $name;
            return new SQLiteConnection($read, $path, '', $configuration);
        });
        $app->bind(AttendanceWriter::class, function ($app) use ($files, $path): AttendanceWriter {
            $configuration = $files->readWriteConfiguration($path, $files->snapshot($path));
            $pdo = (new SQLiteConnector)->connect($configuration);
            return new AttendanceWriter($pdo, $configuration, $app->make(AuditContext::class), $app->make(AuditFunctions::class));
        });
        // Preserve the registered production limit definition, not the boot-time
        // repository cache directory captured by its first RateLimiter instance.
        $definition = $app->make(RateLimiter::class)->limiter('chrono-web-login');
        if ($definition === null) {
            throw new LogicException('The existing local login limiter is required.');
        }
        $app->make('cache')->forgetDriver();
        $app->forgetInstance('cache.store');
        $limiter = new RateLimiter($app->make('cache')->store('file'));
        $limiter->for('chrono-web-login', $definition);
        $app->instance(RateLimiter::class, $limiter);
        $app->instance(Interactions::class, new Interactions(true, true, true, true, true, true));
        LocalWebRoutes::register($app);
    }

    private static function support(string $root): string
    {
        if (realpath($root) !== $root) {
            throw new RuntimeException('The candidate checkout must be canonical.');
        }
        return dirname($root).'/'.basename($root).'-support';
    }

    private static function mapping(string $root): array
    {
        return json_decode(file_get_contents($root.'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
    }
}
