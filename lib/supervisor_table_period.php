<?php
// Definitions only. The caller must diagnose the complete authorized report first.
require_once __DIR__.'/csv_period.php';

/** Reuse the existing exact raw period/date contract; no second date parser. */
function supervisor_table_period(string $queryString): array
{
    try {
        return ['range' => chrono_csv_export_period_from_query_string($queryString), 'invalid' => false];
    } catch (InvalidArgumentException) {
        return ['range' => null, 'invalid' => true];
    }
}

/** Fixed navigation fields only; malformed filters never silently disappear. */
function supervisor_table_period_query(?array $range, bool $invalid): array
{
    if ($invalid) { return ['period' => 'invalid']; }

    return $range === null ? [] : ['period' => '1', 'from' => $range['from'], 'to' => $range['to']];
}

/** Complete quality-checked rows in; table selection only, never a new total. */
function supervisor_table_period_rows(array $rows, ?array $range, bool $invalid): array
{
    if ($invalid) { return []; }

    return $range === null ? $rows : chrono_csv_export_period_select($rows, $range['from'], $range['to']);
}
