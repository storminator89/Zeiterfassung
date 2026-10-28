<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\Auth\AccountLifecycleWebIdentity;
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
        $this->principal();
    }

    public function create(): DatabaseSnapshotArtifact
    {
        [$actor, $authority] = $this->principal();
        return $this->database->databaseSnapshot($actor, $authority);
    }

    private function principal(): array
    {
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1 || $actor->auth_source !== 'local'
            || ! in_array($actor->role, ['user', 'supervisor', 'admin'], true)) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Only administrators may export a database snapshot.');
        }

        // Same immutable Runtime instance as the export, never optional columns
        // or a forgotten nullable facade dependency selecting an older profile.
        $authority = AccountLifecycleWebIdentity::fromRuntime($this->database)->capture($actor);
        return [$actor, $authority];
    }
}
