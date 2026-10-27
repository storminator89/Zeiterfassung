<?php

// Definitions only: no native bootstrap, calendar download, connection or writes.
require_once __DIR__.'/practice.php';
require_once __DIR__.'/reporting.php';

function home_working_days(PDO $db, int $user, string $startDate, string $endDate): int
{
    $begin = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day');
    $interval = DateInterval::createFromDateString('1 day');
    $dateRange = new DatePeriod($begin, $interval, $end);
    $workingDays = 0;
    foreach ($dateRange as $date) {
        if (planned_minutes($db, $user, $date->format('Y-m-d')) > 0) {
            $workingDays++;
        }
    }

    return $workingDays;
}

/** Native carry-in plus completed report-start-days, not a continuous time account. */
function home_total_overtime(PDO $db, int $user, array $reportDays, float $previousOvertime): float
{
    $totalOverHours = (float) $previousOvertime;
    foreach ($reportDays as $day => $minutes) {
        $totalOverHours += ($minutes - planned_minutes($db, $user, $day)) / 60;
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

function home_daily_hours(PDO $db, int $user, string $day): string
{
    return report_format_hours(planned_minutes($db, $user, $day) / 60);
}

/** Already-authorized snapshot data in, raw/unescaped presentation fields out. */
function home_summary(PDO $db, int $user, array $reportDays, float $previousOvertime, DateTimeImmutable $now): array
{
    $now = $now->setTimezone(new DateTimeZone('Europe/Berlin'));
    $totalOverHours = home_total_overtime($db, $user, $reportDays, $previousOvertime);
    $holidays = home_week_holidays($db, $now->format('o'), $now->format('W'));

    return [
        'current_month' => $now->format('m'),
        'current_date' => $now->format('Y-m-d'),
        'working_days_this_month' => home_working_days($db, $user, $now->format('Y-m-01'), $now->format('Y-m-t')),
        'total_overtime_hours' => $totalOverHours,
        'total_overtime_formatted' => report_format_hours($totalOverHours),
        'positive_overtime' => $totalOverHours > 0,
        'daily_hours_formatted' => home_daily_hours($db, $user, $now->format('Y-m-d')),
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
    $credit = !empty($record['absence_kind']);
    $startLabel = $credit ? substr($record['startzeit'], 0, 10) . ' · Gutschrift' : $record['startzeit'];
    $endLabel = $credit ? 'Keine Stempelzeit' : ($record['endzeit'] ?? 'Läuft');
    $durationLabel = $credit ? (int)$record['planned_minutes'] . ' Sollminuten' : calculateDuration($record['startzeit'], $record['endzeit'], $record['pause']);

    return [
        'id' => (int) $record['id'],
        'week_number' => $record['weekNumber'],
        'start_label' => $startLabel,
        'end_label' => $endLabel,
        'duration_label' => $durationLabel,
        'pause' => $record['pause'],
        'location' => $record['standort'],
        'comment' => $record['beschreibung'],
    ];
}

/**
 * Call only AFTER the native ten-raw-row pagination; preserve caller row order.
 * The native list supplies instant DESC/ID DESC order (CH-12).
 * Deliberately retain page-local grouping and raw floor/duration/pause semantics,
 * even where a negative interval is rejected by the separate detail formatter.
 * No SQL, report_days validation, escaping, HTML or global timezone mutation.
 */
function report_time_record_groups(array $pagedRows): array
{
    $dailyRecords = [];
    foreach ($pagedRows as $record) {
        $record['weekNumber'] = substr(report_week($record), 5);
        $record['workDate'] = report_day($record);
        $dailyRecords[$record['workDate']][] = $record;
    }

    $groups = [];
    foreach ($dailyRecords as $workDate => $dayRecords) {
        $totalMinutes = 0;
        $totalPause = 0;
        foreach ($dayRecords as $record) {
            if ($record['endzeit']) {
                // Native config fixes Berlin; do not inherit framework UTC here.
                $start = new DateTime($record['startzeit'], new DateTimeZone('Europe/Berlin'));
                $end = new DateTime($record['endzeit'], new DateTimeZone('Europe/Berlin'));
                $diff = $end->getTimestamp() - $start->getTimestamp();
                $totalMinutes += floor($diff / 60);
                $totalPause += intval($record['pause']);
            }
        }
        $totalMinutes -= $totalPause;
        $totalHours = floor($totalMinutes / 60);
        $remainingMinutes = $totalMinutes % 60;
        $formattedTotal = sprintf('%02d:%02d', $totalHours, $remainingMinutes);
        $dateObj = new DateTime($workDate, new DateTimeZone('Europe/Berlin'));

        $groups[] = [
            'work_date' => $workDate,
            'formatted_date' => $dateObj->format('d.m.Y'),
            'total_duration' => $formattedTotal,
            'total_pause' => $totalPause,
            'entry_count' => count($dayRecords),
            // Keep native raw keys as well as the agreed Blade presentation keys.
            'records' => array_map(static fn (array $record): array => array_merge($record, report_time_record_detail($record)), $dayRecords),
        ];
    }

    return $groups;
}
