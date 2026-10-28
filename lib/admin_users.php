<?php

/**
 * Internal helper for the existing authenticated admin edit/delete handlers.
 * SQLite's writer lock keeps the current role/count and mutation atomic.
 * A null next role denotes deletion. There is no separate active-account flag.
 */
function admin_mutate_existing_user(PDO $db, mixed $userId, ?string $nextRole, Closure $operation): void
{
    $targetId = admin_existing_user_id($userId);

    // A failed BEGIN owns no transaction; only roll back after acquiring it.
    $db->exec('BEGIN IMMEDIATE');
    try {
        admin_existing_user_guard_locked($db, $targetId, $nextRole);
        $operation($targetId);
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
}

/** Same native target parser; the caller keeps validation before acquiring its transaction. */
function admin_existing_user_id(mixed $userId): int
{
    $targetId = filter_var($userId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($targetId === false) {
        throw new InvalidArgumentException('Ungültiger Benutzer.');
    }
    return $targetId;
}

/** Existing target/last-admin rule only; no transaction, mutation, actor or callback API. */
function admin_existing_user_guard_locked(PDO $db, int $targetId, ?string $nextRole): void
{
    if (!$db->inTransaction()) {
        throw new LogicException('Existing-user validation requires the caller transaction.');
    }
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
}

/** A create owns its transaction; never join or roll back another caller's work. */
function admin_create_user(PDO $db, Closure $operation): void
{
    // BEGIN fails before this helper owns a transaction (including nested use).
    if ($db->exec('BEGIN IMMEDIATE') === false) {
        throw new PDOException('Benutzer konnte nicht gespeichert werden.');
    }
    try {
        $operation();
        if ($db->exec('COMMIT') === false) {
            throw new PDOException('Benutzer konnte nicht gespeichert werden.');
        }
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->exec('ROLLBACK');
        }
        throw $error;
    }
}

/** Canonical positive scalar IDs only; no bool/array/float/zero coercion. */
function admin_assignment_id(mixed $value, string $message): int
{
    if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)) {
        throw new InvalidArgumentException($message);
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        throw new InvalidArgumentException($message);
    }
    return $id;
}

/** Preserve the existing exact role allowlist, including non-scalar rejection. */
function admin_assignment_role(mixed $role): void
{
    if (!in_array($role, ['user', 'admin', 'supervisor'], true)) {
        throw new InvalidArgumentException('Ungültige Benutzerrolle.');
    }
}

/**
 * Native create/edit only. Caller already owns its IMMEDIATE writer lock.
 * Validate the selected chain, not unrelated legacy components. No repairs,
 * same-department rule, LDAP role policy, role changes or account data export.
 * @return array{department_id:int,supervisor_id:?int}
 */
function validateUserAssignment(PDO $conn, mixed $role, mixed $supervisor, mixed $department, ?int $targetId = null): array
{
    if (!$conn->inTransaction()) {
        throw new LogicException('User assignment validation requires the caller transaction.');
    }
    admin_assignment_role($role);
    $departmentId = admin_assignment_id($department, 'Ungültige Abteilungs-ID.');
    $stmt = $conn->prepare('SELECT id FROM departments WHERE id = ?');
    $stmt->execute([$departmentId]);
    $exists = $stmt->fetchColumn();
    $stmt->closeCursor();
    if ($exists === false) {
        throw new InvalidArgumentException('Die Abteilung existiert nicht mehr.');
    }
    if ($supervisor === '') {
        return ['department_id' => $departmentId, 'supervisor_id' => null];
    }
    $supervisorId = admin_assignment_id($supervisor, 'Ungültiger Vorgesetzter.');
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role IN ('supervisor', 'admin')");
    $stmt->execute([$supervisorId]);
    $exists = $stmt->fetchColumn();
    $stmt->closeCursor();
    if ($exists === false) {
        throw new InvalidArgumentException('Ungültiger Vorgesetzter.');
    }
    if ($supervisorId === $targetId) {
        throw new InvalidArgumentException('Eine Selbstzuordnung als Vorgesetzter ist nicht zulässig.');
    }

    // IDs/edges only: no password, token, email or other account data is read.
    $stmt = $conn->query('SELECT id, supervisor_id FROM users');
    $graph = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $graph[(int)$row['id']] = $row['supervisor_id'];
    }
    $stmt->closeCursor();
    $seen = [];
    $cursor = $supervisorId;
    while ($cursor !== null) {
        if ($cursor === $targetId || isset($seen[$cursor])) {
            throw new InvalidArgumentException('Die Vorgesetztenzuordnung würde eine zyklische Beziehung enthalten.');
        }
        if (!array_key_exists($cursor, $graph)) {
            throw new InvalidArgumentException('Die Vorgesetztenbeziehung ist nicht eindeutig auflösbar.');
        }
        $seen[$cursor] = true;
        $next = $graph[$cursor];
        $cursor = $next === null ? null : admin_assignment_id($next, 'Die Vorgesetztenbeziehung ist nicht eindeutig auflösbar.');
    }
    return ['department_id' => $departmentId, 'supervisor_id' => $supervisorId];
}
