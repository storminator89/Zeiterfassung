<?php

namespace App\FreshWeb;

use App\Auth\FirstAdminSetup;
use App\Auth\LegacyUser;
use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\RuntimeAdmission;
use App\LegacyDatabase\RuntimeDatabase;
use App\Setup\FirstAdminSetupFiles;
use App\Terminal\AdmittedTerminalConfiguration;
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
        private string $keyFingerprint,
        private string $directory,
        private array $directories,
        private array $manifest,
        private string $manifestHash,
        private array $manifestState,
        private array $databaseState,
        private bool $setupOptIn,
        private bool $nonExpiringSessionLock,
        private bool $ldapLogin,
        private bool $terminalHttp,
        private ?FreshWebLdapSyncProfile $ldapSync,
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
            || $config->get('auth.providers.chrono_users') !== ['driver' => 'eloquent', 'model' => LegacyUser::class]) {
            throw new LogicException('The explicit fresh local web/runtime profile is not admitted.');
        }
        $keyFingerprint = self::key($config);
        $origin = self::origin($config->get('app.url'));
        $setup = $config->get('chrono_fresh_web.setup_enabled', false);
        if (! is_bool($setup)) { throw new LogicException('Fresh setup requires a separate boolean opt-in.'); }
        $terminal = $config->get('chrono_terminal');
        // Device ingress becomes available only after the first administrator has closed setup.
        $setupDirectory=$config->get('chrono_fresh_web.instance_directory');
        if (is_string($setupDirectory) && !is_file($setupDirectory.'/setup-closed') && is_array($terminal)) {
            $terminal=['http_enabled'=>false,'existing_keys_enabled'=>false,'existing_key_admissions'=>null];
            $config->set('chrono_terminal', $terminal);
        }
        if (! is_array($terminal) || ! is_bool($terminal['http_enabled'] ?? null)
            || ($terminal['http_enabled'] && ($setup || ($terminal['existing_keys_enabled'] ?? null) !== true))) {
            throw new LogicException('Fresh terminal HTTP requires separate existing roots and closed setup mode.');
        }
        $ldap = self::ldapSettings($config->get('chrono_ldap'));
        if ($ldap['enabled'] && $setup) {
            throw new LogicException('Existing LDAP login requires the separate closed setup mode.');
        }
        $lock = self::lockSeconds($config->get('chrono_fresh_web.session_lock_seconds'));
        $wait = self::seconds($config->get('chrono_fresh_web.session_wait_seconds'));
        if ($lock !== 0 && $wait > $lock) {
            throw new LogicException('Explicit positive fresh session lock/wait durations are required.');
        }
        if (! function_exists('posix_geteuid')) { throw new LogicException('Fresh web requires the supported local Unix runtime.'); }

        try {
            $directory = $config->get('chrono_fresh_web.instance_directory');
            if (! is_string($directory) || preg_match('/^chrono-fresh-[a-f0-9]{24}$/D', basename($directory)) !== 1) {
                throw new RuntimeException;
            }
            self::requireNoUpgradeFence($directory);
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
            if (! in_array($admission->schemaProfile, ['intake-policy-v4', 'account-lifecycle-v5', 'vacation-requests-v7'], true)
                || $admission->path !== $directory.'/application.sqlite') {
                throw new RuntimeException;
            }
            $v5 = $admission->isGenerationBoundAccountProfile();
            if (($v5 && $lock !== 0) || (! $v5 && $lock === 0)) { throw new RuntimeException; }
            if ($v5) {
                // V5 stays a distinct generation-aware runtime. API retains its
                // separate exact key/generation/shared-budget admission provider.
                $apiEnabled = $config->get('chrono_api.enabled', false);
                if (! is_bool($apiEnabled)
                    || $config->get('chrono_login_budget.enabled', false) !== true) {
                    throw new RuntimeException;
                }
            }
            $databaseState = self::file($admission->path);
            if ($databaseState['dev'] !== $admission->device || $databaseState['ino'] !== $admission->inode) {
                throw new RuntimeException;
            }
            $sync = FreshWebLdapSyncProfile::fromConfiguration($config->get('chrono_ldap_sync.fresh'), $admission,
                [$app->basePath(), dirname($app->basePath()), $app->publicPath()]);
            if ($sync !== null && $setup) { throw new RuntimeException; }
            if (($ldap['enabled'] || $sync !== null || $terminal['http_enabled']) && ! self::setupClosed($directory)) { throw new RuntimeException; }
            if (@lstat($directory.'/'.FirstAdminSetupFiles::LOCAL_BROWSER_PROFILE) !== false && ! self::setupClosed($directory)) {
                // An open local first-user installation is not the closed setup
                // prerequisite of API/LDAP/terminal composition, even though the
                // separate historical token opt-in remains false.
                if (! $v5 || $setup || $ldap['enabled'] || $sync !== null || $terminal['http_enabled']
                    || $apiEnabled) { throw new RuntimeException; }
                FirstAdminSetupFiles::localBrowserConfiguration($directory, $origin);
            }
            self::assets($app->publicPath());
            $namespace = 'chrono_fresh_'.substr(hash('sha256', $directory.'\0'.$admission->device.':'.$admission->inode), 0, 24);
            $overrides = [
                'chrono_runtime.admission' => $manifest,
                // Existing persisted roles; no enrollment, role promotion or source conversion.
                'auth.providers.chrono_users' => ['driver' => $ldap['enabled'] ? 'chrono-existing' : 'eloquent', 'model' => LegacyUser::class],
                'chrono_ldap' => $ldap,
                'chrono_terminal' => $terminal,
                'chrono_ldap_sync.fresh' => $sync?->configuration() ?? ['enabled' => false],
                'session.driver' => 'file', 'session.files' => $directory.'/sessions',
                'session.cookie' => $namespace, 'session.secure' => str_starts_with($origin, 'https://'), 'session.http_only' => true,
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
            if ($admission->isGenerationBoundAccountProfile()) {
                // These selected capabilities are rechecked with all other overrides.
                $overrides += ['chrono_api.enabled' => $apiEnabled, 'chrono_login_budget.enabled' => true];
            }
            $profile = new self($origin, $keyFingerprint, $directory, $directories, $manifest, $manifestHash, $manifestState,
                $databaseState, $setup, $v5, $ldap['enabled'], $terminal['http_enabled'], $sync, $overrides);
            $profile->assertCurrent($config, false);
            return $profile;
        } catch (Throwable) {
            throw new LogicException('The private fresh web instance is not admitted.');
        }
    }

    private static function requireNoUpgradeFence(string $directory): void
    {
        clearstatcache(true,$directory.'/vacation-v7-upgrade.json');
        if (@lstat($directory.'/vacation-v7-upgrade.json') !== false) {
            throw new LogicException('The stopped vacation upgrade must complete before web admission.');
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
        self::requireNoUpgradeFence($this->directory);
        // These initial prerequisites are not configuration overrides. Recheck
        // them before private metadata and before cookie/session middleware too.
        if ($config->get('app.env') !== 'production' || $config->get('app.debug') !== false
            || $config->get('hashing.driver') !== 'bcrypt' || $config->get('auth.defaults.guard') !== 'web'
            || $config->get('auth.guards.web') !== ['driver' => 'session', 'provider' => 'chrono_users']
            || $config->get('chrono_terminal') !== $this->overrides['chrono_terminal']
            || ! hash_equals($this->keyFingerprint, self::key($config))) {
            throw new RuntimeException;
        }
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
        if (($this->ldapLogin || $this->ldapSync !== null || $this->terminalHttp) && ! self::setupClosed($this->directory)) { throw new RuntimeException; }
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

    /** Pure selection from the admitted schema; no route/request input or file I/O. */
    public function requiresNonExpiringSessionLock(): bool { return $this->nonExpiringSessionLock; }

    /** Pure admitted metadata; does not resolve files, keys or a directory. */
    public function ldapSyncProfile(): ?FreshWebLdapSyncProfile { return $this->ldapSync; }

    public function ldapLoginEnabled(): bool { return $this->ldapLogin; }

    /** Both profiles must select the exact same runtime/root composition. */
    public function assertTerminalCurrent(Application $app): void
    {
        if (! $this->terminalHttp) { return; }
        if (! $app->bound(AdmittedTerminalConfiguration::class)) {
            throw new LogicException('Fresh terminal HTTP requires its separately admitted existing roots.');
        }
        $app->make(AdmittedTerminalConfiguration::class)->assertCurrent($app);
    }

    /** Closed/expired/malformed setup disables ONLY setup, not ordinary login. */
    public function setupConfiguration(): array
    {
        $disabled = ['enabled' => false, 'token_sha256' => null];
        try {
            if (self::directory($this->directory) !== $this->directories[$this->directory]) { return $disabled; }
            if (self::setupClosed($this->directory)) {
                return $disabled; // Any existence wins BEFORE profile read, including symlink/wrong type.
            }
            if (@lstat($this->directory.'/'.FirstAdminSetupFiles::LOCAL_BROWSER_PROFILE) !== false) {
                // Installation policy, not proof of a visitor's identity. Only a
                // NEW V5 local-origin profile can select the first-user path.
                if ($this->setupOptIn || $this->ldapLogin || $this->ldapSync !== null || $this->terminalHttp
                    || ($this->overrides['chrono_api.enabled'] ?? false) === true) { return $disabled; }
                $configuration = FirstAdminSetupFiles::localBrowserConfiguration($this->directory, $this->origin);
                if (self::setupClosed($this->directory)
                    || self::directory($this->directory) !== $this->directories[$this->directory]) { return $disabled; }

                return $configuration;
            }
            if (! $this->setupOptIn) { return $disabled; }
            [$profile] = self::json($this->directory.'/'.FirstAdminSetupFiles::PROFILE);
            $configuration = FirstAdminSetupFiles::configuration($profile, $this->manifestHash, false, time());
            if (self::setupClosed($this->directory)
                || self::directory($this->directory) !== $this->directories[$this->directory]) { return $disabled; }
            return $configuration;
        } catch (Throwable) { return $disabled; }
    }

    /** Pure operator metadata, not directory discovery, bind credentials, a CA read or a network probe. */
    private static function ldapSettings(mixed $settings): array
    {
        if (! is_array($settings) || ! is_bool($settings['enabled'] ?? null)) {
            throw new LogicException('Explicit existing LDAP login settings are required.');
        }
        if (! $settings['enabled']) {
            // Disabled metadata never makes local break-glass depend on LDAP configuration/files.
            return ['enabled' => false, 'uri' => null, 'base_dn' => null, 'ca_file' => null];
        }
        $keys = ['enabled', 'uri', 'base_dn', 'ca_file'];
        if (count($settings) !== count($keys) || array_diff($keys, array_keys($settings)) !== []) {
            throw new LogicException('Complete login-only LDAP metadata is required.');
        }
        foreach (['uri', 'base_dn', 'ca_file'] as $key) {
            if (! is_string($settings[$key]) || $settings[$key] === '' || trim($settings[$key]) !== $settings[$key]
                || str_contains($settings[$key], "\0")) {
                throw new LogicException('Complete login-only LDAP metadata is required.');
            }
        }
        try { $parts = @parse_url($settings['uri']); } catch (Throwable) { $parts = false; }
        if (! is_array($parts) || array_diff(array_keys($parts), ['scheme', 'host', 'port']) !== []
            || ($parts['scheme'] ?? null) !== 'ldap' || ! is_string($parts['host'] ?? null)
            || ! is_int($parts['port'] ?? null) || $parts['port'] < 1 || $parts['port'] > 65535
            || $settings['uri'] !== 'ldap://'.$parts['host'].':'.$parts['port']) {
            throw new LogicException('A single explicit StartTLS login URI is required.');
        }
        $host = $parts['host'];
        $valid = str_starts_with($host, '[') && str_ends_with($host, ']')
            ? filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            : filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
        if (! $valid || $settings['ca_file'][0] !== '/'
            || array_intersect(explode('/', substr($settings['ca_file'], 1)), ['', '.', '..']) !== []) {
            throw new LogicException('Explicit login host and canonical absolute CA metadata are required.');
        }
        // Do not stat/open the CA, test extension availability, or contact LDAP here.
        // The unchanged verifier does that only for an actual LDAP user's credential check.
        return $settings;
    }

    private static function setupClosed(string $directory): bool
    {
        clearstatcache(true, $directory.'/'.FirstAdminSetupFiles::CLOSED);
        return @lstat($directory.'/'.FirstAdminSetupFiles::CLOSED) !== false;
    }

    private static function key(Repository $config): string
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
        // Retain identity only, never another raw key copy or an Encrypter.
        return hash('sha256', $key);
    }

    private static function origin(mixed $url): string
    {
        if (! is_string($url)) { throw new LogicException('A canonical HTTPS or private-network HTTP root origin is required.'); }
        try { $parts = @parse_url($url); } catch (Throwable) { $parts = false; }
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http','https'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || ! in_array($parts['path'] ?? '', ['', '/'], true)
            || ! is_string($parts['host'] ?? null) || $parts['host'] !== strtolower($parts['host'])
            || preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/D', $parts['host']) !== 1
            || str_contains($parts['host'], '..')) {
            throw new LogicException('A canonical HTTPS or private-network HTTP root origin is required.');
        }
        foreach (explode('.', $parts['host']) as $label) {
            if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $label) !== 1) {
                throw new LogicException('A canonical HTTPS or private-network HTTP root origin is required.');
            }
        }
        $scheme=$parts['scheme'];
        if ($scheme === 'http' && !LocalNetworkOrigin::allowsHttp($parts['host'])) { throw new LogicException('HTTP requires a local-network origin.'); }
        $defaultPort=$scheme === 'https' ? 443 : 80;
        $origin = $scheme.'://'.$parts['host'].(isset($parts['port']) && $parts['port'] !== $defaultPort ? ':'.$parts['port'] : '');
        if (! in_array($url, [$origin, $origin.'/'], true)) { throw new LogicException('A canonical HTTPS or private-network HTTP root origin is required.'); }
        return $origin;
    }

    /** Zero is the locked FileStore non-expiring lease, allowed only after V5 admission. */
    private static function lockSeconds(mixed $value): int
    {
        return $value === 0 || $value === '0' ? 0 : self::seconds($value);
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
            'assets/css/tokens.css' => 'd4a0b55858679a731daec994141a8c11b5567ec6266432b04c653d5a71c631b2',
            'assets/css/base.css' => 'b1af34b2cddc43ec320d7c664c2e94a529057cbab0654e09d8784ccaf4ba3b5d',
            'assets/vendor/tabler/1.7.1/tabler.min.css' => 'dcf84d7b4319c29ab33450bfe2a7bbf607458d994ef71023f7509e912cafdbd5',
            'assets/vendor/tabler-icons/3.50.0/icons.svg' => '201655260188504fa0160c0f0ae2f49a199cc71c51364bccaf984460015f1f65',
            'assets/illustrations/calendar-plan.png' => '6bf044fd0d3ce2fa6c4f85fb4b851cfb64e7df251f8e0b3f22682b2e68b03734',
            'assets/illustrations/system-access.png' => '5bdfc67370aa4fb5f1b589d70092c90afcc2411971ad475febc806b757aef9ff',
            'assets/illustrations/time-ledger.png' => '823f82d2b2c4640c3f3627c758ba834378712ce3990a65577d085df8c3162fab',
            'assets/css/app_ui.css' => '34da46f3f00c6735feba0699a574ffc5da3e6f1558fb772d5c2b592e14f0b26f',
            'assets/js/main.js' => '11bb9d367475614eefd30fba8c93055f74ef78d875f02d8ef96110c20340775e',
            'assets/js/attendance.js' => '3b87890d2ec04eeccbeeaa6a28b0d7f6d420f24d9d9910e9abc75ba5fdf5a9a4',
            'assets/js/chrono-local-web.js' => '9940b5723b21a2fa21f39db9471523ff5818bc6946a480607c2e5cac20516b3b',
        ] as $name => $hash) {
            $path = $public.'/'.$name;
            if (is_link($path) || ! is_file($path) || ! is_readable($path)
                || ! hash_equals($hash, hash_file('sha256', $path) ?: '')) { throw new RuntimeException; }
        }
    }
}
