<?php

namespace App\View\ExistingUi;

/** Presentation-only copy; never include the constant-defining legacy language files. */
final readonly class Copy
{
    private array $labels;

    public function __construct(public string $language = 'de')
    {
        if (! in_array($language, ['de', 'en', 'zh'], true)) {
            throw new \InvalidArgumentException('Unsupported existing UI language.');
        }

        $this->labels = require dirname(__DIR__, 3).'/lang/'.$language.'/chrono.php';
    }

    public function text(string $key): string
    {
        return $this->labels[$key] ?? throw new \InvalidArgumentException('Unknown existing UI label.');
    }

    public function month(string $number): string
    {
        $key = [
            '01' => 'JANUARY', '02' => 'FEBRUARY', '03' => 'MARCH', '04' => 'APRIL',
            '05' => 'MAY', '06' => 'JUNE', '07' => 'JULY', '08' => 'AUGUST',
            '09' => 'SEPTEMBER', '10' => 'OCTOBER', '11' => 'NOVEMBER', '12' => 'DECEMBER',
        ][$number] ?? throw new \InvalidArgumentException('Unknown existing month.');

        return $this->text('MONTH_'.$key);
    }
}
