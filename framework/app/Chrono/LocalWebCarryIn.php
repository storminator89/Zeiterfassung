<?php

namespace App\Chrono;

/** One own-Admin carry-in replacement; no target, role, reason or account command. */
interface LocalWebCarryIn
{
    /** The committed REAL value's roundtrip display string. */
    public function replace(mixed $raw): string;
}
