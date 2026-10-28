<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\RequestGuard;
use Illuminate\Contracts\Auth\Factory;
use LogicException;

/** Verified bearer identity/source boundary; all booking/replay rules stay in #75. */
final class LegacyApiAttendance implements ApiAttendance
{
    /** @param Closure(): AttendanceWriter $writer Trusted fresh operation-scope resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer) {}

    public function transition(string $eventId, string $action, bool $confirm = false, ?int $expectedRecord = null): array
    {
        // Mandatory RequireLegacyApiToken first: reset request guard and verify
        // JWT/current account/password fingerprint. Never inherit a web identity.
        $guard = $this->auth->guard('chrono_api');
        if (! $guard instanceof RequestGuard) {
            throw new LogicException('The verified legacy API request guard is required.');
        }
        $actor = $guard->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1) {
            throw new AuthenticationException('Unauthenticated.', ['chrono_api']);
        }
        // Existing valid JWTs may identify LDAP accounts or legacy role values.
        // No new local-source/role allowlist, query, or lock-time auth mechanism.
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('A trusted admitted operation-scoped attendance writer is required.');
        }
        $identifier = (int) $actor->getKey();

        // Exact shared namespace. Direct /attendance forwards its optional string binding;
        // /setendzeit keeps its existing expected projection, never row CRUD.
        return $writer->transition($identifier, 'api:'.$identifier, $eventId, $action, $confirm, $expectedRecord);
    }
}
