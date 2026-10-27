<?php

namespace App\View\ExistingUi;

/** Authorized current rule rows only, not an account, writer or validation engine. */
final readonly class AdminPauseSettingsPage
{
    /** @param list<array{id: int, hours_threshold: mixed, minimum_pause: mixed}> $rules Native values, unescaped. */
    public function __construct(
        public array $rules,
        public ?string $error = null,
        public bool $enabled = false,
    ) {}
}
