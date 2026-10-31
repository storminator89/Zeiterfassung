<?php

namespace App\Chrono;

/** Read-only live display projection. Never a booked/credited duration or writer input. */
final class LiveAttendanceTiming
{
    public static function fromSnapshot(array $status, ?array $session, array $breaks, int $now): ?array
    {
        $state = $status['state'] ?? null;
        $record = $status['record_id'] ?? null;
        if (!in_array($state, ['working', 'paused'], true) || !is_int($record) || $record < 1
            || $session === null || !is_int($session['id'] ?? null) || $session['id'] < 1
            || ($session['record_id'] ?? null) !== $record || !array_key_exists('ended_at', $session) || $session['ended_at'] !== null
            || !is_int($session['started_at'] ?? null) || $session['started_at'] < 0
            || $now < $session['started_at']) {
            return null;
        }
        $start = $session['started_at'];
        $closedSeconds = 0;
        $openStart = null;
        $lastEnd = $start;
        foreach ($breaks as $break) {
            $from = $break['started_at'] ?? null;
            $until = $break['ended_at'] ?? null;
            if (!is_array($break) || !array_key_exists('ended_at', $break) || ($break['session_id'] ?? null) !== $session['id'] || !is_int($from)
                || $from < $lastEnd || $from > $now || $openStart !== null
                || ($until !== null && (!is_int($until) || $until < $from || $until > $now))) {
                return null;
            }
            if ($until === null) {
                $openStart = $from;
            } else {
                $duration = $until - $from;
                if ($closedSeconds > PHP_INT_MAX - $duration) {
                    return null;
                }
                $closedSeconds += $duration;
                $lastEnd = $until;
            }
        }
        if (($state === 'paused') !== ($openStart !== null)) {
            return null;
        }
        $elapsed = ($openStart ?? $now) - $start - $closedSeconds;
        if ($elapsed < 0) {
            return null;
        }
        return [
            'version' => 1, 'record_id' => (string) $record, 'state' => $state,
            'as_of_epoch' => $now, 'started_at_epoch' => $start,
            'closed_pause_seconds' => $closedSeconds, 'pause_started_at_epoch' => $openStart,
            'elapsed_work_seconds' => $elapsed,
        ];
    }
}
