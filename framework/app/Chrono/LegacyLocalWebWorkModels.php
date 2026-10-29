<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\Auth\ExistingAccountUserProvider;
use Illuminate\Auth\SessionGuard;
use App\Auth\AccountLifecycleWebIdentity;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use LogicException;

/** Local guard/admin boundary only; the native core owns model validation/SQL. */
final class LegacyLocalWebWorkModels implements LocalWebWorkModels
{
    /** @param Closure(): AttendanceWriter $writer Trusted operation-scope resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer,
        private readonly ?AccountLifecycleWebIdentity $lifecycle = null) {}

    public function apply(array $command): void
    {
        // Mandatory before later HTTP use: #76 RefreshLocalAccount, auth:web,
        // auth.session and the existing web/session/CSRF middleware.
        $guard = $this->auth->guard('web');
        $actor = $guard->user();
        $provider = $guard instanceof SessionGuard ? $guard->getProvider() : null;
        $profile = $provider instanceof ExistingAccountUserProvider;
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1
            || (! $profile && $actor->auth_source !== 'local')
            || ! in_array($actor->role, ['user', 'supervisor', 'admin'], true)) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Nur die Verwaltung darf Arbeitszeitmodelle festlegen.');
        }

        // No writer/path/configuration resolution before authentication/admin.
        $context = $profile ? $provider->context($actor) : null;
        $authority = $this->lifecycle?->capture($actor);
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('A trusted admitted operation-scoped attendance writer is required.');
        }

        // user_id remains the existing target field, never the audit actor.
        try {
            $writer->workModel((int) $actor->getKey(), $actor->role, $command, $authority, $context);
        } catch (\AttendanceError $error) {
            if ($authority !== null && $error->status === 401) { throw new AuthenticationException('Unauthenticated.', ['web']); }
            throw $error;
        }
    }
}
