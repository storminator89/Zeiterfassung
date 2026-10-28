<?php

namespace App\View\ExistingUi;

/** No submitted identifiers, password, hash, account model or old form values. */
final readonly class RegistrationPage
{
    public function __construct(public Copy $copy, public ?string $error = null) {}
}
