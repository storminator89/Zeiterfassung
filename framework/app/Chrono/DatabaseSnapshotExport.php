<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\LegacyDatabase\RuntimeDatabase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;

/** Existing local guard only; no native login copying, LDAP or source/path arguments. */
final class DatabaseSnapshotExport
{
    public function __construct(private readonly Factory $auth, private readonly RuntimeDatabase $database) {}

    public function authorizeHead(): void
    {
        $this->actor();
    }

    public function create(): DatabaseSnapshotArtifact
    {
        return $this->database->databaseSnapshot($this->actor());
    }

    private function actor(): LegacyUser
    {
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1 || $actor->auth_source !== 'local'
            || ! in_array($actor->role, ['user', 'supervisor', 'admin'], true)) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Only administrators may export a database snapshot.');
        }

        return $actor;
    }
}
