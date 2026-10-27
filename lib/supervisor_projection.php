<?php
require_once __DIR__.'/attendance.php';
require_once __DIR__.'/reporting.php';
require_once __DIR__.'/practice.php';

/**
 * Transitional mechanical extraction from supervisor.php at main 729250b.
 * Already-authorized complete rows only: this helper is NOT an access gate.
 * Native remains byte-identical until a separate cutover adopts this helper.
 * No new accounting rules: report_* and planned_minutes remain authoritative.
 * Diagnostics intentionally refuse this WHOLE independent-rollup report before
 * search/page filtering; never expose a misleading final partial total.
 */
function supervisor_report_projection(PDO $conn, array $zeiten, string $userSearch = '', mixed $requestedPage = null): array {
    $invalidRecords = [];
    foreach ($zeiten as $row) {
        $calculation = report_record_calculation($row);
        if ($calculation['status'] === 'invalid') {
            $invalidRecords[] = ['id' => (int)($row['id'] ?? 0), 'user_id' => (int)($row['user_id'] ?? 0), 'error' => $calculation['error']];
        }
    }
    $conflicts = report_absence_conflicts($zeiten);
    $empty = ['overtime' => [], 'details' => [], 'groups' => [], 'total_records' => 0, 'total_pages' => 1, 'page' => 1];
    $diagnostics = ['invalid_records' => $invalidRecords, 'absence_conflicts' => $conflicts];
    if ($invalidRecords !== [] || $conflicts !== []) {
        return ['available' => false] + $diagnostics + $empty;
    }
    $ueberstundenListe = [];
    $detaillierteDaten = [];

    // BEGIN native supervisor.php aggregation (context-only timezone substitution).
    foreach ($zeiten as &$entry) { $entry['weekNumber']=substr(report_week($entry),5);$entry['day']=report_day($entry);$entry['net_minutes']=report_minutes($entry);$entry['planned_minutes_day']=planned_minutes($conn,(int)$entry['user_id'],$entry['day']); } unset($entry);
    // Berechnung der Überstunden und detaillierte Daten
    $workHoursByUser = [];
    foreach ($zeiten as $zeit) {
        $userId = $zeit['user_id'];
        $day = $zeit['day'];
        $regelarbeitszeit = $zeit['regelarbeitszeit'] ?? 8.0;
        $vorherige_ueberstunden = $zeit['vorherige_ueberstunden'] ?? 0.0;

        if (!isset($workHoursByUser[$userId])) {
            $workHoursByUser[$userId] = [
                'username' => $zeit['username'],
                'days' => [],
                'regelarbeitszeit' => $regelarbeitszeit,
                'vorherige_ueberstunden' => $vorherige_ueberstunden,
                'total_hours' => 0,
                'total_days' => 0
            ];
        }

        if ($zeit['net_minutes']===null) { continue; }
        if (!isset($workHoursByUser[$userId]['days'][$day])) {
            $workHoursByUser[$userId]['days'][$day] = 0;
            $workHoursByUser[$userId]['total_days']++;
        }

        $start = new DateTime($zeit['startzeit'], new DateTimeZone('Europe/Berlin'));
        $end = new DateTime($zeit['endzeit'], new DateTimeZone('Europe/Berlin'));
        $interval = $start->diff($end);
        $pauseMinuten = intval($zeit['pause']) ?: 0;

        $gesamtMinuten = $zeit['net_minutes'];
        $workHoursByUser[$userId]['days'][$day] += $gesamtMinuten;
        $workHoursByUser[$userId]['total_hours'] += $gesamtMinuten / 60;
    }

    // Berechnung der Gesamtüberstunden pro Benutzer und Vorbereitung der detaillierten Daten
    foreach ($workHoursByUser as $userId => $data) {
        $totalOverMinutes = 0;
        $regelarbeitszeit = $data['regelarbeitszeit'];
        foreach ($data['days'] as $day => $totalMinutes) {
            $regularWorkingMinutesPerDay = planned_minutes($conn,(int)$userId,$day);

            $overMinutes = $totalMinutes - $regularWorkingMinutesPerDay;
            $totalOverMinutes += $overMinutes;
        }

        $totalOverMinutes += (int) round($data['vorherige_ueberstunden'] * 60);
        $formattedOvertime = report_format_hours($totalOverMinutes / 60);

        $ueberstundenListe[$userId] = [
            'username' => $data['username'],
            'overtime_minutes' => $totalOverMinutes,
            'ueberstunden' => $formattedOvertime
        ];

        $detaillierteDaten[$userId] = [
            'username' => $data['username'],
            'regelarbeitszeit' => $data['regelarbeitszeit'],
            'total_hours' => round($data['total_hours'], 2),
            'total_days' => $data['total_days'],
            'avg_hours_per_day' => ($data['total_days']>0 ? round($data['total_hours'] / $data['total_days'], 2) : 0),
            'overtime_minutes' => $totalOverMinutes,
            'ueberstunden' => $formattedOvertime
        ];
    }
    // END native aggregation.

    // BEGIN native group/search/page extraction (prepared Berlin day and raw page argument).
    // Group entries by user and date
    $groupedEntries = [];
    foreach ($zeiten as $zeit) {
        $userId = $zeit['user_id'];
        $date = $zeit['day'];
        $key = $userId . '_' . $date;

        if (!isset($groupedEntries[$key])) {
            $groupedEntries[$key] = [
                'user_id' => $userId,
                'username' => $zeit['username'],
                'date' => $date,
                'regelarbeitszeit' => $zeit['regelarbeitszeit'],
                'entries' => [],
                'total_duration' => 0,
                'total_break' => 0,
                'completed' => false
            ];
        }

        // Calculate duration for this entry
        $start = new DateTime($zeit['startzeit'], new DateTimeZone('Europe/Berlin'));
        $end = $zeit['endzeit'] ? new DateTime($zeit['endzeit'], new DateTimeZone('Europe/Berlin')) : null;
        $duration = $zeit['net_minutes']===null ? 0 : $zeit['net_minutes']+(int)$zeit['pause'];
        $break = $zeit['net_minutes']===null ? 0 : intval($zeit['pause']);
        if ($zeit['net_minutes']!==null) { $groupedEntries[$key]['completed']=true; }

        // Add entry with its individual duration minus break
        $groupedEntries[$key]['entries'][] = [
            'startzeit' => $zeit['startzeit'],
            'endzeit' => $zeit['endzeit'],
            'duration' => $duration - $break, // Individual duration minus break
            'pause' => $break,
            'standort' => $zeit['standort'],
            'beschreibung' => $zeit['beschreibung'] ?? ''
        ];

        // Update totals
        $groupedEntries[$key]['total_duration'] += $duration - $break; // Add duration minus break
        $groupedEntries[$key]['total_break'] += $break;
    }

    // Search the authorized groups before slicing, so matches on other pages are included.
    if ($userSearch !== '') {
        $groupedEntries = array_filter($groupedEntries, static fn (array $group): bool => mb_stripos($group['username'], $userSearch) !== false);
    }

    // This is the only table render/pagination path.
    $itemsPerPage = 10;
    $totalRecords = count($groupedEntries);
    $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
    $currentPage = is_string($requestedPage) ? (int)$requestedPage : 1;
    $currentPage = max(1, min($currentPage, $totalPages));
    $start = ($currentPage - 1) * $itemsPerPage;
    $pagedEntries = array_slice($groupedEntries, $start, $itemsPerPage, true);
    // END native group/search/page extraction.

    // Native render-loop arithmetic/formatting moved here, never into the DTO/Blade.
    foreach ($pagedEntries as &$group) {
        $totalDuration = $group['total_duration'];
        $hours = floor($totalDuration / 60);
        $minutes = $totalDuration % 60;
        $overtime = $group['completed'] ? $totalDuration - planned_minutes($conn,(int)$group['user_id'],$group['date']) : 0;
        $overtimeHours = floor(abs($overtime) / 60);
        $overtimeMinutes = abs($overtime % 60);
        $group['duration_formatted'] = sprintf('%02d:%02d', $hours, $minutes);
        $group['overtime_minutes'] = $overtime;
        $group['overtime_formatted'] = ($overtime >= 0 ? '+' : '-').sprintf('%02d:%02d', $overtimeHours, $overtimeMinutes);
        $group['date_formatted'] = practice_date($group['date'])->format('d.m.Y');
        $group['detail_id'] = 'time-details-'.$group['user_id'].'-'.$group['date'];
        $group['location'] = count($group['entries']) > 1 ? 'Mehrere' : $group['entries'][0]['standort'];
        foreach ($group['entries'] as &$entry) {
            $entryStart = new DateTime($entry['startzeit'], new DateTimeZone('Europe/Berlin'));
            $entryEnd = $entry['endzeit'] ? new DateTime($entry['endzeit'], new DateTimeZone('Europe/Berlin')) : null;
            $entryHours = floor($entry['duration'] / 60);
            $entryMinutes = $entry['duration'] % 60;
            $entry['start_formatted'] = $entryStart->format('H:i');
            $entry['end_formatted'] = $entryEnd?->format('H:i') ?? 'Läuft';
            $entry['duration_formatted'] = sprintf('%02d:%02d', $entryHours, $entryMinutes);
        }
        unset($entry);
    }
    unset($group);

    return ['available' => true] + $diagnostics + [
        'overtime' => $ueberstundenListe, 'details' => $detaillierteDaten,
        'groups' => $pagedEntries, 'total_records' => $totalRecords,
        'total_pages' => $totalPages, 'page' => $currentPage,
    ];
}
