<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\Auth\ExistingAccountUserProvider;
use Illuminate\Auth\SessionGuard;
use App\Auth\AccountLifecycleWebIdentity;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use InvalidArgumentException;
use LogicException;

/** Current local guard boundary; the fixed writer rechecks Admin under its lock. */
final class LegacyLocalWebPauseSettings implements LocalWebPauseSettings
{
    /** @param Closure(): AttendanceWriter $writer Trusted operation-scope resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer,
        private readonly ?AccountLifecycleWebIdentity $lifecycle = null) {}

    public function apply(array $command): void
    {
        $guard = $this->auth->guard('web');
        $actor = $guard->user();
        $provider = $guard instanceof SessionGuard ? $guard->getProvider() : null;
        $profile = $provider instanceof ExistingAccountUserProvider;
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1
            || (! $profile && $actor->auth_source !== 'local')
            || ! in_array($actor->role, ['user', 'supervisor', 'admin'], true)) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Nur die Verwaltung darf Pauseneinstellungen ändern.');
        }
        if (! isset($command['update_pause_settings'])
            || array_diff(array_keys($command), self::COMMAND_FIELDS) !== []) {
            throw new InvalidArgumentException('Diese Verwaltungsaktion ist in der Laravel-Ansicht noch nicht verbunden.');
        }

        // Never resolve a writer for nonadmins or an unsupported/mixed command.
        $context = $profile ? $provider->context($actor) : null;
        $authority = $this->lifecycle?->capture($actor);
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('A trusted admitted operation-scoped attendance writer is required.');
        }
        try {
            $writer->pauseSettings(
                (int) $actor->getKey(), $command['hours_threshold'] ?? null, $command['minimum_pause'] ?? null, $authority, $context,
            );
        } catch (\AttendanceError $error) {
            if ($authority !== null && $error->status === 401) { throw new AuthenticationException('Unauthenticated.', ['web']); }
            throw $error;
        }
    }
}
