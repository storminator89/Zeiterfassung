<?php

namespace App\Chrono;

/** Optional narrower lookup of an own stored request; no subject or write capability. */
interface OwnApiCorrectionRequestStatusReads
{
    public function ownApiCorrectionRequestStatus(string $requestId, string $status, int $limit, ?int $offset): array;
}
