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

/** Local guard boundary only; the shared core owns absence rules and SQL. */
final class LegacyLocalWebAbsences implements LocalWebAbsences
{
    /** @param Closure(): AttendanceWriter $writer Trusted operation-scope resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer,
        private readonly ?AccountLifecycleWebIdentity $lifecycle = null) {}

    public function create(string $start, string $end, string $kind): array
    {
        // Mandatory before later HTTP use: #76 RefreshLocalAccount, auth:web,
        // auth.session and the existing web/session/CSRF middleware.
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

        // Resolve no writer/path/configuration before authenticating the owner.
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('A trusted admitted operation-scoped attendance writer is required.');
        }

        if ($authority !== null) {
            return $writer->absenceWeb($authority, $start, $end, $kind);
        }

        if ($context !== null) {
            return $writer->absence((int) $actor->getKey(), $start, $end, $kind, $context[0], $context[1]);
        }
        return $writer->absence((int) $actor->getKey(), $start, $end, $kind);
    }
}
