<?php

namespace App\View\ExistingUi;

/** Own display/session preference values only; no model, query or account mutation. */
final readonly class SettingsPage
{
    public function __construct(
        public string $themeMode,
        public string|int|float|null $regularHours,
        public string|int|float|null $carryInHours,
        public ?string $error = null,
        public bool $saved = false,
    ) {
        if (! in_array($themeMode, ['light', 'dark', 'system'], true)) {
            throw new \InvalidArgumentException('Invalid settings display theme.');
        }
    }
}
