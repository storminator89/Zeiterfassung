<?php

/** Delete only an existing, unassigned department through the native admin handler. */
function department_delete_if_unused(PDO $db, mixed $departmentId): void
{
    $targetId = (is_string($departmentId) || is_int($departmentId))
        ? filter_var($departmentId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    if ($targetId === false) {
        throw new InvalidArgumentException('Ungültige Abteilungs-ID.');
    }

    // Acquire the SQLite writer lock before reading the current relationships.
    // A failed BEGIN owns no transaction and must not roll back a caller's work.
    $db->exec('BEGIN IMMEDIATE');
    try {
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
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->exec('ROLLBACK');
        }
        throw $e;
    }
}
