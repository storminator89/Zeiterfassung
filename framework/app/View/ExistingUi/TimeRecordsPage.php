<?php

namespace App\View\ExistingUi;

/** Already scoped/prepared native display groups; no SQL, clock or accounting. */
final readonly class TimeRecordsPage
{
    /** @param list<array> $groups Exact report_time_record_groups() output, unescaped. */
    public function __construct(
        public Copy $copy,
        public array $groups,
        public int $page,
        public int $totalPages,
    ) {}
}
