<?php
namespace App\Chrono;
/** Optional own completed-day comparison. No subject selection or account-writing authority. */
interface OwnPeriodComparisonReads
{
    public function ownPeriodComparison(string $from,string $through): array;
}
