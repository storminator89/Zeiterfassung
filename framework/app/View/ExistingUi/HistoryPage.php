<?php

namespace App\View\ExistingUi;

/** Already-authorized native audit display text; no queries, permissions or JSON parsing. */
final readonly class HistoryPage
{
    /**
     * @param list<array{id: int, occurred_at: string, entity: string, record_id: int,
     *     operation: string, actor: string, reason: string, before_json: string,
     *     after_json: string}> $entries Unescaped display values, in the reader's order.
     * @param array<string, string> $values Native trimmed filter form values.
     * @param list<string> $errors Native filter errors, not exception details.
     * @param array<string, string> $entities Native entity labels.
     * @param ?string $newestUrl Same filters without the cursor, when paging.
     * @param ?string $olderUrl Last returned row ID, only for exactly 100 rows.
     */
    public function __construct(
        public array $entries,
        public array $values,
        public array $errors,
        public array $entities,
        public ?string $newestUrl = null,
        public ?string $olderUrl = null,
    ) {}
}
