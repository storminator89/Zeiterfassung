<?php

namespace App\Chrono;

/** Existing bearer API channel; aliases share this boundary, not transport parsing. */
interface ApiAttendance
{
    public function transition(string $eventId, string $action, bool $confirm = false, ?int $expectedRecord = null): array;
}
