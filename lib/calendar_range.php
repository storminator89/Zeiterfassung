<?php
require_once __DIR__ . '/calendar_events.php';

/** The existing calendar shows Monday–Sunday in booking-local (Berlin) time. */
function calendar_visible_week($requestedDate, DateTimeImmutable $today): array
{
    $today = $today->setTimezone(new DateTimeZone('Europe/Berlin'))->setTime(0, 0);
    $selected = $today;
    if (is_string($requestedDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $requestedDate)) {
        $candidate = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedDate, $today->getTimezone());
        if ($candidate && $candidate->format('Y-m-d') === $requestedDate && $candidate->format('Y') !== '0000') {
            $selected = $candidate;
        }
    }
    $start = $selected->modify('-' . ((int)$selected->format('N') - 1) . ' days');
    $end = $start->modify('+7 days');
    // Do not pass an out-of-format date to the calendar at the four-digit-year edges.
    if (strlen($start->format('Y-m-d')) !== 10 || strlen($end->format('Y-m-d')) !== 10) {
        return calendar_visible_week(null, $today);
    }
    return [
        'start' => $start,
        'end' => $end, // Exclusive local midnight, also across DST changes.
        'previous' => $start->modify('-7 days')->format('Y-m-d'),
        'next' => $end->format('Y-m-d'),
        'today' => $today->format('Y-m-d'),
    ];
}

/** Presentation only; input bookings have already been scoped to the signed-in user. */
function calendar_range_events(array $records, array $holidays, array $range, array $labels): array
{
    $events = [];
    $holidayCredits = [];
    $from = $range['start']->getTimestamp();
    $to = $range['end']->getTimestamp();
    foreach ($records as $record) {
        [$start, $end] = booking_span($record);
        if ($start >= $to || $end <= $from) {
            continue;
        }
        $event = calendar_record_event($record, $labels);
        $events[] = $event;
        $kind = (string)(($record['absence_kind'] ?? '') ?: ($record['beschreibung'] ?? ''));
        if ($event['isAllday'] && $kind === 'Feiertag') {
            $holidayCredits[$event['start']] = true;
        }
    }
    foreach ($holidays as $holiday) {
        $day = $holiday['datum'];
        if ($day < $range['start']->format('Y-m-d') || $day >= $range['end']->format('Y-m-d') || isset($holidayCredits[$day])) {
            continue;
        }
        $events[] = [
            'id' => 'feiertag_' . $day,
            'calendarId' => '1',
            'title' => $labels['Feiertag'] . ' - ' . $holiday['name'],
            'start' => $day,
            'end' => $day,
            'category' => 'allday',
            'isAllday' => true,
            'color' => '#ffffff',
            'backgroundColor' => '#b45309',
            'borderColor' => '#b45309',
            'location' => '',
        ];
    }
    return $events;
}
