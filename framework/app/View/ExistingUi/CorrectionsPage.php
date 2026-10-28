<?php

namespace App\View\ExistingUi;

/** Already-authorized, presentation-only form options and request cards. */
final readonly class CorrectionsPage
{
    /** $requests contain selected preview {before:string, after:string, difference:?string, proposed_usable:bool}, never raw snapshots. */
    public function __construct(
        public array $recordOptions,
        public array $requests,
        public int $selectedRecordId = 0,
        public ?string $error = null,
        public bool $saved = false,
        public bool $enabled = false,
        public bool $selectionInvalid = false,
    ) {}
}
