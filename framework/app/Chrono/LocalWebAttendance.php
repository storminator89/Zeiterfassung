<?php

namespace App\Chrono;

/** Own-user local web transitions; not an HTTP action or API/terminal channel. */
interface LocalWebAttendance
{
    public function transition(string $eventId, string $action, bool $confirm = false, ?int $expectedRecord = null, ?array $startMetadata = null): array;
}
