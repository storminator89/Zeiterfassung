<?php

namespace App\Chrono;

/** Optional own-user stored facts; no request subject, violation or mutation authority. */
interface OwnOpenReviewReads
{
    public function ownOpenReview(): array;
}
