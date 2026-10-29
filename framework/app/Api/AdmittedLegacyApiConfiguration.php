<?php

namespace App\Api;

use App\Auth\WriterLoginBudget;
use App\Api\Management\ManagedApiCapability;
use App\FreshWeb\FreshWebConfiguration;
use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\RuntimeAdmission;
use App\LegacyDatabase\RuntimeDatabase;
use App\Providers\SharedLoginBudgetServiceProvider;
use Illuminate\Foundation\Application;
use JsonSerializable;
use LogicException;

/** Pure immutable selection; never admits a physical key/database by construction. */
final readonly class AdmittedLegacyApiConfiguration implements JsonSerializable
{
    private function __construct(
        public string $origin,
        private array $settings,
        private FreshWebConfiguration $fresh,
        private WriterLoginBudget $budget,
        private WriterApiLoginBudget $apiBudget,
        private ExistingApiFileSigningKey $key,
        private ?RuntimeDatabase $v5Runtime,
        private ?ManagedApiCapability $managed = null,
    ) {}

    public static function fromApplication(Application $app): ?self
    {
        $config = $app->make('config');
        $enabled = $config->get('chrono_api.enabled', false);
        if (! is_bool($enabled)) { throw new LogicException('API composition requires an explicit boolean opt-in.'); }
        $managed = ! $enabled && $app->bound(ManagedApiCapability::class) ? $app->make(ManagedApiCapability::class) : null;
        $managedAdmission = $managed?->admission();
        if (! $enabled && $managedAdmission === null) { return null; }
        if ($app->configurationIsCached() || $app->routesAreCached()) {
            throw new LogicException('API composition requires uncached configuration and routes.');
        }
        foreach ([self::class, LegacyApiTokenKey::class, ExistingApiFileSigningKey::class, ExistingApiKeyAdmission::class,
            AdmittedApiSigningKey::class, LegacyApiLogin::class, LegacyApiIdentity::class] as $service) {
            if ($app->bound($service) || $app->resolved($service)) {
                throw new LogicException('API composition must not replace another key or resolved API profile.');
            }
        }
        $api = $config->get('chrono_api');
        $manifest = $config->get('chrono_runtime.admission');
        if (! is_array($api) || count($api) !== 2 || array_diff(['enabled', 'existing_key_admission'], array_keys($api)) !== []
            || ($managedAdmission === null && ! is_array($api['existing_key_admission'])) || ! is_array($manifest)
            || ! $app->bound(FreshWebConfiguration::class) || ! $app->bound(RuntimeDatabase::class)
            || ! $app->bound(AttendanceWriter::class) || ! SharedLoginBudgetServiceProvider::selected($app)) {
            throw new LogicException('The complete fresh profile, shared budget and existing key metadata are required.');
        }
        $database = RuntimeAdmission::fromConfiguration($manifest);
        if (! in_array($database->schemaProfile, ['intake-policy-v4', 'account-lifecycle-v5'], true)) {
            throw new LogicException('The admitted V4 or V5 API database profile is required.');
        }
        $v5 = $database->schemaProfile === 'account-lifecycle-v5';
        $v5Runtime = self::requireAccountProfile($app, $v5);
        $fresh = $app->make(FreshWebConfiguration::class);
        $budget = $app->make(WriterLoginBudget::class);
        $apiBudget = $app->make(LegacyApiLoginBudget::class);
        if (! $fresh instanceof FreshWebConfiguration || ! $budget instanceof WriterLoginBudget
            || ! $apiBudget instanceof WriterApiLoginBudget || ! $apiBudget->shares($budget)) {
            throw new LogicException('The exact selected shared login profile is required.');
        }
        $settings = [
            'chrono_api' => $api, 'chrono_fresh_web.enabled' => true, 'chrono_login_budget.enabled' => true,
            'chrono_runtime.read_enabled' => true, 'chrono_runtime.write_enabled' => true,
            'chrono_runtime.admission' => $manifest, 'database.connections.chrono' => $database->configuration(),
            'app.env' => 'production', 'app.debug' => false, 'app.url' => $config->get('app.url'),
            'auth.guards.chrono_api' => ['driver' => 'chrono-legacy-jwt'], 'auth.defaults.guard' => 'web',
            'hashing.driver' => 'bcrypt', 'hashing.bcrypt.verify' => false, 'hashing.rehash_on_login' => false,
        ];
        foreach ($settings as $name => $value) {
            if ($config->get($name) !== $value) { throw new LogicException('The selected API configuration is incomplete.'); }
        }
        $admission = $managedAdmission ?? ExistingApiKeyAdmission::fromConfiguration($api['existing_key_admission'], $database);
        if ($managedAdmission !== null && (! $v5 || $api['enabled'] !== false
            || $api['existing_key_admission'] !== null
            || $admission->databaseProfileSha256 !== ExistingApiKeyAdmission::databaseProfileDigest($database))) {
            throw new LogicException('Managed API requires the exact current V5 key source.');
        }
        // The existing source is PURE until secret(); never read/adopt/create here.
        return new self($fresh->origin, $settings, $fresh, $budget, $apiBudget, new ExistingApiFileSigningKey($admission), $v5Runtime, $managed);
    }

    /** Pure source object, NOT a key value, liveness check or physical admission. */
    public function key(): ExistingApiFileSigningKey
    {
        return $this->key;
    }

    public function requiresOperatingPolicy(): bool
    {
        return $this->v5Runtime?->isAccountLifecycleV5() ?? false;
    }

    /** Pure per-request consistency check; physical DB/key checks stay in existing sources. */
    public function assertCurrent(Application $app): void
    {
        $config = $app->make('config');
        foreach ($this->settings as $name => $value) {
            if ($config->get($name) !== $value) { throw new LogicException('The selected API configuration changed.'); }
        }
        if (! $app->bound(self::class) || $app->make(self::class) !== $this
            || ! $app->bound(FreshWebConfiguration::class) || $app->make(FreshWebConfiguration::class) !== $this->fresh
            || ! $app->bound(LegacyApiTokenKey::class) || $app->make(LegacyApiTokenKey::class) !== $this->key
            || ! $app->bound(WriterLoginBudget::class) || $app->make(WriterLoginBudget::class) !== $this->budget
            || ! $app->bound(LegacyApiLoginBudget::class) || $app->make(LegacyApiLoginBudget::class) !== $this->apiBudget
            || ! $app->bound(RuntimeDatabase::class) || ! $app->bound(AttendanceWriter::class)
            || ! SharedLoginBudgetServiceProvider::selected($app)) {
            throw new LogicException('The selected API service profile changed.');
        }
        $this->managed?->assertCurrent();
        self::requireAccountProfile($app, $this->v5Runtime !== null, $this->v5Runtime);
    }

    /** Pure metadata only; never opens the Runtime, key or operation writer. */
    private static function requireAccountProfile(Application $app, bool $v5, ?RuntimeDatabase $expected = null): ?RuntimeDatabase
    {
        if (! $v5) { return null; } // Preserve the original V4 composition path.
        $runtime = $app->make(RuntimeDatabase::class);
        if (! $runtime instanceof RuntimeDatabase || ($expected !== null && $runtime !== $expected)
            || ! $runtime->isAccountLifecycleV5()
            || ! $app->bound(AccountLifecycleApiIdentity::class)
            || ! ($identity = $app->make(AccountLifecycleApiIdentity::class)) instanceof AccountLifecycleApiIdentity
            || ! $identity->requiresGeneration()) {
            throw new LogicException('The exact current V5 API generation profile is required.');
        }

        return $runtime;
    }

    public function __debugInfo(): array { return ['source' => 'explicit admitted legacy API composition']; }
    public function jsonSerialize(): array { return $this->__debugInfo(); }
    public function __serialize(): array { throw new LogicException('API composition must not be serialized.'); }
}
