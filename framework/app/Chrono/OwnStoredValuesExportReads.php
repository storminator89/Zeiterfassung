<?php
namespace App\Chrono;

/** Optional bounded own current stored SQL values; no subject, date/category filter, calculation or writer. */
interface OwnStoredValuesExportReads
{
    /** @return array{content:string,filename:string} Complete attachment bytes, never PDO/stream or partial rows. */
    public function ownStoredValuesExport(): array;
}
