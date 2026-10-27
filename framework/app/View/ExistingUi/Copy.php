<?php

namespace App\View\ExistingUi;

/** Presentation-only copy; never include the constant-defining legacy language files. */
final readonly class Copy
{
    private array $labels;

    public function __construct(public string $language = 'de')
    {
        if (! in_array($language, ['de', 'en'], true)) {
            throw new \InvalidArgumentException('Unsupported existing UI language.');
        }

        $this->labels = require dirname(__DIR__, 3).'/lang/'.$language.'/chrono.php';
    }

    public function text(string $key): string
    {
        return $this->labels[$key] ?? throw new \InvalidArgumentException('Unknown existing UI label.');
    }
}
