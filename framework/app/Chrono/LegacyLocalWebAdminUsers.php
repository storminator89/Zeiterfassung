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

/** Current local Admin first; the one fixed create rechecks under its lock. */
final class LegacyLocalWebAdminUsers implements LocalWebAdminUsers
{
    /** @param Closure(): AttendanceWriter $writer Trusted operation-scope resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer) {}

    public function create(array $command): bool
    {
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1
            || $actor->auth_source !== 'local'
            || ! in_array($actor->role, ['user', 'supervisor', 'admin'], true)) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Nur die Verwaltung darf Benutzer erstellen.');
        }
        if (! isset($command['add_user'])
            || array_diff(array_keys($command), self::COMMAND_FIELDS['add_user']) !== []) {
            throw new InvalidArgumentException('Diese Verwaltungsaktion ist in der Laravel-Ansicht noch nicht verbunden.');
        }

        // Never resolve a writer for nonadmins or mixed/unsupported commands.
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('A trusted admitted operation-scoped attendance writer is required.');
        }

        return $writer->createLocalUser(
            (int) $actor->getKey(), $command['username'] ?? null, $command['password'] ?? null,
            $command['email'] ?? null, $command['role'] ?? null, $command['department'] ?? null,
            $command['supervisor'] ?? null, $command['regelarbeitszeit'] ?? null, $command['ueberstunden'] ?? null,
        );
    }
}
