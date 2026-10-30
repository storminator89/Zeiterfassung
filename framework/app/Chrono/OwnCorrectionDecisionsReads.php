<?php
namespace App\Chrono;

/** Optional read-only own terminal request feedback, not push/unread state or a wider queue. */
interface OwnCorrectionDecisionsReads
{
    public function ownCorrectionDecisions(): array;
}
