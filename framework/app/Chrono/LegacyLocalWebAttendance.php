<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use LogicException;

/** Auth/source boundary only. All booking/transaction/replay rules stay in #75. */
final class LegacyLocalWebAttendance implements LocalWebAttendance
{
    /** @param Closure(): AttendanceWriter $writer Trusted operation-scope resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer) {}

    public function transition(string $eventId, string $action, bool $confirm = false, ?int $expectedRecord = null): array
    {
        // This guard must already have passed #76 RefreshLocalAccount,
        // auth:web and auth.session. No actor/source/role is accepted as input.
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1
            || $actor->auth_source !== 'local'
            || ! in_array($actor->role, ['user', 'supervisor', 'admin'], true)) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }

        // Resolve only after authentication. The application never constructs a
        // PDO, opens a path, binds a default connection or adopts a file here.
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('A trusted admitted operation-scoped attendance writer is required.');
        }
        $identifier = (int) $actor->getKey();

        // EXACT existing web namespace. Do not rotate it across migration,
        // recompute payloads/responses, catch errors or replay automatically.
        return $writer->transition($identifier, 'web:'.$identifier, $eventId, $action, $confirm, $expectedRecord);
    }
}
