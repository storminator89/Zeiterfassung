<?php
namespace App\Chrono;

/** Optional complete navigation of existing own month facts; no subject/category or closing authority. */
interface OwnMonthReviewFindingReads
{
    public function ownMonthReviewFindings(string $month,int $page): array;
}
