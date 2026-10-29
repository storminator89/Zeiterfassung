<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\Auth\AccountLifecycleWebIdentity;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use InvalidArgumentException;
use LogicException;

/** Local Admin and hash-bound fixed operation; no intake, device or key dependency. */
final class LegacyLocalWebTerminalCredentialRevocations implements LocalWebTerminalCredentialRevocations
{
    /** @param Closure(): AttendanceWriter $writer Existing trusted operation-scoped resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer,
        private readonly ?AccountLifecycleWebIdentity $lifecycle = null) {}

    public function revoke(array $command): void
    {
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1 || $actor->auth_source !== 'local'
            || ! is_string($actor->getAuthPassword()) || $actor->getAuthPassword() === '') {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Nur die lokale Verwaltung darf Terminal-Zugänge sperren.');
        }
        if (($command['action'] ?? null) !== 'revoke_credential'
            || array_diff(array_keys($command), self::COMMAND_FIELDS) !== []
            || ! is_string($command['user_id'] ?? null)
            || preg_match('/\A[1-9][0-9]*\z/', $command['user_id']) !== 1
            || filter_var($command['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new InvalidArgumentException('Ungültige Terminalaktion.');
        }

        // Capture only the refreshed guard identity. No request actor/version,
        // stored hash, PIN/card input or session mutation is supported.
        $version = hash('sha256', $actor->getAuthPassword());
        $authority = $this->lifecycle?->capture($actor);
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('An admitted operation-scoped terminal revocation writer is required.');
        }
        try {
            $writer->revokeTerminalCredential((int) $actor->getKey(), $version, $command['user_id'], $authority);
        } catch (\AttendanceError $error) {
            if ($error->status === 401) {
                throw new AuthenticationException('Unauthenticated.', ['web']);
            }
            throw $error;
        }
    }
}
