<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\Auth\ExistingAccountUserProvider;
use Illuminate\Auth\SessionGuard;
use App\Auth\AccountLifecycleWebIdentity;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use InvalidArgumentException;
use LogicException;

/** Lazy fixed own-request operation; no credentials or requested actor are accepted. */
final class LegacyLocalWebCorrectionWithdrawals implements LocalWebCorrectionWithdrawals
{
    /** @param Closure(): AttendanceWriter $writer Trusted operation-scope resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer,
        private readonly ?AccountLifecycleWebIdentity $lifecycle = null) {}

    public function withdraw(array $command): void
    {
        $guard = $this->auth->guard('web');
        $actor = $guard->user();
        $provider = $guard instanceof SessionGuard ? $guard->getProvider() : null;
        $profile = $provider instanceof ExistingAccountUserProvider;
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1 || (! $profile && $actor->auth_source !== 'local')
            || ! in_array($actor->role, ['user', 'supervisor', 'admin'], true)
            || ! is_string($actor->getAuthPassword()) || $actor->getAuthPassword() === '') {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if (($command['action'] ?? null) !== 'withdraw'
            || array_diff(array_keys($command), self::COMMAND_FIELDS) !== []) {
            throw new InvalidArgumentException('Ungültige Rücknahmeaktion.');
        }
        $context = $profile ? $provider->context($actor) : null;
        $user = (int) $actor->getKey();
        $role = $actor->role;
        $version = hash('sha256', $actor->getAuthPassword());
        $authority = $this->lifecycle?->capture($actor);
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('An admitted operation-scoped correction withdrawal writer is required.');
        }
        try {
            if ($authority !== null) {
                $writer->withdrawCorrectionWeb($authority, $version, $command['id'] ?? null, $command['withdraw_reason'] ?? null);
            } else {
                $writer->withdrawCorrection($user, $role, $version, $command['id'] ?? null, $command['withdraw_reason'] ?? null, $context[0] ?? 'local');
            }
        } catch (\AttendanceError $error) {
            if ($error->status === 401) { throw new AuthenticationException('Unauthenticated.', ['web']); }
            throw $error;
        }
    }
}
