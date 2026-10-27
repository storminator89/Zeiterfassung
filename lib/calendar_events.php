<?php
require_once __DIR__ . '/booking.php';

/** Presentation only: credits describe a local day, not hours of attendance. */
function calendar_record_event(array $record, array $labels): array
{
    $isAbsence = booking_is_absence($record);
    $description = $record['beschreibung'] ?? '';
    $kind = (string)(($record['absence_kind'] ?? '') ?: ($record['beschreibung'] ?? ''));
    $start = $record['startzeit'];
    $end = $record['endzeit'];
    if ($isAbsence) {
        $start = booking_local_date(booking_time($start))->format('Y-m-d');
        // TOAST UI uses an inclusive end date for all-day events.
        $end = $start;
    }
    $colors = ['Urlaub' => '#2563eb', 'Krankheit' => '#b91c1c', 'Feiertag' => '#b45309'];
    $color = $isAbsence ? ($colors[$kind] ?? '#4a5568') : '#4a5568';

    return [
        'id' => (string)$record['id'],
        'calendarId' => '1',
        'title' => $isAbsence ? ($labels[$kind] ?? $kind) : ($description !== '' ? $description : $labels['Arbeit']),
        'start' => $start,
        'end' => $end,
        'category' => $isAbsence ? 'allday' : 'time',
        'isAllday' => $isAbsence,
        'color' => '#ffffff',
        'backgroundColor' => $color,
        'borderColor' => $isAbsence ? $color : '#2d3748',
        'location' => $record['standort'],
    ];
}
