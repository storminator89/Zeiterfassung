<?php

namespace App\View\ExistingUi;

/** Prepared own native fragment fields only; no DB, clock, auth or domain rules. */
final readonly class LatestTimeRecordPage
{
    /** @param array{id:int,invalid:bool,error:?string,duration:string,start:string,end:string,pause:string,location:string,comment:string,timestamp_review?:bool}|null $entry */
    public function __construct(public Copy $copy, public ?array $entry) {}
}
