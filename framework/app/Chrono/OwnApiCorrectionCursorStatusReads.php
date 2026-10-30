<?php

namespace App\Chrono;

/** Optional exclusive own-request ID boundary; no stable feed or subject capability. */
interface OwnApiCorrectionCursorStatusReads
{
    public function ownApiCorrectionCursorStatus(string $beforeId, ?string $requestId, ?string $recordId, string $status, int $limit, ?int $offset): array;
}
