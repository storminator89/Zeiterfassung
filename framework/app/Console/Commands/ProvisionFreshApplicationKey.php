<?php

namespace App\Console\Commands;

use App\Setup\FreshApplicationKeyFiles;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class ProvisionFreshApplicationKey extends Command
{
    protected $signature = 'chrono:application-key
        {--instance= : Existing canonical private EMPTY Fresh4 instance}
        {--new-only : Confirm first-installation only; never read/replace/rotate/reset an existing key}
        {--stopped : Confirm all workers/requests remain stopped and drained}';

    protected $description = 'Prepare a NEW private FRAMEWORK_APP_KEY EnvironmentFile; never prints a key or activates it.';

    public function handle(): int
    {
        try {
            $instance = $this->option('instance');
            $config = $this->laravel->make('config');
            if (! is_string($instance) || $instance === '' || $this->option('new-only') !== true || $this->option('stopped') !== true
                || $config->get('chrono_fresh_web.enabled', false) !== false
                || $config->get('chrono_runtime.read_enabled', false) !== false
                || $config->get('chrono_runtime.write_enabled', false) !== false) { throw new RuntimeException; }
            // Deliberately do not inspect app.key, getenv, ENV or existing keyfiles.
            $this->line(json_encode(FreshApplicationKeyFiles::createNew($instance), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Application-key preparation not confirmed. Keep workers stopped and inspect privately; no automatic retry.');
            return self::FAILURE;
        }
    }
}
