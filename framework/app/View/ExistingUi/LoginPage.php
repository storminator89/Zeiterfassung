<?php

namespace App\View\ExistingUi;

/** Inert presentation contract, not a session/auth/setup service. */
final readonly class LoginPage
{
    public function __construct(
        public Copy $copy,
        public ?string $error = null,
        public ?string $successMessage = null,
        public bool $showRegistration = false,
        public Interactions $interactions = new Interactions,
        public bool $showSetup = false,
    ) {}
}
