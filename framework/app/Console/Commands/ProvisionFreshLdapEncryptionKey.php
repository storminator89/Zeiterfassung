<?php

namespace App\Console\Commands;

use App\Setup\FreshLdapEncryptionKeyFiles;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class ProvisionFreshLdapEncryptionKey extends Command
{
    protected $signature = 'chrono:ldap-encryption-key
        {--instance= : Existing canonical private EMPTY NEW V4 instance, before setup-token preparation}
        {--new-only : Confirm first installation, never existing-data recovery/reset/rotation}
        {--stopped : Confirm all web/API/terminal/worker requests stopped and drained}';

    protected $description = 'Prepare ONLY a NEW private native-format encryption.key; no secret output or activation.';

    public function handle(): int
    {
        try {
            $instance = $this->option('instance');
            $config = $this->laravel->make('config');
            if (! is_string($instance) || $instance === '' || $this->option('new-only') !== true || $this->option('stopped') !== true
                || $config->get('chrono_fresh_web.enabled', false) !== false
                || $config->get('chrono_runtime.read_enabled', false) !== false
                || $config->get('chrono_runtime.write_enabled', false) !== false
                || $config->get('chrono_setup.enabled', false) !== false) { throw new RuntimeException; }
            $result = FreshLdapEncryptionKeyFiles::createNew($instance,
                [$this->laravel->basePath(), dirname($this->laravel->basePath()), $this->laravel->publicPath()]);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('LDAP root preparation not confirmed. Keep workers stopped and inspect privately; no automatic retry.');
            return self::FAILURE;
        }
    }
}
