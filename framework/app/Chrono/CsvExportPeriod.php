<?php

namespace App\Chrono;

/** Explicit own-CSV presentation range, not identity, accounting or a query scope. */
final readonly class CsvExportPeriod
{
    private function __construct(public string $from, public string $to) {}

    public static function fromQueryString(string $queryString): ?self
    {
        require_once dirname(__DIR__, 3).'/lib/csv_period.php';
        $period = \chrono_csv_export_period_from_query_string($queryString);

        return $period === null ? null : new self($period['from'], $period['to']);
    }

    /** Called only AFTER the unchanged full-own-snapshot conflict/quality gates. */
    public function select(array $validatedOwnRows): array
    {
        require_once dirname(__DIR__, 3).'/lib/csv_period.php';

        return \chrono_csv_export_period_select($validatedOwnRows, $this->from, $this->to);
    }
}
