<?php

namespace App\View\ExistingUi;

/** Already-authorized, presentation-only form options and request cards. */
final readonly class CorrectionsPage
{
    public function __construct(
        public array $recordOptions,
        public array $requests,
        public int $selectedRecordId = 0,
        public ?string $error = null,
        public bool $saved = false,
        public bool $enabled = false,
    ) {}
}
