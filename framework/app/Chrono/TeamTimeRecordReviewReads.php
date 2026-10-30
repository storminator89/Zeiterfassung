<?php

namespace App\Chrono;

/** Optional existing team-scope diagnostics only; no subject selector or repair. */
interface TeamTimeRecordReviewReads
{
    public function teamTimeRecordReview(): array;
}
