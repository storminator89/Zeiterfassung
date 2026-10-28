<?php

namespace App\View\ExistingUi;

/** No token, digest, password, account model or submitted/old values. */
final readonly class FirstAdminSetupPage
{
    public function __construct(public Copy $copy, public ?string $error = null) {}
}
