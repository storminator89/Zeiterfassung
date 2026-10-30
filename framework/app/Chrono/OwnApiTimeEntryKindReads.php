<?php

namespace App\Chrono;

/** Optional own raw-row classification filter; no new identity or pagination. */
interface OwnApiTimeEntryKindReads
{
    public function ownApiTimeEntriesByKind(ApiTimeEntriesQuery $query, string $kind): array;
}
