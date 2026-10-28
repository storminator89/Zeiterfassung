<?php

namespace App\Console\Commands;

use App\Setup\FreshApiSigningKeyFiles;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class ProvisionFreshApiSigningKey extends Command
{
    protected $signature = 'chrono:api-key
        {--instance= : Existing canonical private EMPTY budget-ready Fresh4 instance}
        {--new-only : Confirm NEW installation only; never read/adopt/replace/rotate an existing API key}
        {--stopped : Confirm all workers/requests remain stopped and drained}';

    protected $description = 'Prepare a NEW private API signing key and metadata EnvironmentFile; never prints or activates them.';

    public function handle(): int
    {
        try {
            $instance = $this->option('instance');
            $config = $this->laravel->make('config');
            if (! is_string($instance) || $instance === '' || $this->option('new-only') !== true || $this->option('stopped') !== true
                || $this->laravel->configurationIsCached() || $this->laravel->routesAreCached()
                || $config->get('chrono_fresh_web.enabled', false) !== false
                || $config->get('chrono_fresh_web.setup_enabled', false) !== false
                || $config->get('chrono_runtime.read_enabled', false) !== false
                || $config->get('chrono_runtime.write_enabled', false) !== false
                || $config->get('chrono_login_budget.enabled', false) !== false
                || $config->get('chrono_api.enabled', false) !== false) { throw new RuntimeException; }
            // No environment dump, existing-key read, key value or profile output.
            $this->line(json_encode(FreshApiSigningKeyFiles::createNew($instance), JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('API-key preparation not confirmed. Keep workers stopped and inspect privately; no automatic retry.');
            return self::FAILURE;
        }
    }
}
