<?php

namespace App\Chrono;

/** Optional own stored assignment/week-rule display; no subject or account mutation. */
interface OwnSettingsProfileReads
{
    public function ownSettingsProfile(): array;
}
