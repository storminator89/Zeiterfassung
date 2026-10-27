<?php

namespace App\View\ExistingUi;

/** Already-authorized model rows/options and presentation-only form defaults. */
final readonly class WorkModelsPage
{
    public const DAYS = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

    /**
     * @param list<array{id: int, username: string, valid_from: string, minutes: array, reason: string, effective_id: ?int}> $models
     * @param list<array{id: int, username: string}> $targetOptions
     */
    public function __construct(
        public array $models,
        public array $targetOptions,
        public string $defaultValidFrom,
        public ?string $error = null,
        public bool $saved = false,
        public bool $enabled = false,
        public int $total = 0,
        public int $page = 1,
        public int $pages = 1,
        public int $pageSize = 50,
        public int $selectedModelId = 0,
        public ?array $selectedModel = null,
        public ?array $effectiveModel = null,
        public ?string $selectionError = null,
    ) {}

    /** Presentation URL only; explicit zero clears selection, null preserves it. */
    public function url(int $page, ?int $id = null): string
    {
        $parameters = ['model_page' => $page];
        $id ??= $this->selectedModelId;
        if ($id > 0) {
            $parameters['model_id'] = $id;
        }

        return 'work_models.php?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }
}
