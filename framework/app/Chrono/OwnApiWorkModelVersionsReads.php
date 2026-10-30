<?php

namespace App\Chrono;

/** Optional own stored-version discovery; no subject or model-write capability. */
interface OwnApiWorkModelVersionsReads
{
    public function ownApiWorkModelVersions(int $limit, ?int $offset): array;
}
