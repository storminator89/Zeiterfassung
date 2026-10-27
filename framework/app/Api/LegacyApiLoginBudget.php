<?php

namespace App\Api;

/** Explicitly admitted shared budget; no default cache/persistent-store binding. */
interface LegacyApiLoginBudget
{
    public function consume(string $username, string $remoteAddress): void;
}
