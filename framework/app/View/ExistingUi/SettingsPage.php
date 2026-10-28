<?php

namespace App\View\ExistingUi;

/** Own display/session preference values only; no model, query or account mutation. */
final readonly class SettingsPage
{
    private array $passwordLabels;

    public function __construct(
        public string $themeMode,
        public string|int|float|null $regularHours,
        public string|int|float|null $carryInHours,
        public ?string $error = null,
        public bool $saved = false,
        public bool $passwordAvailable = false,
        public string $language = 'de',
    ) {
        if (! in_array($language, ['de', 'en', 'zh'], true)) { throw new \InvalidArgumentException('Unsupported settings language.'); }
        $this->passwordLabels = require dirname(__DIR__, 3).'/lang/'.$language.'/settings.php';
        if (! in_array($themeMode, ['light', 'dark', 'system'], true)) {
            throw new \InvalidArgumentException('Invalid settings display theme.');
        }
    }
    public function passwordText(string $key): string
    {
        return $this->passwordLabels[$key] ?? throw new \InvalidArgumentException('Unknown settings password label.');
    }
}
