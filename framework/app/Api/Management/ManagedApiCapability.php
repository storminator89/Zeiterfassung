<?php

namespace App\Api\Management;

use App\Api\ExistingApiKeyAdmission;
use Closure;
use App\FreshWeb\FreshWebConfiguration;
use App\LegacyDatabase\RuntimeAdmission;
use App\LegacyDatabase\RuntimeDatabase;
use Illuminate\Foundation\Application;
use LogicException;
use Throwable;

/** Operator capability is NOT effective API enablement. No key generation here. */
final readonly class ManagedApiCapability
{
    private function __construct(private Application $app, private FreshWebConfiguration $fresh,
        private RuntimeDatabase $runtime, public ApiManagementFiles $files, private array $selectedApi) {}

    public static function fromApplication(Application $app, ?Closure $trustedEntropy = null): ?self
    {
        $config = $app->make('config');
        $enabled = $config->get('chrono_api_management.enabled', false);
        if (! is_bool($enabled)) { throw new LogicException('API UI requires an explicit capability boolean.'); }
        if (! $enabled || ! $app->bound(FreshWebConfiguration::class) || ! $app->bound(RuntimeDatabase::class)) { return null; }
        $runtime = $app->make(RuntimeDatabase::class);
        if (! $runtime->hasGenerationBoundAccountAuthority()) { return null; } // Never infer V6 or open its HOLD.
        $fresh = $app->make(FreshWebConfiguration::class);
        $fresh->assertCurrent($config, false);
        $admission = RuntimeAdmission::fromConfiguration($config->get('chrono_runtime.admission', []));
        if (!$admission->isGenerationBoundAccountProfile()
            || $config->get('chrono_fresh_web.setup_enabled', false) !== false
            || $config->get('chrono_login_budget.enabled', false) !== true) { return null; }
        $api = $config->get('chrono_api');
        if (! is_array($api) || array_diff(['enabled','existing_key_admission'], array_keys($api)) !== [] || count($api) !== 2) {
            throw new LogicException('The original API source configuration is required.');
        }
        try { $files = new ApiManagementFiles($admission, $trustedEntropy); }
        catch (Throwable) { return null; } // Missing/partial closure never bricks ordinary Web.
        return new self($app, $fresh, $runtime, $files, $api);
    }

    public function assertCurrent(): void
    {
        $config = $this->app->make('config');
        if ($config->get('chrono_api_management.enabled', false) !== true
            || $config->get('chrono_fresh_web.setup_enabled', false) !== false
            || $config->get('chrono_login_budget.enabled', false) !== true
            || $config->get('chrono_api') !== $this->selectedApi
            || ! $this->app->bound(self::class) || $this->app->make(self::class) !== $this
            || ! $this->app->bound(RuntimeDatabase::class) || $this->app->make(RuntimeDatabase::class) !== $this->runtime
            || ! $this->runtime->hasGenerationBoundAccountAuthority()
            || ! $this->app->bound(FreshWebConfiguration::class) || $this->app->make(FreshWebConfiguration::class) !== $this->fresh) {
            throw new LogicException('API UI capability changed.');
        }
        $this->fresh->assertCurrent($config);
    }

    /** Typed, DB-bound existing metadata only; not a secret read or effective enabled result. */
    public function admission(): ?ExistingApiKeyAdmission
    {
        $this->assertCurrent();
        try { return $this->files->metadata(); }
        catch (Throwable) { return null; } // Partial/malformed files cannot expose an API route profile.
    }
    public function legacyDefaultEnabled(): bool { $this->assertCurrent(); return $this->selectedApi['enabled'] === true; }
    public function __debugInfo(): array { return ['source' => 'operator-allowed V5 API UI capability']; }
    public function __serialize(): array { throw new LogicException('API capabilities must not be serialized.'); }
}
