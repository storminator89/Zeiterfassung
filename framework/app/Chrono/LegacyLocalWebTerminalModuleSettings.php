<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use InvalidArgumentException;
use LogicException;

/** Local Admin and hash-bound fixed operation; no host-setting mutation, pairing-code or signing-key dependency. */
final class LegacyLocalWebTerminalModuleSettings implements LocalWebTerminalModuleSettings
{
    /** @param Closure(): AttendanceWriter $writer Existing trusted operation-scoped resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer) {}

    public function save(array $command): void
    {
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1 || $actor->auth_source !== 'local'
            || ! is_string($actor->getAuthPassword()) || $actor->getAuthPassword() === '') {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Nur die lokale Verwaltung darf den optionalen Terminal-Betrieb ändern.');
        }
        if (($command['action'] ?? null) !== 'toggle'
            || array_diff(array_keys($command), self::COMMAND_FIELDS) !== []
            || (array_key_exists('enabled', $command) && ! is_string($command['enabled']))) {
            throw new InvalidArgumentException('Ungültige Terminalaktion.');
        }

        // Only the refreshed web guard selects the actor/version. No request
        // actor/version, generation, pairing code, credential or key is accepted.
        $version = hash('sha256', $actor->getAuthPassword());
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('An admitted operation-scoped terminal module settings writer is required.');
        }
        try {
            $writer->saveTerminalModuleSettings((int) $actor->getKey(), $version, isset($command['enabled']));
        } catch (\AttendanceError $error) {
            if ($error->status === 401) {
                throw new AuthenticationException('Unauthenticated.', ['web']);
            }
            throw $error;
        }
    }
}
