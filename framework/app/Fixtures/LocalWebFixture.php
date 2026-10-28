<?php

namespace App\Fixtures;

use App\Http\LocalWebRoutes;
use App\Auth\LegacyUser;
use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\RuntimeAdmission;
use App\LegacyDatabase\RuntimeDatabase;
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
    private const LDAP_KIND = 'chrono-local-ldap-web-fixture-v2';
    private const LDAP_PROFILE = 'ordinary-ldap-seed3-v2';
    private const REGISTRATION_KIND = 'chrono-local-registration-web-fixture-v3';

    private function __construct(private readonly string $root, private readonly string $directory, private readonly array $manifest) {}

    /** No existing database, credential, key, directory or port arguments. */
    public static function prepare(Application $app, ?string $ldapCaFile = null, bool $registration = false): array
    {
        if (PHP_SAPI !== 'cli' || $app->make('config')->has('database.connections.chrono')
            || $app->make('db')->getConnections() !== [] || $app->bound(AttendanceWriter::class) || $app->bound(RuntimeDatabase::class)) {
            throw new LogicException('Synthetic preparation requires the inactive application.');
        }
        if ($registration && $ldapCaFile !== null) {
            throw new LogicException('Registration requires its own fresh local-only fixture.');
        }
        if ($ldapCaFile !== null && (! str_starts_with($ldapCaFile, '/') || str_contains($ldapCaFile, "\0")
            || realpath($ldapCaFile) !== $ldapCaFile || ! is_file($ldapCaFile) || ! is_readable($ldapCaFile))) {
            throw new RuntimeException('Explicit existing canonical lab CA file required.');
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
        $directory = $support.'/'.($registration ? 'local-registration-web-fixture-' : ($ldapCaFile === null ? 'local-web-fixture-' : 'local-ldap-web-fixture-')).$instance;
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
        $app->make(AuditContext::class)->run('fixture:local-web', 'Synthetic local browser accounts', function () use ($source, $ldapCaFile, $registration, $app): void {
            $statement = $source->prepare('UPDATE users SET username=?,email=?,password=? WHERE id=?');
            $hash = password_hash(self::PASSWORD, PASSWORD_BCRYPT);
            foreach ([1 => 'fixture.member', 2 => 'fixture.admin'] as $identifier => $username) {
                if ($registration) {
                    $username = $identifier === 1 ? 'registration.seed.member' : 'registration.seed.admin';
                }
                // Never expose a publicly known seed-admin password on the signup profile.
                $seedHash = $registration ? $app->make('hash')->make(bin2hex(random_bytes(32))) : $hash;
                $statement->execute([$username, $username.'@example.invalid', $seedHash, $identifier]);
            }
            $statement->closeCursor();
            if ($ldapCaFile !== null) {
                // A NEW invented LDAP principal, never a local-account conversion.
                $statement = $source->prepare('INSERT INTO users(id,username,email,password,role,auth_source) VALUES(3,?,?,?,?,?)');
                $statement->execute(['ldap_worker', 'ldap-worker@example.invalid',
                    password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT), 'user', 'ldap']);
                $statement->closeCursor();
            }
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
            'kind' => $registration ? self::REGISTRATION_KIND : ($ldapCaFile === null ? self::KIND : self::LDAP_KIND), 'checkout' => $root, 'instance' => $instance,
            'original_sha256' => hash_file('sha256', $directory.'/original.sqlite'),
            'app_key' => 'base64:'.base64_encode(random_bytes(32)),
            'cookie' => ($registration ? 'chrono_registration_web_' : ($ldapCaFile === null ? 'chrono_local_web_' : 'chrono_ldap_web_')).$instance, 'port' => self::PORT,
        ];
        if ($ldapCaFile !== null) {
            $manifest['profile'] = self::LDAP_PROFILE;
            $manifest['ldap'] = ['enabled' => true, 'uri' => 'ldap://127.0.0.1:1389',
                'base_dn' => 'dc=chrono,dc=test', 'ca_file' => $ldapCaFile];
        }
        $path = $directory.'/manifest.json';
        if (file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException('Private synthetic manifest could not be written.');
        }

        $result = [
            'manifest' => $path, 'document_root' => $directory.'/public',
            'username' => 'fixture.member', 'admin_username' => 'fixture.admin',
            'password' => self::PASSWORD, 'url' => 'http://localhost:'.self::PORT.'/login.php',
            'launch' => 'CHRONO_LOCAL_WEB_FIXTURE_MANIFEST='.escapeshellarg($path)
                .' php -S 127.0.0.1:'.self::PORT.' -t '.escapeshellarg($directory.'/public')
                .' '.escapeshellarg($root.'/framework/tools/local-web-fixture-router.php'),
            'synthetic_only' => true, 'server_started' => false,
        ];
        if ($registration) {
            unset($result['password'], $result['username'], $result['admin_username']);
            $result['url'] = 'http://localhost:'.self::PORT.'/register.php';
            $result['user_chosen_credentials'] = true;
            $result['seed_admin_login_available'] = false;
        }
        return $result;
    }

    /** Accept only this checkout's newly generated manifest, never a DB/key path. */
    public static function fromManifest(string $path, string $root): self
    {
        $prefix = preg_quote(self::support($root), '~');
        if (! preg_match('~^'.$prefix.'/(?:local-web-fixture-|local-ldap-web-fixture-|local-registration-web-fixture-)([a-f0-9]{24})/manifest\.json$~D', $path, $match)) {
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
        $ldapProfile = ($manifest['kind'] ?? null) === self::LDAP_KIND;
        $ldapPath = str_starts_with(basename(dirname($path)), 'local-ldap-web-fixture-');
        $registrationProfile = ($manifest['kind'] ?? null) === self::REGISTRATION_KIND;
        $registrationPath = str_starts_with(basename(dirname($path)), 'local-registration-web-fixture-');
        if ($ldapProfile !== $ldapPath || $registrationProfile !== $registrationPath
            || ! in_array($manifest['kind'] ?? null, [self::KIND, self::LDAP_KIND, self::REGISTRATION_KIND], true) || ($manifest['checkout'] ?? null) !== $root
            || ($manifest['instance'] ?? null) !== $match[1] || ($manifest['port'] ?? null) !== self::PORT
            || ($manifest['cookie'] ?? null) !== ($registrationProfile ? 'chrono_registration_web_' : ($ldapProfile ? 'chrono_ldap_web_' : 'chrono_local_web_')).$match[1]
            || ! is_string($key) || strlen($key) !== 32
            || ! is_string($manifest['original_sha256'] ?? null)
            || ! preg_match('/^[a-f0-9]{64}$/D', $manifest['original_sha256'])) {
            throw new RuntimeException('Synthetic manifest identity is invalid.');
        }
        if ($ldapProfile) {
            $allowed = ['kind', 'checkout', 'instance', 'original_sha256', 'app_key', 'cookie', 'port', 'profile', 'ldap'];
            if (count($manifest) !== count($allowed) || array_diff(array_keys($manifest), $allowed) !== []
                || ($manifest['profile'] ?? null) !== self::LDAP_PROFILE) {
                throw new RuntimeException('Unknown synthetic LDAP manifest metadata.');
            }
            $settings = $manifest['ldap'] ?? null;
            if (! is_array($settings) || count($settings) !== 4 || ($settings['enabled'] ?? null) !== true
                || ($settings['uri'] ?? null) !== 'ldap://127.0.0.1:1389'
                || ($settings['base_dn'] ?? null) !== 'dc=chrono,dc=test'
                || ! is_string($settings['ca_file'] ?? null) || ! str_starts_with($settings['ca_file'], '/')
                || str_contains($settings['ca_file'], "\0")
                || preg_match('~/(?:\.|\.\.)(?:/|$)|//~', $settings['ca_file'])) {
                throw new RuntimeException('Explicit fixed synthetic LDAP profile required.');
            }
            // No extension/CA-liveness/network check here: local break-glass
            // must remain independent of an unavailable synthetic directory.
        }
        if ($registrationProfile && (count($manifest) !== 7
            || array_diff(array_keys($manifest), ['kind', 'checkout', 'instance', 'original_sha256', 'app_key', 'cookie', 'port']) !== [])) {
            throw new RuntimeException('Unknown synthetic registration metadata.');
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

        $fixture = new self($root, $directory, $manifest);
        if ($ldapProfile || $registrationProfile) {
            // Pristine LDAP has exactly three seeds, registration exactly two
            // distinct seeds. Target-only writes/old retagging are not preparation.
            $pristine = $files->openReadOnly($directory.'/original.sqlite');
            if (! $fixture->accountsFit($pristine, true)) {
                throw new RuntimeException($registrationProfile ? 'The pristine synthetic registration profile does not match.' : 'The pristine synthetic LDAP profile does not match.');
            }
            $pristine = null;
        }

        return $fixture;
    }

    /** Called only by the private loopback front controller after explicit boot. */
    public function configure(Application $app): void
    {
        $config = $app->make('config');
        $database = $app->make('db');
        if (PHP_SAPI !== 'cli-server' || $config->has('database.connections.chrono')
            || $database->getConnections() !== [] || $app->bound(AttendanceWriter::class) || $app->bound(RuntimeDatabase::class)
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
            || ! $this->accountsFit($read)) {
            throw new RuntimeException('Only the adopted synthetic browser fixture is supported.');
        }
        if ($this->ldapProfile() && ($app->make('auth')->hasResolvedGuards()
            || $config->get('chrono_ldap.enabled', false) !== false
            || $config->get('auth.providers.chrono_users') !== ['driver' => 'eloquent', 'model' => LegacyUser::class])) {
            throw new LogicException('Synthetic LDAP cannot replace an activated authentication profile.');
        }
        $reference = $connection = $repository = null;
        // This binding can only describe the already verified invented fixture;
        // never a request/ENV-selected source. It opens no native export handle.
        $stat = lstat($path);
        $snapshotAdmission = RuntimeAdmission::fromConfiguration([
            'path' => $path, 'device' => $stat['dev'], 'inode' => $stat['ino'],
            'schema_version' => $read->query('PRAGMA schema_version')->fetchColumn(),
            'schema_signature' => $checked['schema_signature'],
            'foreign_keys' => $read->query('PRAGMA foreign_keys')->fetchColumn(),
            'journal_mode' => $read->query('PRAGMA journal_mode')->fetchColumn(),
            'synchronous' => $read->query('PRAGMA synchronous')->fetchColumn(),
        ]);
        $app->instance(RuntimeDatabase::class, new RuntimeDatabase($snapshotAdmission, $files));
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
        if ($this->ldapProfile()) {
            $config->set(['chrono_ldap' => $this->manifest['ldap'],
                'auth.providers.chrono_users' => ['driver' => 'chrono-existing', 'model' => LegacyUser::class]]);
        }
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
        $registrationDefinition = $app->make(RateLimiter::class)->limiter('chrono-web-register');
        if ($this->registrationProfile() && $registrationDefinition === null) {
            throw new LogicException('The persistent registration limiter is required.');
        }
        if ($definition === null) {
            throw new LogicException('The existing local login limiter is required.');
        }
        $app->make('cache')->forgetDriver();
        $app->forgetInstance('cache.store');
        $limiter = new RateLimiter($app->make('cache')->store('file'));
        $limiter->for('chrono-web-login', $definition);
        if ($this->registrationProfile()) {
            $limiter->for('chrono-web-register', $registrationDefinition);
            $config->set('chrono_registration.enabled', true);
        }
        $app->instance(RateLimiter::class, $limiter);
        $app->instance(Interactions::class, new Interactions(
            login: true, attendance: true, logout: true, corrections: true,
            home: true, absences: true, workModels: true, history: true, supervisor: true, dashboard: true, adminPauseSettings: true,
        ));
        LocalWebRoutes::register($app, includeHistory: true, includeSupervisor: true, includeDashboard: true, includeCsv: true, includeSettings: true, includeAdminPauseSettings: true, includeTerminalDevices: true, includeDatabaseSnapshot: true, includeRegistration: $this->registrationProfile());
    }

    private function ldapProfile(): bool
    {
        return $this->manifest['kind'] === self::LDAP_KIND;
    }

    private function registrationProfile(): bool
    {
        return $this->manifest['kind'] === self::REGISTRATION_KIND;
    }

    private function accountsFit(PDO $read, bool $pristine = false): bool
    {
        if ($this->registrationProfile()) {
            return $this->registrationAccountsFit($read, $pristine);
        }
        if (! $this->ldapProfile()) {
            // Exact original v1 all-row boundary; never upgraded in place.
            return $read->query('SELECT id,username,email FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === [
                ['id' => 1, 'username' => 'fixture.member', 'email' => 'fixture.member@example.invalid'],
                ['id' => 2, 'username' => 'fixture.admin', 'email' => 'fixture.admin@example.invalid'],
            ];
        }
        if ($pristine) {
            // Only seed identity/context columns: no password hashes or journal
            // contents are selected for this fresh-profile provenance check.
            return $read->query('SELECT id,username,email,auth_source,role FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === [
                ['id' => 1, 'username' => 'fixture.member', 'email' => 'fixture.member@example.invalid', 'auth_source' => 'local', 'role' => 'user'],
                ['id' => 2, 'username' => 'fixture.admin', 'email' => 'fixture.admin@example.invalid', 'auth_source' => 'local', 'role' => 'admin'],
                ['id' => 3, 'username' => 'ldap_worker', 'email' => 'ldap-worker@example.invalid', 'auth_source' => 'ldap', 'role' => 'user'],
            ];
        }
        $anchors = [
            1 => ['fixture.member', 'fixture.member@example.invalid', 'local', 'user'],
            2 => ['fixture.admin', 'fixture.admin@example.invalid', 'local', 'admin'],
            3 => ['ldap_worker', 'ldap-worker@example.invalid', 'ldap', 'user'],
        ];
        $seen = [];
        foreach ($read->query('SELECT id,username,email,auth_source,role,password FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (! is_int($row['id']) || $row['id'] < 1 || ! is_string($row['username']) || $row['username'] === ''
                || ! is_string($row['password']) || ! $row['password']
                || ! in_array($row['role'], ['user', 'supervisor', 'admin'], true)) {
                return false;
            }
            if (isset($anchors[$row['id']])) {
                if ([$row['username'], $row['email'], $row['auth_source'], $row['role']] !== $anchors[$row['id']]) {
                    return false;
                }
                $seen[] = $row['id'];
            } elseif ($row['auth_source'] !== 'local') {
                return false; // No extra LDAP/enrollment or unknown source.
            }
        }

        // Fresh private-file provenance is NOT proof of the endpoint that made
        // an extra local row. Existing local admin creation must remain usable.
        return $seen === [1, 2, 3];
    }

    /** Fresh source identity, not proof of which endpoint inserted a later row. */
    private function registrationAccountsFit(PDO $read, bool $pristine): bool
    {
        $rows = $read->query('SELECT id,username,email,role,auth_source,token,department_id,supervisor_id FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) < 2 || ($pristine && count($rows) !== 2)) {
            return false;
        }
        foreach ($rows as $index => $row) {
            if (! is_int($row['id']) || $row['id'] < 1 || $row['auth_source'] !== 'local'
                || $row['token'] !== null || $row['department_id'] !== null || $row['supervisor_id'] !== null) {
                return false;
            }
            if ($index < 2) {
                $name = $index === 0 ? 'registration.seed.member' : 'registration.seed.admin';
                if ($row['id'] !== $index + 1 || $row['username'] !== $name
                    || $row['email'] !== $name.'@example.invalid' || $row['role'] !== ($index === 0 ? 'user' : 'admin')) {
                    return false;
                }
            } elseif ($row['role'] !== 'user') {
                return false;
            }
        }
        return true;
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
