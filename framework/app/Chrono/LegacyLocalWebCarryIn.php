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

/** Current own business Admin only, before writer resolution; fixed writer checks again. */
final class LegacyLocalWebCarryIn implements LocalWebCarryIn
{
    /** @param Closure(): AttendanceWriter $writer Trusted operation-scope resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer,
        private readonly ?AccountLifecycleWebIdentity $lifecycle = null) {}

    public function replace(mixed $raw): string
    {
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
            throw new AuthorizationException('Nur die Verwaltung darf den eigenen Überstunden-Übertrag ändern.');
        }
        require_once dirname(__DIR__, 3).'/lib/settings_carry_in.php';
        \settings_carry_in_value($raw);

        $context = $profile ? $provider->context($actor) : null;
        $authority = $this->lifecycle?->capture($actor);
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('A trusted admitted operation-scoped attendance writer is required.');
        }

        try {
            return $writer->ownCarryIn((int) $actor->getKey(), $raw, $authority, $context);
        } catch (\AttendanceError $error) {
            if ($authority !== null && $error->status === 401) { throw new AuthenticationException('Unauthenticated.', ['web']); }
            throw $error;
        }
    }
}
