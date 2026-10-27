<?php

namespace App\Chrono;

/** Direct legacy /api.php/attendance only; no API aliases or transport parsing. */
interface ApiAttendance
{
    public function transition(string $eventId, string $action, bool $confirm = false): array;
}
