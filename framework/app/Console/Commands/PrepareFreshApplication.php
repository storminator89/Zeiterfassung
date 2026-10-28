<?php

namespace App\Console\Commands;

use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\FreshApplicationFiles;
use App\LegacyDatabase\RuntimeDatabase;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/** Explicit new-file preparation only; never starts or activates the application. */
final class PrepareFreshApplication extends Command
{
    protected $signature = 'chrono:prepare-fresh
        {--private-parent= : Canonical existing owner-only directory outside checkout and webroots}
        {--new-only : Explicitly prepare a NEW empty instance, never reuse/reset an existing database}';

    protected $description = 'Prepare new private empty V4 database/admission files; no users, secrets or runtime activation.';

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
            $result = FreshApplicationFiles::createNew($parent);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Fresh preparation not confirmed. Keep the private parent for operator inspection; no activation or automatic retry.');

            return self::FAILURE;
        }
    }
}
