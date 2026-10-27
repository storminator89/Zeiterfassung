<?php

namespace App\View\ExistingUi;

/** Already-authorized model rows/options and presentation-only form defaults. */
final readonly class WorkModelsPage
{
    public const DAYS = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

    /**
     * @param list<array{username: string, valid_from: string, minutes: array, reason: string}> $models
     * @param list<array{id: int, username: string}> $targetOptions
     */
    public function __construct(
        public array $models,
        public array $targetOptions,
        public string $defaultValidFrom,
        public ?string $error = null,
        public bool $saved = false,
        public bool $enabled = false,
    ) {}
}
