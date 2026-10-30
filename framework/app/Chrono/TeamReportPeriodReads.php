<?php

namespace App\Chrono;

/** Optional display-only period for the existing supervisor detail table. */
interface TeamReportPeriodReads
{
    public function teamReportPeriod(string $userSearch, mixed $page, ?array $range, bool $invalid): array;
}
