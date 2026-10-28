<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\Accounts\IntakePolicyAccess;
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
    public function __construct(private readonly Factory $auth, private readonly Closure $writer,
        private readonly ?IntakePolicyAccess $intake = null) {}

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

        // V4 reads and closes its fresh current policy/Admin snapshot before writer resolution.
        $context = $this->intake?->manualContext();
        // Never resolve a writer for nonadmins or mixed/unsupported commands.
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('A trusted admitted operation-scoped attendance writer is required.');
        }

        try {
            return $writer->createLocalUser(
                $context['user'] ?? (int) $actor->getKey(), $command['username'] ?? null, $command['password'] ?? null,
                $command['email'] ?? null, $command['role'] ?? null, $command['department'] ?? null,
                $command['supervisor'] ?? null, $command['regelarbeitszeit'] ?? null, $command['ueberstunden'] ?? null,
                expectedPolicyRevision: $context['revision'] ?? null, expectedWebVersion: $context['version'] ?? null,
            );
        } catch (\AttendanceError $error) {
            if ($context !== null && $error->status === 401) {
                throw new AuthenticationException('Unauthenticated.', ['web']);
            }
            throw $error;
        }
    }
}
