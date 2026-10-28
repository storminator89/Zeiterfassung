<?php

namespace App\FreshWeb;

use App\Auth\FirstAdminSetup;
use App\Auth\LegacyUser;
use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\RuntimeAdmission;
use App\LegacyDatabase\RuntimeDatabase;
use App\Setup\FirstAdminSetupFiles;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Application;
use LogicException;
use RuntimeException;
use Throwable;

/** Explicit private NEW-instance composition. No database/secret creation or repair. */
final readonly class FreshWebConfiguration
{
    private function __construct(
        public string $origin,
        private string $directory,
        private array $directories,
        private array $manifest,
        private string $manifestHash,
        private array $manifestState,
        private array $databaseState,
        private bool $setupOptIn,
        private array $overrides,
    ) {}

    public static function fromApplication(Application $app): ?self
    {
        $config = $app->make('config');
        $enabled = $config->get('chrono_fresh_web.enabled', false);
        if (! is_bool($enabled)) { throw new LogicException('Fresh web requires an explicit boolean opt-in.'); }
        if (! $enabled) { return null; } // No private paths, keys, stores or database on the off path.
        if ($app->configurationIsCached() || $app->routesAreCached()) {
            throw new LogicException('Fresh web currently requires uncached configuration and routes.');
        }
        // These source prerequisites are mandatory, not configurable approval flags.
        // A pre-gate V4 runtime cannot be activated through this composition.
        if (! method_exists(RuntimeDatabase::class, 'isIntakePolicyV4')
            || ! class_exists(FirstAdminSetup::class) || ! class_exists(FirstAdminSetupFiles::class)) {
            throw new LogicException('The complete fresh web source prerequisites are missing.');
        }
        foreach (['auth', 'auth.driver', 'hash', 'cache', 'cache.store', 'session', 'session.store',
            'encrypter', 'view', 'blade.compiler', RateLimiter::class] as $service) {
            if ($app->resolved($service)) { throw new LogicException('Fresh web must configure private state before service resolution.'); }
        }
        if ($app->bound(RuntimeDatabase::class) || $app->bound(AttendanceWriter::class)
            || $config->has('database.connections.chrono')) {
            throw new LogicException('Fresh web must not replace another database profile.');
        }
        if ($config->get('chrono_runtime.read_enabled', false) !== true
            || $config->get('chrono_runtime.write_enabled', false) !== true
            || $config->get('app.env') !== 'production' || $config->get('app.debug') !== false
            || $config->get('hashing.driver') !== 'bcrypt'
            || $config->get('auth.defaults.guard') !== 'web'
            || $config->get('auth.guards.web') !== ['driver' => 'session', 'provider' => 'chrono_users']
            || $config->get('auth.providers.chrono_users') !== ['driver' => 'eloquent', 'model' => LegacyUser::class]
            || $config->get('chrono_terminal.http_enabled', false) !== false) {
            throw new LogicException('The explicit fresh local web/runtime profile is not admitted.');
        }
        self::key($config);
        $origin = self::origin($config->get('app.url'));
        $setup = $config->get('chrono_fresh_web.setup_enabled', false);
        if (! is_bool($setup)) { throw new LogicException('Fresh setup requires a separate boolean opt-in.'); }
        $lock = self::seconds($config->get('chrono_fresh_web.session_lock_seconds'));
        $wait = self::seconds($config->get('chrono_fresh_web.session_wait_seconds'));
        if ($wait > $lock) {
            throw new LogicException('Explicit positive fresh session lock/wait durations are required.');
        }
        if (! function_exists('posix_geteuid')) { throw new LogicException('Fresh web requires the supported local Unix runtime.'); }

        try {
            $directory = $config->get('chrono_fresh_web.instance_directory');
            if (! is_string($directory) || preg_match('/^chrono-fresh-[a-f0-9]{24}$/D', basename($directory)) !== 1) {
                throw new RuntimeException;
            }
            $directories = [$directory => self::directory($directory)];
            foreach ([$app->basePath(), dirname($app->basePath()), $app->publicPath()] as $forbidden) {
                $forbidden = realpath($forbidden);
                if ($forbidden === false || $directory === $forbidden || str_starts_with($directory, $forbidden.'/')) {
                    throw new RuntimeException;
                }
            }
            foreach (['sessions', 'cache', 'locks', 'compiled'] as $name) {
                $directories[$directory.'/'.$name] = self::directory($directory.'/'.$name);
            }
            [$manifest, $manifestHash, $manifestState] = self::json($directory.'/runtime-admission.json');
            $admission = RuntimeAdmission::fromConfiguration($manifest);
            if ($admission->schemaProfile !== 'intake-policy-v4' || $admission->path !== $directory.'/application.sqlite') {
                throw new RuntimeException;
            }
            $databaseState = self::file($admission->path);
            if ($databaseState['dev'] !== $admission->device || $databaseState['ino'] !== $admission->inode) {
                throw new RuntimeException;
            }
            self::assets($app->publicPath());
            $namespace = 'chrono_fresh_'.substr(hash('sha256', $directory.'\0'.$admission->device.':'.$admission->inode), 0, 24);
            $overrides = [
                'chrono_runtime.admission' => $manifest,
                'session.driver' => 'file', 'session.files' => $directory.'/sessions',
                'session.cookie' => $namespace, 'session.secure' => true, 'session.http_only' => true,
                'session.same_site' => 'lax', 'session.domain' => null, 'session.path' => '/',
                'session.partitioned' => false, 'session.encrypt' => true, 'session.serialization' => 'json',
                'session.block' => true, 'session.block_store' => 'file',
                'session.block_lock_seconds' => $lock, 'session.block_wait_seconds' => $wait,
                'cache.default' => 'file', 'cache.limiter' => 'file',
                'cache.stores.file' => ['driver' => 'file', 'path' => $directory.'/cache', 'lock_path' => $directory.'/locks'],
                'cache.prefix' => $namespace.'-', 'cache.serializable_classes' => false,
                'view.compiled' => $directory.'/compiled',
                // A route/display capability only: current DB policy and locked writer stay authoritative.
                'chrono_registration.enabled' => true,
            ];
            $profile = new self($origin, $directory, $directories, $manifest, $manifestHash, $manifestState,
                $databaseState, $setup, $overrides);
            $profile->assertCurrent($config, false);
            return $profile;
        } catch (Throwable) {
            throw new LogicException('The private fresh web instance is not admitted.');
        }
    }

    /** Set before cache/limiter/session/view resolution; never resets an existing service. */
    public function configure(Repository $config): void
    {
        $this->assertCurrent($config, false);
        $config->set($this->overrides);
        $config->set('chrono_setup', $this->setupConfiguration());
    }

    /** Metadata only; no DB contents/hash/PDO and no receipt/raw-token read. */
    public function assertCurrent(Repository $config, bool $checkConfiguration = true): void
    {
        foreach ($this->directories as $path => $state) {
            if (self::directory($path) !== $state) { throw new RuntimeException; }
        }
        [$manifest, $hash, $state] = self::json($this->directory.'/runtime-admission.json');
        if ($manifest !== $this->manifest || ! hash_equals($this->manifestHash, $hash) || $state !== $this->manifestState
            || self::file($this->directory.'/application.sqlite') !== $this->databaseState) {
            throw new RuntimeException;
        }
        foreach ($this->directories as $path => $expected) {
            if (self::directory($path) !== $expected) { throw new RuntimeException; }
        }
        if ($checkConfiguration) {
            if ($config->get('chrono_fresh_web.enabled') !== true || self::origin($config->get('app.url')) !== $this->origin
                || $config->get('chrono_runtime.read_enabled') !== true || $config->get('chrono_runtime.write_enabled') !== true) {
                throw new RuntimeException;
            }
            foreach ($this->overrides as $key => $value) {
                if ($config->get($key) !== $value) { throw new RuntimeException; }
            }
        }
    }

    /** Closed/expired/malformed setup disables ONLY setup, not ordinary login. */
    public function setupConfiguration(): array
    {
        $disabled = ['enabled' => false, 'token_sha256' => null];
        if (! $this->setupOptIn) { return $disabled; }
        try {
            if (self::directory($this->directory) !== $this->directories[$this->directory]) { return $disabled; }
            clearstatcache(true, $this->directory.'/'.FirstAdminSetupFiles::CLOSED);
            if (@lstat($this->directory.'/'.FirstAdminSetupFiles::CLOSED) !== false) {
                return $disabled; // Any existence wins BEFORE profile read, including symlink/wrong type.
            }
            [$profile] = self::json($this->directory.'/'.FirstAdminSetupFiles::PROFILE);
            $configuration = FirstAdminSetupFiles::configuration($profile, $this->manifestHash, false, time());
            clearstatcache(true, $this->directory.'/'.FirstAdminSetupFiles::CLOSED);
            if (@lstat($this->directory.'/'.FirstAdminSetupFiles::CLOSED) !== false
                || self::directory($this->directory) !== $this->directories[$this->directory]) { return $disabled; }
            return $configuration;
        } catch (Throwable) { return $disabled; }
    }

    private static function key(Repository $config): void
    {
        $key = $config->get('app.key');
        $cipher = $config->get('app.cipher');
        if (! is_string($key) || $key === '' || $cipher !== 'AES-256-CBC' || $config->get('app.previous_keys', []) !== []) {
            throw new LogicException('A separately supplied fresh Laravel encryption key is required.');
        }
        if (str_starts_with($key, 'base64:')) { $key = base64_decode(substr($key, 7), true); }
        if (! is_string($key) || ! Encrypter::supported($key, $cipher)) {
            throw new LogicException('A separately supplied fresh Laravel encryption key is required.');
        }
    }

    private static function origin(mixed $url): string
    {
        if (! is_string($url)) { throw new LogicException('A trusted canonical HTTPS root origin is required.'); }
        try { $parts = @parse_url($url); } catch (Throwable) { $parts = false; }
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || ! in_array($parts['path'] ?? '', ['', '/'], true)
            || ! is_string($parts['host'] ?? null) || $parts['host'] !== strtolower($parts['host'])
            || preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/D', $parts['host']) !== 1
            || str_contains($parts['host'], '..')) {
            throw new LogicException('A trusted canonical HTTPS root origin is required.');
        }
        foreach (explode('.', $parts['host']) as $label) {
            if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $label) !== 1) {
                throw new LogicException('A trusted canonical HTTPS root origin is required.');
            }
        }
        $origin = 'https://'.$parts['host'].(isset($parts['port']) && $parts['port'] !== 443 ? ':'.$parts['port'] : '');
        if (! in_array($url, [$origin, $origin.'/'], true)) { throw new LogicException('A trusted canonical HTTPS root origin is required.'); }
        return $origin;
    }

    private static function seconds(mixed $value): int
    {
        // ENV numbers are strings. Reject whitespace, signs, floats and overflow rather than cast them.
        if (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1 && (string) (int) $value === $value) {
            $value = (int) $value;
        }
        if (! is_int($value) || $value < 1) { throw new LogicException('Explicit positive fresh session durations are required.'); }
        return $value;
    }

    private static function directory(string $path): array
    {
        if ($path === '' || $path[0] !== '/' || str_contains($path, "\0")
            || array_intersect(explode('/', substr($path, 1)), ['', '.', '..']) !== []) { throw new RuntimeException; }
        $current = '';
        self::ancestor('/');
        foreach (explode('/', substr($path, 1)) as $part) { $current .= '/'.$part; $state = self::ancestor($current); }
        if (realpath($path) !== $path || ($state['mode'] & 07777) !== 0700
            || $state['uid'] !== posix_geteuid() || ! is_writable($path)) { throw new RuntimeException; }
        return array_intersect_key($state, array_flip(['dev', 'ino', 'uid', 'mode']));
    }

    private static function ancestor(string $path): array
    {
        clearstatcache(true, $path);
        $state = @lstat($path);
        if ($state === false || ($state['mode'] & 0170000) !== 0040000
            || ! in_array($state['uid'], [0, posix_geteuid()], true)
            || (($state['mode'] & 0022) !== 0 && ($state['mode'] & 01000) === 0)) { throw new RuntimeException; }
        return $state;
    }

    private static function file(string $path, mixed $handle = null): array
    {
        clearstatcache(true, $path);
        $state = @lstat($path);
        $keys = array_flip(['dev', 'ino', 'uid', 'mode', 'nlink']);
        if ($state === false || ($state['mode'] & 0170000) !== 0100000 || ($state['mode'] & 07777) !== 0600
            || $state['uid'] !== posix_geteuid() || $state['nlink'] !== 1 || realpath($path) !== $path) { throw new RuntimeException; }
        if ($handle !== null) {
            $opened = @fstat($handle);
            if ($opened === false || array_intersect_key($opened, $keys) !== array_intersect_key($state, $keys)) { throw new RuntimeException; }
        }
        return array_intersect_key($state, $keys);
    }

    /** Only the two FIXED metadata basenames call this; canonical creator encoding rejects duplicate keys. */
    private static function json(string $path): array
    {
        $before = self::file($path); // Refuse link/mode before fopen; parent trust is checked by caller.
        $handle = @fopen($path, 'rb');
        if ($handle === false) { throw new RuntimeException; }
        try {
            if (self::file($path, $handle) !== $before) { throw new RuntimeException; }
            $opened = fstat($handle);
            if ($opened['size'] < 1 || $opened['size'] > 4096) { throw new RuntimeException; }
            $bytes = stream_get_contents($handle, 4097);
            $after = fstat($handle);
            if (! is_string($bytes) || strlen($bytes) !== $opened['size'] || $after['size'] !== $opened['size']
                || $after['mtime'] !== $opened['mtime'] || $after['ctime'] !== $opened['ctime']
                || self::file($path, $handle) !== $before) { throw new RuntimeException; }
            $decoded = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($decoded) || $bytes !== json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n") {
                throw new RuntimeException;
            }
            return [$decoded, hash('sha256', $bytes), $before];
        } finally { fclose($handle); }
    }

    private static function assets(string $public): void
    {
        foreach ([
            'assets/kolibri_icon.png' => 'b450f436baec5dbde264c4fe21d9001c0b466ff44ae1311f5919315bd32430e4',
            'assets/css/main.css' => 'd293dcf9d31e0adfc0c6cda1c2f03f4c57afe325c79e223794460dc9f94c4e6f',
            'assets/css/app_ui.css' => '4f8bf76f83a5c5d3ea3807818a792aa2b4c12ec83aaf46a33342d1aadf4adc1e',
            'assets/js/main.js' => 'd5786e6ebe049297ef5c38499a598104e2edf0e2b97960401daf21c604879365',
            'assets/js/attendance.js' => '659c1b1f44268726cb4efe9958dfbdd1f60fc5b3578913fc460df52bd2c7d374',
            'assets/js/chrono-local-web.js' => 'b55caaaa3396f51361426a039bfc929e15f0618f91027bc57ed1617252ad3924',
        ] as $name => $hash) {
            $path = $public.'/'.$name;
            if (is_link($path) || ! is_file($path) || ! is_readable($path)
                || ! hash_equals($hash, hash_file('sha256', $path) ?: '')) { throw new RuntimeException; }
        }
    }
}
