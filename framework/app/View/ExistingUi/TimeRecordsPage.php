<?php

namespace App\View\ExistingUi;

/** Already scoped/prepared native display groups; no SQL, clock or accounting. */
final readonly class TimeRecordsPage
{
    /**
     * @param list<array> $groups Exact report_time_record_groups() output, unescaped.
     * @param array<int|string, array<string, string>> $localTimeFindings Existing category/field pairs for visible own rows only.
     */
    public function __construct(
        public Copy $copy,
        public array $groups,
        public int $page,
        public int $totalPages,
        public bool $csvExport = false,
        public bool $reviewOnly = false,
        public int $totalRecords = 0,
        public array $localTimeFindings = [],
        /** Existing composition capability only; never a new read authority. */
        public bool $historyLinks = false,
    ) {}
}
