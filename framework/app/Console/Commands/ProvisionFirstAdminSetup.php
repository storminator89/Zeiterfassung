<?php

namespace App\Console\Commands;

use App\Setup\FirstAdminSetupFiles;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class ProvisionFirstAdminSetup extends Command
{
    protected $signature = 'chrono:setup-token
        {action : prepare or close; no show/reset/rotate/reopen operation}
        {--instance= : Existing canonical private NEW Fresh4 instance directory}
        {--stopped : Confirm all web workers/requests are stopped and drained}';

    protected $description = 'Local operator setup-token files only; never prints a secret, creates accounts or starts web processes.';

    public function handle(): int
    {
        try {
            $action = $this->argument('action');
            $instance = $this->option('instance');
            if (! in_array($action, ['prepare', 'close'], true) || $this->option('stopped') !== true
                || ! is_string($instance) || $instance === '') { throw new RuntimeException; }
            $result = $action === 'prepare' ? FirstAdminSetupFiles::prepareNew($instance) : FirstAdminSetupFiles::close($instance);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            // Never forward native exception/token/JSON/SQL/path diagnostics.
            $this->error('Setup-token operation not confirmed. Keep web workers stopped and inspect the private instance locally; no automatic retry.');

            return self::FAILURE;
        }
    }
}
