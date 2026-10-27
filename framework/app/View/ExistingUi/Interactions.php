<?php

namespace App\View\ExistingUi;

/** Trusted composition-only display capability, never request data or authorization. */
final readonly class Interactions
{
    public function __construct(
        public bool $login = false,
        public bool $attendance = false,
        public bool $logout = false,
    ) {}
}
