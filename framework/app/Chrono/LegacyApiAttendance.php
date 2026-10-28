<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\Api\AccountLifecycleApiIdentity;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\RequestGuard;
use Illuminate\Contracts\Auth\Factory;
use LogicException;
use InvalidArgumentException;

/** Verified bearer identity/source boundary; all booking/replay rules stay in #75. */
final class LegacyApiAttendance implements ApiAttendance
{
    /** @param Closure(): AttendanceWriter $writer Trusted fresh operation-scope resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer,
        private readonly ?AccountLifecycleApiIdentity $lifecycle = null) {}

    public function transition(string $eventId, string $action, bool $confirm = false, ?int $expectedRecord = null, ?array $startMetadata = null): array
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
        // Older profiles keep their JWT account/role behavior. Explicit V5
        // captures only the verified bearer model, never client actor/generation.
        try {
            $authority = $this->lifecycle?->capture($actor);
        } catch (InvalidArgumentException) {
            require_once dirname(__DIR__, 3).'/lib/attendance.php';
            throw new \AttendanceError('Account deleted or credentials changed.', 401);
        }
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('A trusted admitted operation-scoped attendance writer is required.');
        }
        $identifier = (int) $actor->getKey();

        if ($authority !== null) {
            // The fixed V5 operation rechecks the actor UNDER its sole lock,
            // before both receipt replay and domain DML. Old transition stays closed.
            return $writer->transitionApi($authority, $eventId, $action, $confirm, $expectedRecord, $startMetadata);
        }

        // Exact shared namespace. Direct /attendance forwards its optional string binding;
        // /setendzeit keeps its existing expected projection, never row CRUD.
        return $writer->transition($identifier, 'api:'.$identifier, $eventId, $action, $confirm, $expectedRecord, false, null, null, $startMetadata);
    }
}
