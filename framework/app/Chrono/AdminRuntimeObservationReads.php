<?php

namespace App\Chrono;

/** Local-Admin fixed observations only; no path, account, threshold or repair selector. */
interface AdminRuntimeObservationReads
{
    public function adminRuntimeObservation(bool $includeTerminal): array;
}
