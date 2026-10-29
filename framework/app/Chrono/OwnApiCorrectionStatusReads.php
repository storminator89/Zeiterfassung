<?php

namespace App\Chrono;

/** Optional own-user API facts only; no review/withdrawal or subject capability. */
interface OwnApiCorrectionStatusReads
{
    public function ownApiCorrectionStatus(string $status, int $limit, ?int $offset): array;
}
