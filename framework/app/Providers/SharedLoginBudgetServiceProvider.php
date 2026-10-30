<?php

namespace App\Providers;

use App\Api\LegacyApiLoginBudget;
use App\Api\WriterApiLoginBudget;
use App\Auth\LocalSession;
use App\Auth\AccountLifecycleWebIdentity;
use App\Auth\WriterLoginBudget;
use App\FreshWeb\FreshWebConfiguration;
use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\AuditContext;
use App\LegacyDatabase\AuditFunctions;
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\RuntimeAdmission;
use App\LegacyDatabase\RuntimeDatabase;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use LogicException;

/** Explicit default-off composition; register AFTER the admitted runtime provider. */
final class SharedLoginBudgetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->app->make('config');
        $enabled = $config->get('chrono_login_budget.enabled', false);
        if (! is_bool($enabled)) {
            throw new LogicException('Shared login budget requires an explicit boolean opt-in.');
        }
        if (! $enabled) { return; }
        foreach ([WriterLoginBudget::class, LegacyApiLoginBudget::class, LocalSession::class] as $service) {
            if ($this->app->bound($service) || $this->app->resolved($service)) {
                throw new LogicException('Shared login budget must not replace another login profile.');
            }
        }
        $manifest = $config->get('chrono_runtime.admission');
        if (! is_array($manifest)) {
            throw new LogicException('Shared login budget requires the complete admitted local runtime.');
        }
        $admission = RuntimeAdmission::fromConfiguration($manifest);
        self::requireProfile($this->app, $admission, $manifest);
        $app = $this->app;
        $forbidden = [dirname($app->basePath()), $app->publicPath()];
        $budget = new WriterLoginBudget(static function () use ($app, $admission, $manifest, $forbidden): AttendanceWriter {
            self::requireProfile($app, $admission, $manifest);
            // Fixed physical admission from the SAME frozen manifest as reads.
            // No arbitrary writer binding, authenticated audit scope or handle cache.
            $context = new AuditContext;
            $functions = new AuditFunctions(static fn (): AuditContext => $context);
            return (new RuntimeDatabase($admission, new OfflineFiles($forbidden)))->writer($context, $functions);
        });
        $this->app->instance(WriterLoginBudget::class, $budget);
        $this->app->instance(LegacyApiLoginBudget::class, new WriterApiLoginBudget($budget));
        $this->app->bind(LocalSession::class, static fn ($app): LocalSession =>
            new LocalSession($app->make('auth'), $budget, $app->make(AccountLifecycleWebIdentity::class)));
    }

    /** Pure route-selection check; never resolves auth, a writer, PDO, key or store. */
    public static function selected(Application $app): bool
    {
        $enabled = $app->make('config')->get('chrono_login_budget.enabled', false);
        if (! is_bool($enabled) || (! $enabled && $app->bound(WriterLoginBudget::class))) {
            throw new LogicException('Shared login budget selection is inconsistent.');
        }
        if (! $enabled) { return false; }
        if (! $app->bound(WriterLoginBudget::class) || ! $app->bound(LegacyApiLoginBudget::class)
            || ! $app->bound(LocalSession::class)) {
            throw new LogicException('The selected shared login budget profile is incomplete.');
        }
        $budget = $app->make(WriterLoginBudget::class);
        $api = $app->make(LegacyApiLoginBudget::class);
        if (! $budget instanceof WriterLoginBudget || ! $api instanceof WriterApiLoginBudget || ! $api->shares($budget)) {
            throw new LogicException('Web and API must use the same selected login budget.');
        }
        return true;
    }

    private static function requireProfile(Application $app, RuntimeAdmission $admission, array $manifest): void
    {
        $config = $app->make('config');
        if ($config->get('chrono_login_budget.enabled') !== true
            || $config->get('chrono_fresh_web.enabled') !== true || ! $app->bound(FreshWebConfiguration::class)
            || $config->get('chrono_runtime.read_enabled') !== true || $config->get('chrono_runtime.write_enabled') !== true
            || $config->get('chrono_runtime.admission') !== $manifest || ! in_array($admission->schemaProfile, ['intake-policy-v4', 'account-lifecycle-v5', 'vacation-requests-v7'], true)
            || ! $app->bound(RuntimeDatabase::class) || ! $app->bound(AttendanceWriter::class)
            || ($admission->isGenerationBoundAccountProfile()
                && ! $app->make(RuntimeDatabase::class)->hasGenerationBoundAccountAuthority())
            || $config->get('database.connections.chrono') !== $admission->configuration()) {
            throw new LogicException('Shared login budget requires the complete selected fresh local read/write profile.');
        }
    }
}
