<?php

require_once __DIR__.'/admin_users.php';

/** Delete only an existing, unassigned department through the native admin handler. */
function department_delete_if_unused(PDO $db, mixed $departmentId): void
{
    $targetId = department_delete_id($departmentId);

    // Acquire the SQLite writer lock before reading the current relationships.
    // A failed BEGIN owns no transaction and must not roll back a caller's work.
    $db->exec('BEGIN IMMEDIATE');
    try {
        department_delete_locked($db, $targetId);
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->exec('ROLLBACK');
        }
        throw $e;
    }
}

/** Canonical department target ID; no transaction, coercion or bootstrap. */
function department_delete_id(mixed $departmentId): int
{
    return admin_assignment_id($departmentId, 'Ungültige Abteilungs-ID.');
}

/** Caller owns the native IMMEDIATE lock; never BEGIN/COMMIT/ROLLBACK here. */
function department_delete_locked(PDO $db, int $targetId): void
{
    $stmt = $db->prepare('SELECT id FROM departments WHERE id = ?');
    $stmt->execute([$targetId]);
    $exists = $stmt->fetchColumn();
    $stmt->closeCursor();
    if ($exists === false) {
        throw new InvalidArgumentException('Die Abteilung existiert nicht mehr.');
    }

    $stmt = $db->prepare('SELECT id FROM users WHERE department_id = ? LIMIT 1');
    $stmt->execute([$targetId]);
    $assignedUser = $stmt->fetchColumn();
    $stmt->closeCursor();
    if ($assignedUser !== false) {
        throw new DomainException('Die Abteilung ist noch Benutzern zugeordnet und kann nicht gelöscht werden.');
    }

    $stmt = $db->prepare('DELETE FROM departments WHERE id = ?');
    $stmt->execute([$targetId]);
    $deleted = $stmt->rowCount();
    $stmt->closeCursor();
    if ($deleted !== 1) {
        throw new DomainException('Die Abteilung wurde nicht gelöscht. Bitte erneut prüfen.');
    }
}

/** Preserve native empty() exactly: notably "0" is empty and whitespace is not. */
function department_create_name_missing(mixed $department_name): bool
{
    return empty($department_name);
}

/** Native one-statement insert; caller owns any framework transaction. */
function department_create_core(PDO $conn, mixed $department_name): void
{
    $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
    $stmt->execute([$department_name]);
}

/** Preserve rename semantics, but reject noncanonical target IDs before SQL. */
function department_rename_core(PDO $conn, mixed $department_id, mixed $department_name): void
{
    $targetId = department_delete_id($department_id);
    $stmt = $conn->prepare("UPDATE departments SET name = ? WHERE id = ?");
    $stmt->execute([$department_name, $targetId]);
}
