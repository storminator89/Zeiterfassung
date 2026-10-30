<?php

namespace App\Chrono;

/** Optional exact request selector within the existing own/admin/current-team queue. */
interface CorrectionQueueRequestReads
{
    public function correctionRequestPage(string $status, int $page, ?int $previewRequestId, mixed $requestId): array;
}
