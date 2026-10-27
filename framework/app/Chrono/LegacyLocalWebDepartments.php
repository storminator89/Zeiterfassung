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

/** Current local Admin first; the fixed writer rechecks under its own lock. */
final class LegacyLocalWebDepartments implements LocalWebDepartments
{
    /** @param Closure(): AttendanceWriter $writer Trusted operation-scope resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer) {}

    public function apply(array $command): bool
    {
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1
            || $actor->auth_source !== 'local'
            || ! in_array($actor->role, ['user', 'supervisor', 'admin'], true)) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Nur die Verwaltung darf Abteilungen ändern.');
        }

        $actions = [];
        foreach (self::COMMAND_FIELDS as $flag => $fields) {
            if (isset($command[$flag])) {
                $actions[] = $flag;
            }
        }
        if (count($actions) !== 1
            || array_diff(array_keys($command), self::COMMAND_FIELDS[$actions[0]]) !== []) {
            throw new InvalidArgumentException('Diese Verwaltungsaktion ist in der Laravel-Ansicht noch nicht verbunden.');
        }

        // Never resolve a writer for a nonadmin or an unsupported/mixed command.
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('A trusted admitted operation-scoped attendance writer is required.');
        }
        $operation = match ($actions[0]) {
            'add_department' => 'create',
            'edit_department' => 'rename',
            'delete_department' => 'delete',
        };

        return $writer->department(
            (int) $actor->getKey(), $operation,
            $command['department_id'] ?? null, $command['department_name'] ?? null,
        );
    }
}
