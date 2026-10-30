<?php

namespace App\Console\Commands;

use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\FreshApplicationFiles;
use App\LegacyDatabase\RuntimeDatabase;
use App\Setup\FirstAdminSetupFiles;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/** Explicit new-file preparation only; never starts or activates the application. */
final class PrepareFreshApplication extends Command
{
    protected $signature = 'chrono:prepare-fresh
        {--private-parent= : Canonical existing owner-only directory outside checkout and webroots}
        {--new-only : Explicitly prepare a NEW empty instance, never reuse/reset an existing database}
        {--dormant-account-lifecycle-v5 : Prepare only NEW empty V5 files; runtime and setup remain unavailable}
        {--vacation-workflow-v7 : Prepare the NEW standard vacation workflow for explicit CLI first-admin setup}
        {--local-browser-first-admin : NEW standard V7 local install: first successful browser registration becomes Admin; no token}';

    protected $description = 'Prepare NEW private files: standard V7 with local first-user setup, or explicit legacy V4/V5 preparation; no users, secrets or runtime activation.';

    public function handle(): int
    {
        try {
            $parent = $this->option('private-parent');
            if ($this->option('new-only') !== true || ! is_string($parent) || $parent === ''
                || $this->laravel->make('config')->get('chrono_runtime.read_enabled', false) !== false
                || $this->laravel->make('config')->get('chrono_runtime.write_enabled', false) !== false
                || $this->laravel->bound(RuntimeDatabase::class) || $this->laravel->bound(AttendanceWriter::class)
                || $this->laravel->make('db')->getConnections() !== []) {
                throw new RuntimeException;
            }
            $localBrowser = $this->option('local-browser-first-admin') === true;
            $vacation=$localBrowser || $this->option('vacation-workflow-v7')===true;
            if ($vacation && $this->option('dormant-account-lifecycle-v5') === true) { throw new RuntimeException; }
            $result = $vacation ? FreshApplicationFiles::createNewVacationWorkflowV7($parent)
                : ($this->option('dormant-account-lifecycle-v5') === true
                ? FreshApplicationFiles::createNewDormantV5($parent)
                : FreshApplicationFiles::createNew($parent));
            if ($localBrowser) {
                $result += FirstAdminSetupFiles::prepareLocalBrowserNew($result['directory']);
                unset($result['runtime_openable'], $result['setup_provisionable']);
            }
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Fresh preparation not confirmed. Keep the private parent for operator inspection; no activation or automatic retry.');

            return self::FAILURE;
        }
    }
}
