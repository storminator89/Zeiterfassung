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
        if (in_array($category, ['ambiguous_berlin_local_time', 'nonexistent_berlin_local_time'], true)
            || ($category === 'unsupported_timestamp_shape' && report_stored_epoch($value) !== null)) {
            // Runtime-readable is not the same as supported by the strict legacy diagnostic.
            // Unreadable values retain the existing invalid-row hint, not a duplicate format hint.
            $findings[$column] = $category;
        }
    }

    return $findings;
}

/**
 * Read-only participant lookup, not a pair export or changed report calculation.
 * Complete already-authorized current rows are required before date/page filtering.
 * Only supported, unambiguous completed positive gross work spans participate.
 */
function time_record_work_overlap_lookup(array $authorizedRows): array
{
    $byOwner = [];
    foreach ($authorizedRows as $row) {
        if (booking_is_absence($row)) { continue; }
        foreach (['startzeit', 'endzeit'] as $column) {
            if (legacy_timestamp_assessment($row[$column] ?? null)['category'] !== null) { continue 2; }
        }
        $span = report_record_calculation($row);
        if ($span['status'] !== 'completed' || $span['end'] <= $span['start']) { continue; }
        $owner = filter_var($row['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($owner === false || $id === false) { continue; }
        $byOwner[$owner][] = [$span['start'], $span['end'], report_conflict_key($row)];
    }
    $lookup = [];
    foreach ($byOwner as $spans) {
        usort($spans, static fn(array $a, array $b): int => ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]) ?: strcmp($a[2], $b[2]));
        $furthest = null;
        foreach ($spans as $span) {
            // Keeping the furthest end is enough to mark all participants:
            // a non-furthest prior span was marked when it entered the cluster.
            if ($furthest !== null && $span[0] < $furthest[1]) {
                $lookup[$span[2]] = $lookup[$furthest[2]] = true;
            }
            if ($furthest === null || $span[1] > $furthest[1]) { $furthest = $span; }
        }
    }
    return $lookup;
}

/** Preserve caller order and original rows. Not a complete quality/availability scan. */
function time_record_review_rows(array $authorizedRows, array $absenceConflicts, ?array $workOverlapLookup = null): array
{
    $lookup = report_conflict_lookup($absenceConflicts);
    $workOverlapLookup ??= time_record_work_overlap_lookup($authorizedRows);
    $selected = [];
    foreach ($authorizedRows as $row) {
        if (report_record_calculation($row)['status'] === 'invalid'
            || isset($lookup[report_conflict_key($row)])
            || isset($workOverlapLookup[report_conflict_key($row)])
            || time_record_local_time_findings($row) !== []) {
            $selected[] = $row;
        }
    }
    return $selected;
}
