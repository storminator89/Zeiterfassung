<?php

// Definitions only: no bootstrap, credentials, directory calls or schema changes.
/** Routing eligibility only; the existing local password check still authenticates. */
function native_local_recovery_admin(PDO $conn, string $username): bool
{
    $select = $conn->prepare('SELECT auth_source,role FROM users WHERE username = ?');
    $select->execute([$username]);
    $user = $select->fetch(PDO::FETCH_ASSOC);
    $select->closeCursor();
    return $user !== false && $user['auth_source'] === 'local' && $user['role'] === 'admin';
}

function native_ldap_placeholder(): string
{
    return password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
}

/** Called only AFTER the existing TLS-protected directory bind/search succeeds. */
function native_ldap_login_account(PDO $conn, string $username, string $email): object
{
    if ($conn->inTransaction()) {
        throw new LogicException('LDAP login requires its own transaction.');
    }
    // Serialize first creation/conversion, so a simultaneous second login cannot
    // replace the first login's placeholder. Never re-use an earlier row snapshot.
    $conn->exec('BEGIN IMMEDIATE');
    try {
        $select = $conn->prepare('SELECT * FROM users WHERE username = ?');
        $select->execute([$username]);
        $user = $select->fetch(PDO::FETCH_OBJ);
        $select->closeCursor();

        if (!$user) {
            $conn->prepare("INSERT INTO users (username, password, email, role, auth_source) VALUES (?, ?, ?, 'user', 'ldap')")
                ->execute([$username, native_ldap_placeholder(), $email]);
        } elseif ($user->auth_source === 'local') {
            if ($user->role === 'admin') {
                throw new DomainException('LDAP conversion refused for a local recovery identity.');
            }
            // A real authentication-source change MUST revoke old local sessions
            // and JWTs and discard the formerly reusable local password.
            $conn->prepare("UPDATE users SET auth_source='ldap', password=? WHERE id=?")
                ->execute([native_ldap_placeholder(), $user->id]);
        } elseif ($user->auth_source !== 'ldap') {
            throw new DomainException('Unsupported account authentication source.');
        }
        // Existing LDAP accounts retain their revocation fingerprint. Explicit
        // password/placeholder changes and deletion still invalidate old clients.
        $select->execute([$username]);
        $current = $select->fetch(PDO::FETCH_OBJ);
        $select->closeCursor();
        if (!$current || $current->auth_source !== 'ldap' || $current->password === '') {
            throw new RuntimeException('LDAP account unavailable.');
        }
        $conn->commit();
        return $current;
    } catch (Throwable $error) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        throw $error;
    }
}
