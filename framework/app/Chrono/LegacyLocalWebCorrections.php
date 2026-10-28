<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\Auth\AccountLifecycleWebIdentity;
use App\Auth\ExistingAccountUserProvider;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Factory;
use LogicException;

/** Guard boundary only; the shared core owns correction permissions and SQL. */
final class LegacyLocalWebCorrections implements LocalWebCorrections
{
    /** @param Closure(): AttendanceWriter $writer Trusted operation-scope resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer,
        private readonly ?AccountLifecycleWebIdentity $lifecycle = null) {}

    public function apply(array $command): void
    {
        // Mandatory before a later HTTP use: #76 RefreshLocalAccount,
        // auth:web and auth.session, plus the existing web/CSRF middleware.
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

        if ($context !== null && $context[0] === 'ldap' && ($command['action'] ?? null) !== 'request') {
            throw new AuthorizationException('Diese LDAP-Slice erlaubt nur eigene Korrekturanträge.');
        }

        $authority = $this->lifecycle?->capture($actor);

        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('A trusted admitted operation-scoped attendance writer is required.');
        }

        if ($authority !== null) {
            $writer->correctionWeb($authority, $command);

            return;
        }

        // Only the current trusted guard supplies actor/role. Same assurance as
        // the refreshed native session; no new lock-time account/role rule.
        // Command user_id/role/source/audit fields are ignored by the core.
        if ($context !== null) {
            $writer->correction((int) $actor->getKey(), $actor->role, $command, $context[0], $context[1]);

            return;
        }
        $writer->correction((int) $actor->getKey(), $actor->role, $command);
    }
}
