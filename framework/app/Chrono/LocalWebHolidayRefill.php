<?php

namespace App\Chrono;

interface LocalWebHolidayRefill
{
    public const COMMAND_FIELDS = ['refill_holidays', 'holiday_year', 'confirm_bw_source', 'holiday_json'];

    /** Confirmed committed receipt, never completeness or legal source attestation. */
    public function apply(array $command): array;
}
