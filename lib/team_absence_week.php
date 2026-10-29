<?php
// Definitions only: fixed shared read projection, no bootstrap, connection or automatic read.
require_once __DIR__.'/team_status.php';
require_once __DIR__.'/calendar_range.php';

/** Same current direct-team/Admin plan in the caller's already-authorized snapshot. */
function chrono_team_absence_week_snapshot(PDO $pdo, int $actor, string $role, mixed $calendarDate, mixed $page, DateTimeImmutable $loaded): array
{
    if (! $pdo->inTransaction()) { throw new LogicException('The caller must own a team-absence snapshot.'); }
    if ($actor < 1 || ! in_array($role, ['admin', 'supervisor'], true)) {
        throw new AttendanceError('Team-Abwesenheitswoche nicht verfügbar.', 403);
    }
    $loaded = $loaded->setTimezone(new DateTimeZone('Europe/Berlin'));
    $range = \calendar_visible_week($calendarDate, $loaded);
    $days = [];
    for ($day = $range['start']; $day < $range['end']; $day = $day->modify('+1 day')) {
        $days[] = $day->format('Y-m-d');
    }
    // Preserve the existing report's exact current direct-team/Admin scope.
    // No own-row exception, arbitrary person selector, notes or health type.
    $scope = "(supervisor_id = ? OR ?='admin')";
    $parameters = [$actor, $role];
    $total = (int) chrono_team_status_rows($pdo, 'SELECT COUNT(*) AS total FROM users WHERE '.$scope, $parameters)[0]['total'];
    $pages = max(1, (int) ceil($total / 50));
    $selected = (is_int($page) || is_string($page))
        ? filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
    $selected = $selected === false ? 1 : min($selected, $pages);
    $roster = chrono_team_status_rows($pdo, 'SELECT id,username FROM users WHERE '.$scope.' ORDER BY username,id LIMIT 50 OFFSET ?',
        [...$parameters, ($selected - 1) * 50]);
    $people = $positions = [];
    foreach ($roster as $person) {
        $positions[(int) $person['id']] = count($people);
        $people[] = ['username' => $person['username'], 'days' => array_fill_keys($days, false)];
    }
    $incomplete = $truncated = false;
    if ($roster !== []) {
        // Strict persisted instant parser, not a text-prefix date/SQLite timezone.
        if (! $pdo->createFunction('chrono_team_absence_epoch', 'report_stored_epoch', 1)) {
            throw new LogicException('The team planning date projection is unavailable.');
        }
        $marks = implode(',', array_fill(0, count($roster), '?'));
        $ids = array_map(static fn (array $person): int => (int) $person['id'], $roster);
        $rows = chrono_team_status_rows($pdo, "SELECT z.user_id,z.startzeit,z.endzeit,z.pause FROM zeiterfassung z JOIN users u ON u.id=z.user_id
            WHERE (u.supervisor_id=? OR ?='admin') AND z.user_id IN ($marks)
            AND (z.absence_kind IN ('Urlaub','Krankheit') OR ((z.absence_kind IS NULL OR z.absence_kind='') AND z.beschreibung IN ('Urlaub','Krankheit')))
            AND ((chrono_team_absence_epoch(z.startzeit)>=CAST(? AS INTEGER) AND chrono_team_absence_epoch(z.startzeit)<CAST(? AS INTEGER)) OR chrono_team_absence_epoch(z.startzeit) IS NULL)
            ORDER BY (chrono_team_absence_epoch(z.startzeit) IS NULL),chrono_team_absence_epoch(z.startzeit),z.id LIMIT 1001",
            [...$parameters, ...$ids, $range['start']->getTimestamp(), $range['end']->getTimestamp()]);
        $truncated = count($rows) > 1000;
        foreach (array_slice($rows, 0, 1000) as $row) {
            $calculation = \report_record_calculation($row);
            if ($calculation['status'] !== 'completed') { $incomplete = true; continue; }
            // Zero/cancelled credit is not an absence day. Never invent a full day.
            if ($calculation['minutes'] <= 0) { continue; }
            $position = $positions[(int) $row['user_id']];
            if (array_key_exists($calculation['day'], $people[$position]['days'])) {
                $people[$position]['days'][$calculation['day']] = true;
            }
        }
    }

    return ['loaded_at' => $loaded->format(\DateTimeInterface::ATOM), 'days' => $days, 'people' => $people,
        'from' => $days[0], 'through' => $days[6], 'previous' => $range['previous'], 'next' => $range['next'],
        'today' => $range['today'], 'page' => $selected, 'pages' => $pages, 'total_people' => $total,
        'incomplete' => $incomplete, 'truncated' => $truncated];
}

/** Native current account/version and planning data share one read snapshot. */
function chrono_native_team_absence_week(PDO $pdo, int $actor, string $authVersion, mixed $calendarDate = null, mixed $page = null): array
{
    if ($pdo->inTransaction()) { throw new LogicException('Native team planning requires an idle PDO.'); }
    if (! $pdo->beginTransaction()) { throw new RuntimeException('Team-absence snapshot unavailable.'); }
    try {
        $current = att_one($pdo, 'SELECT role,password FROM users WHERE id=?', [$actor]);
        if ($actor < 1 || $current === null || $authVersion === ''
            || ! hash_equals($authVersion, hash('sha256', (string) $current['password']))) {
            throw new AttendanceError('Team-Abwesenheitswoche nicht verfügbar.', 401);
        }
        $role = (string) $current['role'];
        unset($current);
        if (! in_array($role, ['admin', 'supervisor'], true)) { throw new AttendanceError('Team-Abwesenheitswoche nicht verfügbar.', 403); }
        $result = chrono_team_absence_week_snapshot($pdo, $actor, $role, $calendarDate, $page,
            new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')));
        if (! $pdo->commit()) { throw new RuntimeException('Team-absence snapshot could not be completed.'); }
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $error;
    }
}
