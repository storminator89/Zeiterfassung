<?php

namespace App\View\ExistingUi;

/** Already-authorized selected audit display text; no queries, permissions or parsing here. */
final readonly class HistoryPage
{
    /**
     * @param list<array{id: int, occurred_at: string, entity: string, record_id: int,
     *     operation: string, actor: string, reason: string,
     *     presentation: array{rows: list<array{field: string, before: string, after: string}>,
     *         before: string, after: string, limited: bool, available: bool}, relation?: ?array}>
     *     $entries Unescaped selected display values, in the reader's order; no raw snapshots.
     * @param array<string, string> $values Native trimmed filter form values.
     * @param list<string> $errors Native filter errors, not exception details.
     * @param array<string, string> $entities Native entity labels.
     * @param ?string $newestUrl Same filters without the cursor, when paging.
     * @param ?string $olderUrl Last returned row ID, only for exactly 100 rows.
     * @param ?string $exportUrl Same existing filters and current cursor, selected page only.
     * @param ?string $correlatedExportUrl Explicit bounded references, not implicit filter expansion.
     * @param bool $showRelations Explicit HTML opt-in, preserved by filter/paging controls.
     * @param ?array $relations Existing validated public collections only, enriched with fixed display labels.
     * @param ?string $relationsUrl Same filters/cursor with only the display opt-in toggled.
     */
    public function __construct(
        public array $entries,
        public array $values,
        public array $errors,
        public array $entities,
        public ?string $newestUrl = null,
        public ?string $olderUrl = null,
        public ?string $exportUrl = null,
        public ?string $correlatedExportUrl = null,
        public bool $showRelations = false,
        /** Public validated correlation collections only; no internal owners/raw snapshots. */
        public ?array $relations = null,
        public ?string $relationsUrl = null,
    ) {}
}
