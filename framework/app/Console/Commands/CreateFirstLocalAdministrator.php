<?php

namespace App\Console\Commands;

use App\Auth\LegacyUser;
use App\Auth\FirstAdminSetupInputRejected;
use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\RuntimeDatabase;
use App\Setup\FirstAdminSetupFiles;
use App\View\ExistingUi\FirstAdminSetupPage;
use Illuminate\Console\Command;
use Illuminate\Contracts\Hashing\Hasher;
use RuntimeException;
use Throwable;

/** Explicit local NEW-V5 operator action; never a web endpoint or password reset. */
final class CreateFirstLocalAdministrator extends Command
{
    protected $signature = 'chrono:first-admin
        {--instance= : Existing canonical private NEW V5 instance directory}
        {--new-only : Create only the first account of an empty NEW V5 installation}
        {--stopped : Confirm every web worker/request is stopped and drained}';

    protected $description = 'Interactively create the first local administrator and close setup; no setup token or server startup.';

    public function handle(): int
    {
        try {
            $instance = $this->option('instance');
            $config = $this->laravel->make('config');
            if ($this->option('new-only') !== true || $this->option('stopped') !== true
                || ! is_string($instance) || $instance === '' || ! $this->input->isInteractive()
                || ! function_exists('stream_isatty') || ! stream_isatty(STDIN) || ! stream_isatty(STDOUT)
                || $config->get('app.debug', false) !== false || $config->get('hashing.driver') !== 'bcrypt'
                || $config->get('auth.providers.chrono_users') !== ['driver' => 'eloquent', 'model' => LegacyUser::class]
                || $config->get('chrono_runtime.read_enabled', false) !== false
                || $config->get('chrono_runtime.write_enabled', false) !== false
                || $config->get('chrono_fresh_web.enabled', false) !== false
                || $this->laravel->bound(RuntimeDatabase::class) || $this->laravel->bound(AttendanceWriter::class)
                || $this->laravel->make('db')->getConnections() !== []) {
                throw new RuntimeException;
            }
            $result = FirstAdminSetupFiles::createLocalAdministrator($instance, $this->laravel->make(Hasher::class), fn (): array => [
                'username' => $this->ask('Administrator username', FirstAdminSetupPage::STANDARD_USERNAME),
                'email' => $this->ask('Administrator email'),
                // Never fall back to visible input when the terminal cannot hide it.
                'password' => $this->secret('Choose your own administrator password (12-72 bytes)', false),
                'password_confirmation' => $this->secret('Confirm your administrator password', false),
            ]);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (FirstAdminSetupInputRejected) {
            $this->error('Enter a valid username/email and matching chosen passwords of 12-72 bytes. This input refusal created no account; correct the details before running the command again.');

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Local first-administrator setup not confirmed. Keep web workers stopped and inspect the private instance and login state locally; no automatic retry.');

            return self::FAILURE;
        }
    }
}
