<?php

namespace App\Chrono;

/** Optional explicit local-Admin read; never an assignment or migration capability. */
interface DepartmentIntegrityReads
{
    public function departmentIntegrity(mixed $after = null): array;
}
