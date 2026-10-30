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
        /** Visible own-row participant flags only; no counterpart/account data. */
        public array $workOverlapFindings = [],
        public bool $storedValuesExport = false,
    ) {}

    public function storedValuesText(string $key): string
    {
        $labels=require dirname(__DIR__,3).'/lang/'.$this->copy->language.'/stored-values-export.php';
        return $labels[$key]??throw new \InvalidArgumentException('Unknown own stored-value export label.');
    }
}
