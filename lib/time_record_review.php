<?php
// Definitions only: existing diagnostics over complete, already-authorized rows.
require_once __DIR__.'/reporting.php';
require_once __DIR__.'/legacy_timestamp_assessment.php';

/** Diagnostic categories only; never a normalized date, offset or chosen instant. */
function time_record_local_time_findings(array $row): array
{
    $findings = [];
    foreach (['startzeit', 'endzeit'] as $column) {
        $value = $row[$column] ?? null;
        if ($column === 'endzeit' && ($value === null || $value === '')) { continue; }
        $category = legacy_timestamp_assessment($value)['category'];
        if (in_array($category, ['ambiguous_berlin_local_time', 'nonexistent_berlin_local_time'], true)) {
            $findings[$column] = $category;
        }
    }

    return $findings;
}

/** Preserve caller order and original rows. Not a complete quality/availability scan. */
function time_record_review_rows(array $authorizedRows, array $absenceConflicts): array
{
    $lookup = report_conflict_lookup($absenceConflicts);
    $selected = [];
    foreach ($authorizedRows as $row) {
        if (report_record_calculation($row)['status'] === 'invalid'
            || isset($lookup[report_conflict_key($row)])
            || time_record_local_time_findings($row) !== []) {
            $selected[] = $row;
        }
    }
    return $selected;
}
