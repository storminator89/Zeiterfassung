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

/** Current local Admin only; the fixed site writer rechecks under its lock. */
final class LegacyLocalWebTerminalSites implements LocalWebTerminalSites
{
    /** @param Closure(): AttendanceWriter $writer Trusted operation-scope resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer) {}

    public function create(array $command): void
    {
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1
            || $actor->auth_source !== 'local'
            || ! in_array($actor->role, ['user', 'supervisor', 'admin'], true)) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Nur die Verwaltung darf Terminal-Standorte anlegen.');
        }
        if (($command['action'] ?? null) !== 'site'
            || array_diff(array_keys($command), self::COMMAND_FIELDS) !== []) {
            throw new InvalidArgumentException('Diese Terminalaktion ist in der Laravel-Ansicht nicht verbunden.');
        }
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('An admitted operation-scoped terminal site writer is required.');
        }

        $writer->createTerminalSite((int) $actor->getKey(), $command['name'] ?? null);
    }
}
