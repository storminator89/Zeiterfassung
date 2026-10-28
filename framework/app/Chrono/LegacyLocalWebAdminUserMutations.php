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

/** Current local Admin and hash-bound operation only; no intake-policy dependency. */
final class LegacyLocalWebAdminUserMutations implements LocalWebAdminUserMutations
{
    /** @param Closure(): AttendanceWriter $writer Existing trusted operation-scoped resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer,
        private readonly ?AccountLifecycleWebIdentity $lifecycle = null) {}

    public function apply(#[\SensitiveParameter] array $command): AdminUserMutationResult
    {
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1 || $actor->auth_source !== 'local'
            || ! is_string($actor->getAuthPassword()) || $actor->getAuthPassword() === '') {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Nur die lokale Verwaltung darf Benutzer ändern.');
        }

        $actions = array_values(array_filter(array_keys(self::COMMAND_FIELDS),
            static fn (string $action): bool => isset($command[$action])));
        if (count($actions) !== 1
            || array_diff(array_keys($command), self::COMMAND_FIELDS[$actions[0]]) !== []) {
            throw new InvalidArgumentException('Ungültige Benutzeraktion.');
        }
        // Normal HTML forms supply raw strings; never pass arrays/objects/bools
        // through password_hash or PDO, trim passwords, or accept an actor field.
        foreach ($command as $value) {
            if (! is_string($value)) {
                throw new InvalidArgumentException('Ungültige Benutzereingabe.');
            }
        }
        foreach (self::COMMAND_FIELDS[$actions[0]] as $field) {
            if (! array_key_exists($field, $command)) {
                throw new InvalidArgumentException('Ungültige Benutzereingabe.');
            }
        }

        // Capture the refreshed guard hash, never a request/session-selected
        // actor or version. The same value is checked inside the writer lock.
        $version = hash('sha256', $actor->getAuthPassword());
        $lifecycleAuthority = $this->lifecycle?->capture($actor);
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('An admitted operation-scoped user-mutation writer is required.');
        }
        try {
            return $actions[0] === 'delete_user'
                ? $writer->deleteManagedUser((int) $actor->getKey(), $version, $command['user_id'], $lifecycleAuthority)
                : $writer->editManagedUser((int) $actor->getKey(), $version, $command['user_id'],
                    $command['username'], $command['email'], $command['role'], $command['password'],
                    $command['department'], $command['supervisor'], $command['regelarbeitszeit'], $command['ueberstunden'], $lifecycleAuthority);
        } catch (\AttendanceError $error) {
            if ($error->status === 401) {
                throw new AuthenticationException('Unauthenticated.', ['web']);
            }
            throw $error;
        }
    }
}
