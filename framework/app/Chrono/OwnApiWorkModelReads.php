<?php

namespace App\Chrono;

/** Optional own stored effective version only; no subject or model-write capability. */
interface OwnApiWorkModelReads
{
    public function ownApiWorkModel(?string $day): array;
}
