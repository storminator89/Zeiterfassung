<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\Auth\AccountLifecycleWebIdentity;
use App\Auth\ExistingAccountUserProvider;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use LogicException;

/** Auth/source boundary only. All booking/transaction/replay rules stay in #75. */
final class LegacyLocalWebAttendance implements LocalWebAttendance
{
    /** @param Closure(): AttendanceWriter $writer Trusted operation-scope resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer,
        private readonly ?AccountLifecycleWebIdentity $lifecycle = null) {}

    public function transition(string $eventId, string $action, bool $confirm = false, ?int $expectedRecord = null, ?array $startMetadata = null): array
    {
        // This guard must already have passed #76 RefreshLocalAccount,
        // auth:web and auth.session. No actor/source/role is accepted as input.
        $guard = $this->auth->guard('web');
        $actor = $guard->user();
        $provider = $guard instanceof SessionGuard ? $guard->getProvider() : null;
        $profile = $provider instanceof ExistingAccountUserProvider;
        $context = null;
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1
            || (! $profile && $actor->auth_source !== 'local')
            || ! in_array($actor->role, ['user', 'supervisor', 'admin'], true)) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($profile) {
            $context = $provider->context($actor);
        }

        $authority = $this->lifecycle?->capture($actor);

        // Resolve only after authentication. The application never constructs a
        // PDO, opens a path, binds a default connection or adopts a file here.
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('A trusted admitted operation-scoped attendance writer is required.');
        }
        $identifier = (int) $actor->getKey();

        if ($authority !== null) {
            return $writer->transitionWeb($authority, $eventId, $action, $confirm, $expectedRecord, $startMetadata);
        }

        // EXACT existing web namespace. Do not rotate it across migration,
        // recompute payloads/responses, catch errors or replay automatically.
        if ($context !== null) {
            return $writer->transition($identifier, 'web:'.$identifier, $eventId, $action, $confirm, $expectedRecord, true, $context[0], $context[1], $startMetadata);
        }
        return $writer->transition($identifier, 'web:'.$identifier, $eventId, $action, $confirm, $expectedRecord, true, null, null, $startMetadata);
    }
}
