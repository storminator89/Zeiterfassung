<?php

namespace App\View\ExistingUi;

/** No token, digest, password, account model or submitted/old values. */
final readonly class FirstAdminSetupPage
{
    /** Presentation-only preset, never an existing account or login credential. */
    public const STANDARD_USERNAME = 'admin';

    public function __construct(public Copy $copy, public ?string $error = null, public bool $requiresOperatorToken = true) {}
}
