<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use LogicException;

/** Local guard boundary only; the shared core owns absence rules and SQL. */
final class LegacyLocalWebAbsences implements LocalWebAbsences
{
    /** @param Closure(): AttendanceWriter $writer Trusted operation-scope resolver. */
    public function __construct(private readonly Factory $auth, private readonly Closure $writer) {}

    public function create(string $start, string $end, string $kind): array
    {
        // Mandatory before later HTTP use: #76 RefreshLocalAccount, auth:web,
        // auth.session and the existing web/session/CSRF middleware.
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1
            || $actor->auth_source !== 'local'
            || ! in_array($actor->role, ['user', 'supervisor', 'admin'], true)) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }

        // Resolve no writer/path/configuration before authenticating the owner.
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('A trusted admitted operation-scoped attendance writer is required.');
        }

        return $writer->absence((int) $actor->getKey(), $start, $end, $kind);
    }
}
