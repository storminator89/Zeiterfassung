<?php

namespace App\Accounts;

/** Returned only after AttendanceWriter confirms COMMIT and its idle state. */
final readonly class AccountLifecycleTransitionResult
{
    public function __construct(public int $target, public string $status, public int $generation) {}
}
