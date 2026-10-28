<?php

namespace App\Chrono;

/** Confirmed post-COMMIT transport hint only; no identity, command, password or account projection. */
final readonly class AdminUserMutationResult
{
    public function __construct(public bool $reauthenticate) {}
}
