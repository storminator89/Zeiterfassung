<?php
// Definitions only: read stored own-row facts, never create a time or decide a violation.
require_once __DIR__.'/attendance.php';

/** Internal already-authorized own-user projection within the caller's snapshot. */
function chrono_own_open_review_snapshot(PDO $pdo, int $user, DateTimeImmutable $loaded): array
{
    if (! $pdo->inTransaction() || $user < 1) { throw new LogicException('An authorized own-row snapshot is required.'); }
    $loaded = $loaded->setTimezone(new DateTimeZone('Europe/Berlin'));
    $cutoff = $loaded->setTime(0, 0)->getTimestamp();
    if (! $pdo->createFunction('chrono_own_open_epoch', 'report_stored_epoch', 1)) {
        throw new RuntimeException('Open-row date projection unavailable.');
    }
    // Both reads use the same fixed owner/date/open predicate BEFORE the row limit.
    // Unreadable starts remain unknown, never acquire an invented day or disappear.
    $where = "user_id=? AND (endzeit IS NULL OR endzeit='') AND (chrono_own_open_epoch(startzeit)<CAST(? AS INTEGER) OR chrono_own_open_epoch(startzeit) IS NULL)";
    $arguments = [$user, $cutoff];
    $total = (int) att_one($pdo, 'SELECT COUNT(*) AS total FROM zeiterfassung WHERE '.$where, $arguments)['total'];
    $statement = $pdo->prepare('SELECT id,startzeit FROM zeiterfassung WHERE '.$where.' ORDER BY (chrono_own_open_epoch(startzeit) IS NULL) DESC,chrono_own_open_epoch(startzeit),id LIMIT 5');
    try { $statement->execute($arguments);$rows = $statement->fetchAll(PDO::FETCH_ASSOC); }
    finally { $statement->closeCursor(); }
    $selected = [];
    foreach ($rows as $row) {
        $start = report_stored_epoch($row['startzeit']);
        $selected[] = ['record_id' => (string) $row['id'], 'start_label' => $start === null ? null : attendance_local($start)];
    }
    return ['loaded_at' => $loaded->format(DateTimeInterface::ATOM), 'cutoff_day' => $loaded->format('Y-m-d'),
        'count' => $total, 'rows' => $selected, 'limited' => $total > count($selected)];
}

/** Current native identity/version and all list/count reads share one owned snapshot. */
function chrono_native_own_open_review(PDO $pdo, int $actor, #[SensitiveParameter] string $authVersion): array
{
    if ($pdo->inTransaction()) { throw new LogicException('Own open-row review requires an idle PDO.'); }
    if (! $pdo->beginTransaction()) { throw new RuntimeException('Own open-row snapshot unavailable.'); }
    try {
        $current = att_one($pdo, 'SELECT role,password FROM users WHERE id=?', [$actor]);
        if ($actor < 1 || $current === null || $authVersion === ''
            || ! hash_equals($authVersion, hash('sha256', (string) $current['password']))) {
            throw new AttendanceError('Eigene Prüfliste nicht verfügbar.', 401);
        }
        if (! in_array($current['role'], ['user', 'supervisor', 'admin'], true)) {
            throw new AttendanceError('Eigene Prüfliste nicht verfügbar.', 403);
        }
        unset($current);
        $result = chrono_own_open_review_snapshot($pdo, $actor, new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')));
        if (! $pdo->commit()) { throw new RuntimeException('Own open-row snapshot not confirmed.'); }
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $error;
    }
}
