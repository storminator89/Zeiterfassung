<?php

namespace App\LegacyDatabase;

use App\Accounts\AccountLifecycleAuthority;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use RuntimeException;
use SQLite3;
use Throwable;

/**
 * @internal Fixed V5 actor check on the caller's already admitted native read
 * snapshot. No opener, path, transaction owner, artifact or export callback.
 * SQLite3 exposes no transaction-state query: the fixed Runtime caller owns
 * BEGIN DEFERRED and the prior admission reads on this exact handle.
 */
final class AccountLifecycleSnapshotAuthority
{
    public static function authorize(SQLite3 $source, #[\SensitiveParameter] AccountLifecycleAuthority $authority): void
    {
        if ($authority->source !== 'local') { throw new AuthenticationException; }
        if ($authority->role !== 'admin') { throw new AuthorizationException('Only administrators may export a database snapshot.'); }
        try {
            if ($source->querySingle('PRAGMA query_only') !== 1) { throw new RuntimeException; }
            $statement = $source->prepare('SELECT id,username,role,auth_source,password,account_status,auth_generation FROM users WHERE id=:actor');
            try {
                if (! $statement->bindValue(':actor', $authority->id, SQLITE3_INTEGER)) { throw new RuntimeException; }
                $result = $statement->execute();
                try {
                    $actor = $result->fetchArray(SQLITE3_ASSOC);
                    if ($result->fetchArray(SQLITE3_ASSOC) !== false) { throw new RuntimeException; }
                } finally {
                    $result->finalize();
                }
            } finally {
                $statement->close();
            }
            if ($actor === false || ! $authority->matches($actor)
                || ! AccountLifecycleAuthority::locallyLoginEligibleAdmin($actor)) {
                throw new AuthenticationException;
            }
        } catch (AuthenticationException|AuthorizationException $error) {
            throw $error;
        } catch (Throwable) {
            // Never retain source SQL, a private path, account/hash or previous error.
            throw new RuntimeException('Snapshot account authority could not be confirmed.');
        }
    }
}
