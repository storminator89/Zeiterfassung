<?php
/** Disposable CPU/heap rehearsal. No app/bootstrap, database, session or HTTP. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$source = realpath($argv[1] ?? dirname(__DIR__, 2));
$count = filter_var($argv[2] ?? 10000, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20000]]);
$operation = $argv[3] ?? 'days';
if ($source === false || $count === false || !in_array($operation, ['days', 'conflicts', 'period'], true)) {
    fwrite(STDERR, "Usage: php rehearse-report-projection.php [source-root] [1..20000 rows] [days|conflicts|period]\n");
    exit(2);
}
require $source . '/lib/reporting.php';
$rows = [];
$day = new DateTimeImmutable('2000-01-01', new DateTimeZone('Europe/Berlin'));
for ($i = 0; $i < $count; $i++) {
    $date = $day->modify('+' . $i . ' days');
    $absence = $i % 17 === 0;
    $start = $date->setTime($absence ? 0 : 9, 0);
    $rows[] = ['id' => $i + 1, 'user_id' => 7,
        'startzeit' => $start->format('Y-m-d\TH:i:sP'),
        'endzeit' => $start->modify('+8 hours')->format('Y-m-d\TH:i:sP'),
        'pause' => $absence ? 0 : 30, 'beschreibung' => $absence ? 'Urlaub' : 'synthetic work',
        'absence_kind' => $absence ? 'vacation' : null];
}
$original = hash('sha256', serialize($rows));
$timezone = date_default_timezone_get();
$times = $peaks = $hashes = [];
for ($repeat = 0; $repeat < 7; $repeat++) {
    memory_reset_peak_usage();
    $memory = memory_get_usage();
    $started = hrtime(true);
    if ($operation === 'conflicts') {
        $days = report_absence_conflicts($rows); $invalid = $conflicts = [];
    } elseif ($operation === 'period') {
        $days = report_days_for_period($rows, '2000-01-01', '2027-01-01'); $invalid = $conflicts = [];
    } else {
        $days = report_days($rows, $invalid, $conflicts);
    }
    $times[] = (hrtime(true) - $started) / 1e6;
    $peaks[] = memory_get_peak_usage() - $memory;
    $hashes[] = hash('sha256', serialize([$days, $invalid, $conflicts]));
    unset($days, $invalid, $conflicts);
}
if ($original !== hash('sha256', serialize($rows)) || count(array_unique($hashes)) !== 1
    || $timezone !== date_default_timezone_get()) {
    throw new RuntimeException('Projection changed source rows, timezone or repeated results.');
}
sort($times); sort($peaks);
echo json_encode(['runtime' => PHP_VERSION, 'rows' => $count, 'operation' => $operation,
    'synthetic_only' => true, 'SQL_queries_in_operation' => 0,
    'median_ms' => $times[3], 'times_ms' => $times, 'median_additional_peak_bytes' => $peaks[3],
    'result_sha256' => $hashes[0], 'source_rows_and_timezone_preserved' => true,
    'reporting_source_sha256' => hash_file('sha256', $source . '/lib/reporting.php')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
