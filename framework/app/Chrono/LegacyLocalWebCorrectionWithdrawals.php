<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use InvalidArgumentException;
use LogicException;

/** Lazy fixed operation. LDAP remains request-only; no credentials are accepted. */
final class LegacyLocalWebCorrectionWithdrawals implements LocalWebCorrectionWithdrawals
{
    /** @param Closure(): AttendanceWriter $writer Trusted operation-scope resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer) {}

    public function withdraw(array $command): void
    {
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1 || $actor->auth_source !== 'local'
            || ! in_array($actor->role, ['user', 'supervisor', 'admin'], true)
            || ! is_string($actor->getAuthPassword()) || $actor->getAuthPassword() === '') {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if (($command['action'] ?? null) !== 'withdraw'
            || array_diff(array_keys($command), self::COMMAND_FIELDS) !== []) {
            throw new InvalidArgumentException('Ungültige Rücknahmeaktion.');
        }
        $user = (int) $actor->getKey();
        $role = $actor->role;
        $version = hash('sha256', $actor->getAuthPassword());
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('An admitted operation-scoped correction withdrawal writer is required.');
        }
        try {
            $writer->withdrawCorrection($user, $role, $version, $command['id'] ?? null, $command['withdraw_reason'] ?? null);
        } catch (\AttendanceError $error) {
            if ($error->status === 401) { throw new AuthenticationException('Unauthenticated.', ['web']); }
            throw $error;
        }
    }
}
