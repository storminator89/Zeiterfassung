<?php

/**
 * Internal helper for the existing authenticated admin edit/delete handlers.
 * SQLite's writer lock keeps the current role/count and mutation atomic.
 * A null next role denotes deletion. There is no separate active-account flag.
 */
function admin_mutate_existing_user(PDO $db, mixed $userId, ?string $nextRole, Closure $operation): void
{
    $targetId = filter_var($userId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($targetId === false) {
        throw new InvalidArgumentException('Ungültiger Benutzer.');
    }

    // A failed BEGIN owns no transaction; only roll back after acquiring it.
    $db->exec('BEGIN IMMEDIATE');
    try {
        $stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$targetId]);
        $currentRole = $stmt->fetchColumn();
        $stmt->closeCursor();
        if ($currentRole === false) {
            throw new InvalidArgumentException('Ungültiger Benutzer.');
        }
        if ($currentRole === 'admin' && $nextRole !== 'admin') {
            $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            $adminCount = (int)$stmt->fetchColumn();
            $stmt->closeCursor();
            if ($adminCount <= 1) {
                throw new InvalidArgumentException('Der letzte Administrator kann nicht herabgestuft oder gelöscht werden.');
            }
        }

        $operation($targetId);
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
}
