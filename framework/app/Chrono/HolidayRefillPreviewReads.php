<?php

namespace App\Chrono;

/** Optional local-Admin read capability; no write token or approval. */
interface HolidayRefillPreviewReads
{
    public const COMMAND_FIELDS = ['preview_holidays', 'holiday_year', 'confirm_bw_source', 'holiday_json'];

    public function holidayRefillPreview(mixed $year, mixed $region, #[\SensitiveParameter] mixed $json): array;
}
