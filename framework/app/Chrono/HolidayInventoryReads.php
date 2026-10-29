<?php

namespace App\Chrono;

/** Optional authenticated stored-calendar inventory, not import/write authority. */
interface HolidayInventoryReads
{
    public function holidayInventory(mixed $requestedYear = null): array;
}
