<?php

namespace App\Chrono;

/** Own-user absence only; no caller-supplied actor, role, source or audit data. */
interface LocalWebAbsences
{
    public function create(string $start, string $end, string $kind): array;
}
