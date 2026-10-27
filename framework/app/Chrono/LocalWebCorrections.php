<?php

namespace App\Chrono;

/** Existing local-web correction commands; no actor/role/source input contract. */
interface LocalWebCorrections
{
    public function apply(array $command): void;
}
