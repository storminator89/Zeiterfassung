<?php

namespace App\Chrono;

/** Existing local-web model form fields; no caller-supplied actor or role. */
interface LocalWebWorkModels
{
    public function apply(array $command): void;
}
