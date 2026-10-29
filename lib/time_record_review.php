<?php
// Definitions only: existing diagnostics over complete, already-authorized rows.
require_once __DIR__.'/reporting.php';

/** Preserve caller order and original rows. Not a complete quality/availability scan. */
function time_record_review_rows(array $authorizedRows, array $absenceConflicts): array
{
    $lookup = report_conflict_lookup($absenceConflicts);
    $selected = [];
    foreach ($authorizedRows as $row) {
        if (report_record_calculation($row)['status'] === 'invalid'
            || isset($lookup[report_conflict_key($row)])) {
            $selected[] = $row;
        }
    }
    return $selected;
}
