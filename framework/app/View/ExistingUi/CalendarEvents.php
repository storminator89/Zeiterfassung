<?php

namespace App\View\ExistingUi;

/** Presentation adapter only: canonical native events in, FullCalendar values out. */
final class CalendarEvents
{
    /** @param list<array> $events Unchanged calendar_range_events() output. */
    public static function fromNative(array $events): array
    {
        require_once dirname(__DIR__, 4).'/lib/booking.php';

        return array_map(static function (array $event): array {
            $allDay = (bool) $event['isAllday'];
            $start = \booking_local_date(\booking_time($event['start']));
            $missingEnd = $event['end'] === null || $event['end'] === '';
            $end = $missingEnd ? null : \booking_local_date(\booking_time($event['end']));
            $endMilliseconds = $end === null ? null : $end->getTimestamp() * 1000;
            // TOAST displayed a missing timed end as +30 civil minutes. This is
            // presentation only: retain the real missing end, never write it back.
            $displayEnd = $end ?? ($allDay ? $start : $start->modify('+30 minutes'));

            return [
                'id' => (string) $event['id'],
                'title' => (string) $event['title'],
                'allDay' => $allDay,
                'start' => $allDay ? $start->format('Y-m-d') : $start->getTimestamp() * 1000,
                // FullCalendar's all-day end is exclusive. Advance a Berlin civil
                // day, not 86400 seconds, across either daylight-saving transition.
                'end' => $allDay ? $displayEnd->modify('+1 day')->format('Y-m-d') : $endMilliseconds,
                'color' => (string) $event['backgroundColor'],
                'contrastColor' => (string) $event['color'],
                'editable' => false,
                'extendedProps' => ['chrono' => [
                    'start' => $start->getTimestamp() * 1000,
                    'end' => $endMilliseconds,
                    'displayEnd' => $displayEnd->getTimestamp() * 1000,
                    // Keep the original inclusive dates for the existing modal.
                    'startDate' => $start->format('d.m.Y'),
                    'endDate' => $displayEnd->format('d.m.Y'),
                    'location' => (string) ($event['location'] ?? ''),
                    'borderColor' => (string) $event['borderColor'],
                ]],
            ];
        }, $events);
    }
}
