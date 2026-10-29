<?php
// Definitions only: display eligibility, never password authority or profile admission.
require_once __DIR__.'/native_own_password.php';

/** Mirror the existing operation's ledger refusal without asking for credentials. */
function native_own_password_display(PDO $db, mixed $user): string
{
    if (!is_array($user) || !native_own_password_available($user)) {
        return 'account_unavailable';
    }
    try {
        // Never borrow, finish or query inside another operation's transaction.
        if ($db->inTransaction()) { return 'profile_unavailable'; }
        // Four rows suffice to distinguish both existing accepted ledgers from
        // every longer/unknown ledger. No migration, full profile scan or write.
        $statement=$db->query('SELECT version FROM app_migrations ORDER BY version LIMIT 4');
        try { $versions=$statement->fetchAll(PDO::FETCH_COLUMN); }
        finally { $statement->closeCursor(); }
        return $versions===[1,2] || $versions===[1,2,3] ? 'available' : 'profile_unavailable';
    } catch (Throwable) {
        // Only the form is unavailable; no logout, success, retry or raw error.
        return 'profile_unavailable';
    }
}
