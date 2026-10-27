<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use LogicException;

/** Guard boundary only; the shared core owns correction permissions and SQL. */
final class LegacyLocalWebCorrections implements LocalWebCorrections
{
    /** @param Closure(): AttendanceWriter $writer Trusted operation-scope resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer) {}

    public function apply(array $command): void
    {
        // Mandatory before a later HTTP use: #76 RefreshLocalAccount,
        // auth:web and auth.session, plus the existing web/CSRF middleware.
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1
            || $actor->auth_source !== 'local'
            || ! in_array($actor->role, ['user', 'supervisor', 'admin'], true)) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }

        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('A trusted admitted operation-scoped attendance writer is required.');
        }

        // Only the current trusted guard supplies actor/role. Same assurance as
        // the refreshed native session; no new lock-time account/role rule.
        // Command user_id/role/source/audit fields are ignored by the core.
        $writer->correction((int) $actor->getKey(), $actor->role, $command);
    }
}
