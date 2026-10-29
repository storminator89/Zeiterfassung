<?php
namespace App\Chrono;
/** Optional own stored open/pending start-day facts, never a period close or subject selector. */
interface OwnMonthReviewReads
{
    public function ownMonthReview(string $month): array;
}
