<?php

namespace App\Chrono;

/** Optional narrower view of the existing authorized correction queue. */
interface OwnCorrectionQueueReads
{
    public function ownCorrectionRequestsPage(string $status, int $page, ?int $previewRequestId, mixed $requestId = null): array;
}
