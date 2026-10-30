<?php

namespace App\Chrono;

/** Explicit own stored-credit review, not an entitlement or approval account. */
interface OwnAbsenceYearReads
{
    public function ownAbsenceYear(mixed $year = null, mixed $page = null): array;
}
