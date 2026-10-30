<?php

namespace App\Chrono;

/** Optional own stored-record reference filter; no subject, lookup or write capability. */
interface OwnApiCorrectionRecordStatusReads
{
    public function ownApiCorrectionRecordStatus(string $recordId, ?string $requestId, string $status, int $limit, ?int $offset): array;
}
