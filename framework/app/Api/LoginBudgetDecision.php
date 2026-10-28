<?php

namespace App\Api;

/** Provisional until the caller confirms COMMIT; never an HTTP response. */
final readonly class LoginBudgetDecision
{
    public function __construct(public bool $limited) {}
}
