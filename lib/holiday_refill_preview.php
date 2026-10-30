<?php
// Definitions only: no writer, fetch, bootstrap, transaction lifecycle or persistent state.
require_once __DIR__.'/holiday_refill.php';

/** Informative same-year prefix comparison only; never write admission or a source attestation. */
function chrono_holiday_refill_preview_snapshot(PDO $db, mixed $year, mixed $region,
    #[SensitiveParameter] mixed $json): array
{
    if (!$db->inTransaction()) { throw new LogicException('An authorized preview read snapshot is required.'); }
    $stage = chrono_holiday_refill_stage($year, $region, $json);
    $prefix = sprintf('%04d', $stage['year']);
    // Keep the writer's selected-year bounds/order/367-row ceiling. Oversized
    // originals can never equal a staged day/name; do not materialize or echo them.
    $statement = $db->prepare("SELECT
        CASE WHEN typeof(datum)='text' AND length(CAST(datum AS BLOB))=10 THEN datum ELSE NULL END AS datum,
        CASE WHEN typeof(name) IN ('text','blob') AND length(CAST(name AS BLOB))<=250 THEN name ELSE NULL END AS name
        FROM Feiertage WHERE datum>=? AND datum<? ORDER BY datum LIMIT 367");
    try { $statement->execute([$prefix.'-', $prefix.'.']);$existing = $statement->fetchAll(PDO::FETCH_ASSOC); }
    finally { $statement->closeCursor(); }
    $matched = []; $extra = 0;
    foreach ($existing as $row) {
        if (!is_string($row['datum']) || !array_key_exists($row['datum'], $stage['rows'])) { $extra++; continue; }
        $matched[$row['datum']] = $row['name'];
    }
    $truncated = count($existing)>366;
    $counts = ['missing'=>0,'unchanged'=>0,'conflict'=>0,'extra_existing'=>$extra,'not_compared'=>0];$rows = [];
    foreach ($stage['rows'] as $day => $name) {
        $status = !array_key_exists($day, $matched) ? ($truncated ? 'not_compared' : 'missing')
            : ($matched[$day] === $name ? 'unchanged' : 'conflict');
        $counts[$status]++;
        // Only canonical staged days/names, never arbitrary existing raw values.
        $rows[] = ['day'=>$day,'name'=>$name,'status'=>$status];
    }
    return ['year'=>$stage['year'],'region'=>'BW','source_sha256'=>$stage['source_sha256'],
        'conditional_omitted'=>$stage['conditional_omitted'],'rows'=>$rows,'counts'=>$counts,
        'existing_scan_limited'=>$truncated,'has_conflicts'=>$counts['conflict']>0 || $extra>0 || $truncated,
        'scope'=>'selected_year_prefix','comparison_only'=>true];
}
