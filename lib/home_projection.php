<?php

// Definitions only: no native bootstrap, calendar download, connection or writes.
require_once __DIR__.'/practice.php';
require_once __DIR__.'/reporting.php';
require_once __DIR__.'/planning_snapshot.php';

function home_working_days(PDO $db, int $user, string $startDate, string $endDate, ?Closure $planned = null): int
{
    $begin = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day');
    $interval = DateInterval::createFromDateString('1 day');
    $dateRange = new DatePeriod($begin, $interval, $end);
    $workingDays = 0;
    foreach ($dateRange as $date) {
        if (($planned === null ? planned_minutes($db, $user, $date->format('Y-m-d')) : $planned($date->format('Y-m-d'))) > 0) {
            $workingDays++;
        }
    }

    return $workingDays;
}

/** Native carry-in plus completed report-start-days, not a continuous time account. */
function home_total_overtime(PDO $db, int $user, array $reportDays, float $previousOvertime, ?Closure $planned = null): float
{
    $totalOverHours = (float) $previousOvertime;
    foreach ($reportDays as $day => $minutes) {
        $totalOverHours += ($minutes - ($planned === null ? planned_minutes($db, $user, $day) : $planned($day))) / 60;
    }

    return $totalOverHours;
}

/** Stored calendar only. The native caller still owns its existing calendar refresh. */
function home_week_holidays(PDO $db, $currentYear, $currentWeekNumber): array
{
    $monday = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->setISODate((int) $currentYear, (int) $currentWeekNumber, 1);
    $stmt = $db->prepare('SELECT datum,name FROM Feiertage WHERE datum>=? AND datum<=? ORDER BY datum');
    $stmt->execute([$monday->format('Y-m-d'), $monday->modify('+6 days')->format('Y-m-d')]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function home_daily_hours(PDO $db, int $user, string $day, ?Closure $planned = null): string
{
    return report_format_hours(($planned === null ? planned_minutes($db, $user, $day) : $planned($day)) / 60);
}

/** Already-authorized snapshot data in, raw/unescaped presentation fields out. */
function home_summary(PDO $db, int $user, array $reportDays, float $previousOvertime, DateTimeImmutable $now): array
{
    $now = $now->setTimezone(new DateTimeZone('Europe/Berlin'));
    $planned = null;
    if ($db->inTransaction()) {
        // Laravel's own-reader already owns this stable snapshot. Build once,
        // use synchronously, then discard. Native callers without a transaction
        // retain the existing per-day SQL path; never introduce a hidden cache.
        $firstDay = $now->format('Y-m-01');
        $lastDay = $now->format('Y-m-t');
        foreach ($reportDays as $day => $_minutes) {
            if (strcmp((string) $day, $firstDay) < 0) { $firstDay = (string) $day; }
            if (strcmp((string) $day, $lastDay) > 0) { $lastDay = (string) $day; }
        }
        $planned = planned_minutes_snapshot($db, $user, $firstDay, $lastDay);
    }
    $totalOverHours = home_total_overtime($db, $user, $reportDays, $previousOvertime, $planned);
    $holidays = home_week_holidays($db, $now->format('o'), $now->format('W'));

    return [
        'current_month' => $now->format('m'),
        'current_date' => $now->format('Y-m-d'),
        'working_days_this_month' => home_working_days($db, $user, $now->format('Y-m-01'), $now->format('Y-m-t'), $planned),
        'total_overtime_hours' => $totalOverHours,
        'total_overtime_formatted' => report_format_hours($totalOverHours),
        'positive_overtime' => $totalOverHours > 0,
        'daily_hours_formatted' => home_daily_hours($db, $user, $now->format('Y-m-d'), $planned),
        'holidays_this_week' => array_map(static fn (array $holiday): array => [
            'date' => $holiday['datum'],
            'formatted_date' => date('d.m.Y', strtotime($holiday['datum'])),
            'name' => $holiday['name'],
        ], $holidays),
    ];
}

function calculateDuration($startzeit, $endzeit, $pause) {
    $minutes=report_minutes(['startzeit'=>$startzeit,'endzeit'=>$endzeit,'pause'=>$pause]);
    return $minutes===null ? '-' : report_format_hours($minutes/60);
}

/** Raw record contract is retained for the native outputRecordRow caller. */
function report_time_record_detail(array $record): array
{
    $calculation = report_record_calculation($record);
    $invalid = $calculation['status'] === 'invalid';
    $credit = !empty($record['absence_kind']);
    $startLabel = $credit ? substr($record['startzeit'], 0, 10) . ' · Gutschrift' : $record['startzeit'];
    $endLabel = $credit ? 'Keine Stempelzeit' : (($record['endzeit'] ?? '') === '' ? 'Läuft' : $record['endzeit']);
    $durationLabel = $invalid ? 'Ungültig' : ($credit ? (int)$record['planned_minutes'] . ' Sollminuten' : calculateDuration($record['startzeit'], $record['endzeit'], $record['pause']));

    return [
        'id' => (int) $record['id'],
        'week_number' => $record['weekNumber'] ?? ($calculation['week'] === null ? '-' : substr($calculation['week'], 5)),
        'start_label' => $startLabel,
        'end_label' => $endLabel,
        'duration_label' => $durationLabel,
        'pause' => $record['pause'],
        'location' => $record['standort'],
        'comment' => $record['beschreibung'],
        'is_invalid' => $invalid,
        'validation_error' => $calculation['error'],
        'is_unresolved' => !empty($record['has_absence_conflict']),
    ];
}

/**
 * Call AFTER native ten-raw-row pagination; preserve caller detail row order.
 * Complete, already-own-user-scoped rows affect totals only (CH-05), not rows.
 * A bad completed interval/pause invalidates its whole day's displayed total;
 * unreadable starts remain visible without acquiring an invented calendar day.
 * Conflicts must be derived from complete own-user rows before pagination or
 * start-day filtering. A counterpart can start on a different, off-page day.
 * Measured source durations/pauses remain visible; conflicted day totals are
 * explicitly unresolved, not a choice to zero either source projection.
 * No SQL, escaping, HTML, data changes or global timezone mutation.
 */
function report_time_record_groups(array $pagedRows, ?array $completeDayRows = null, ?array $conflicts = null): array
{
    $conflicts ??= report_absence_conflicts($completeDayRows ?? $pagedRows);
    $conflictLookup = report_conflict_lookup($conflicts);
    $dailyRecords = [];
    foreach ($pagedRows as $index => $record) {
        $calculation = report_record_calculation($record);
        $record['weekNumber'] = $calculation['week'] === null ? '-' : substr($calculation['week'], 5);
        $record['workDate'] = $calculation['day'];
        $record['has_absence_conflict'] = isset($conflictLookup[report_conflict_key($record)]);
        // An opaque DOM key is not a report date; keep each undated source row.
        $key = $calculation['day'] ?? 'invalid-start-' . (int)$record['id'] . '-' . $index;
        $dailyRecords[$key][] = $record;
    }

    $summaryRecords = $dailyRecords;
    if ($completeDayRows !== null) {
        $summaryRecords = [];
        foreach ($completeDayRows as $record) {
            $workDate = report_record_calculation($record)['day'];
            if ($workDate !== null && isset($dailyRecords[$workDate])) {
                $summaryRecords[$workDate][] = $record;
            }
        }
        foreach ($dailyRecords as $key => $rows) {
            if ($rows[0]['workDate'] === null) { $summaryRecords[$key] = $rows; }
        }
    }

    $groups = [];
    foreach ($dailyRecords as $key => $dayRecords) {
        if (!isset($summaryRecords[$key])) {
            throw new RuntimeException('Complete day rows missing for a visible date.');
        }
        $totalMinutes = 0;
        $totalPause = 0;
        $invalidCount = 0;
        foreach ($summaryRecords[$key] as $record) {
            $calculation = report_record_calculation($record);
            if ($calculation['status'] === 'invalid') { $invalidCount++; }
            elseif ($calculation['status'] === 'completed') {
                $totalMinutes += $calculation['minutes'];
                $totalPause += $calculation['pause'];
            }
        }
        $workDate = $dayRecords[0]['workDate'];
        $subject = (int)($dayRecords[0]['user_id'] ?? 0);
        $conflictCount = 0;
        foreach ($conflicts as $conflict) {
            if ((int)$conflict['user_id'] === $subject && $workDate !== null
                && ($conflict['work_day'] === $workDate || $conflict['absence_day'] === $workDate)) {
                $conflictCount++;
            }
        }
        $groups[] = [
            'work_date' => $workDate,
            'group_key' => $key,
            'formatted_date' => $workDate === null ? 'Datum ungültig' : (new DateTimeImmutable($workDate, new DateTimeZone('Europe/Berlin')))->format('d.m.Y'),
            'total_duration' => $invalidCount > 0 ? 'Ungültig' : ($conflictCount > 0 ? 'Klärung offen' : report_format_hours($totalMinutes / 60)),
            'total_pause' => $invalidCount > 0 ? null : $totalPause,
            'invalid_count' => $invalidCount,
            'is_unresolved' => $conflictCount > 0,
            'conflict_count' => $conflictCount,
            'entry_count' => count($dayRecords),
            // Keep raw keys as well as the agreed presentation keys.
            'records' => array_map(static fn (array $record): array => array_merge($record, report_time_record_detail($record)), $dayRecords),
        ];
        if ($completeDayRows !== null) {
            $groups[array_key_last($groups)]['day_entry_count'] = count($summaryRecords[$key]);
            $groups[array_key_last($groups)]['is_partial'] = count($dayRecords) < count($summaryRecords[$key]);
        }
    }

    return $groups;
}
