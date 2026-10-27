<?php

namespace App\View\ExistingUi;

/** Authorized current rule/department rows only, not an account or writer. */
final readonly class AdminPauseSettingsPage
{
    /**
     * @param list<array{id: int, hours_threshold: mixed, minimum_pause: mixed}> $rules Native values, unescaped.
     * @param list<array{id: int, name: mixed}> $departments Native values/order, unescaped.
     */
    public function __construct(
        public array $rules,
        public ?string $error = null,
        public bool $enabled = false,
        public array $departments = [],
    ) {}
}
