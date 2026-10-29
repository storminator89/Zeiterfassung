<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\Auth\AccountLifecycleWebIdentity;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use InvalidArgumentException;
use LogicException;

/** Fresh local Admin; one fixed enrollment command without any signing-key dependency. */
final class LegacyLocalWebTerminalDevicePairings implements LocalWebTerminalDevicePairings
{
    /** @param Closure(): AttendanceWriter $writer Trusted operation-scoped resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer,
        private readonly ?AccountLifecycleWebIdentity $lifecycle = null) {}

    public function create(array $command): TerminalDevicePairing
    {
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1 || $actor->auth_source !== 'local'
            || ! is_string($actor->getAuthPassword()) || $actor->getAuthPassword() === '') {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Nur die lokale Verwaltung darf Terminal-Geräte anlegen.');
        }
        if (($command['action'] ?? null) !== 'device'
            || array_diff(array_keys($command), self::COMMAND_FIELDS) !== []
            || ! is_string($command['name'] ?? null) || trim($command['name']) === '' || strlen(trim($command['name'])) > 100
            || ! is_string($command['site_id'] ?? null)
            || preg_match('/\A[1-9][0-9]*\z/', $command['site_id']) !== 1
            || filter_var($command['site_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new InvalidArgumentException('Name und Standort prüfen.');
        }

        $version = hash('sha256', $actor->getAuthPassword());
        $authority = $this->lifecycle?->capture($actor);
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('An admitted operation-scoped terminal device enrollment writer is required.');
        }
        try {
            // No result leaves the writer before confirmed COMMIT and idle state.
            return $writer->createTerminalDevicePairing((int) $actor->getKey(), $version, $command['name'], $command['site_id'], $authority);
        } catch (\AttendanceError $error) {
            if ($error->status === 401) {
                throw new AuthenticationException('Unauthenticated.', ['web']);
            }
            throw $error;
        }
    }
}
