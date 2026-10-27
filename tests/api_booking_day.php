<?php

// Coarse CLI-only proof: no bootstrap, HTTP/authentication, server or file DB.
require_once __DIR__.'/../lib/attendance.php';
require_once __DIR__.'/../lib/api_booking_day.php';

function ensure_booking_day(bool $condition, string $group): void
{
    if (!$condition) {
        throw new RuntimeException($group);
    }
}

// Load only the existing native reader definition, never config.php or routing.
$source = file_get_contents(__DIR__.'/../api.php');
ensure_booking_day(preg_match('/^function getTimeEntries\(.*?^\}/ms', $source, $definition) === 1, 'native reader definition');
eval($definition[0]);

$cases = [
    ['2026-10-27T00:30:00+01:00', '2026-10-27'],
    ['2026-10-26T23:30:00Z', '2026-10-27'],
    ['2026-10-27 00:30:00', '2026-10-27'],
    ['2026-10-26T23:30:00+01:00', '2026-10-26'],
    ['2026-07-01T00:30:00+02:00', '2026-07-01'],
    ['2026-06-30T22:30:00Z', '2026-07-01'],
    ['2026-07-01 00:30:00', '2026-07-01'],
    ['2026-03-29T00:30:00+01:00', '2026-03-29'],
    ['2026-03-29T03:30:00+02:00', '2026-03-29'],
    ['2026-10-25T02:30:00+02:00', '2026-10-25'],
    ['2026-10-25T02:30:00+01:00', '2026-10-25'],
    ['2027-01-01T00:30:00+01:00', '2027-01-01'],
    ['2026-12-31T23:30:00+01:00', '2026-12-31'],
    ['2026-12-31T23:30:00Z', '2027-01-01'],
    // Overnight remains on the start day; open entries remain filterable.
    ['2026-10-26T23:30:00+01:00', '2026-10-26', '2026-10-27T03:30:00+01:00'],
    ['2026-10-27T01:15:00+01:00', '2026-10-27', null],
];
$originalTimezone = date_default_timezone_get();
foreach (['UTC', 'America/Los_Angeles'] as $timezone) {
    date_default_timezone_set($timezone);
    foreach ($cases as [$start, $day]) {
        ensure_booking_day(api_booking_day($start) === $day && report_day(['startzeit' => $start]) === $day, 'Berlin/report start-day agreement');
    }
    foreach ([null, '', 'unreadable-fixture-time', '2026-02-30 12:00:00'] as $invalid) {
        ensure_booking_day(api_booking_day($invalid) === null, 'unreadable data has no invented day');
    }
}
date_default_timezone_set($originalTimezone);

$db = PDO::connect('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE zeiterfassung(id INTEGER PRIMARY KEY,user_id INTEGER,startzeit TEXT,endzeit TEXT,pause INTEGER)');
$insert = $db->prepare('INSERT INTO zeiterfassung VALUES(?,?,?,?,0)');
foreach ($cases as $index => $case) {
    $insert->execute([$index + 1, 1, $case[0], array_key_exists(2, $case) ? $case[2] : $case[0]]);
}
$insert->execute([17, 1, 'unreadable-fixture-time', null]);
$insert->execute([18, 1, '2026-02-30 12:00:00', null]);
$insert->execute([19, 1, '', null]);
$insert->execute([20, 2, $cases[0][0], null]);

function native_day_read(PDO $db, array $query): array
{
    $_GET = $query;
    ob_start();
    getTimeEntries($db, 1);
    return json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
}

$before = $db->query('SELECT * FROM zeiterfassung ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach (array_unique(array_column($cases, 1)) as $day) {
    $expected = [];
    foreach ($cases as $index => $case) {
        if ($case[1] === $day) { $expected[] = $index + 1; }
    }
    $actual = native_day_read($db, ['start_date' => $day, 'end_date' => $day, 'user_id' => 2]);
    $ids = array_column($actual['data'], 'id');
    sort($ids);
    ensure_booking_day($actual['success'] === true && $ids === $expected && $actual['meta']['total'] === count($expected), 'native inclusive day filter, own scope and count');
}
$bounds = ['start_date' => '2026-10-27', 'end_date' => '2026-10-27'];
$page = native_day_read($db, $bounds + ['limit' => 1, 'offset' => 1]);
ensure_booking_day(array_column($page['data'], 'id') === [1]
    && $page['meta'] === ['total' => 4, 'filtered' => 1, 'limit' => 1, 'offset' => 1], 'filtered pagination preserves raw ordering and metadata');
$offset = native_day_read($db, $bounds + ['offset' => 1]);
ensure_booking_day(array_column($offset['data'], 'id') === [1, 3, 2]
    && $offset['meta'] === ['total' => 4, 'filtered' => 3, 'limit' => null, 'offset' => 1], 'offset-only remains unbounded after day filtering');
foreach ([['start_date' => '2026-10-27'], ['end_date' => '2026-10-27']] as $query) {
    $expected = 0;
    foreach ($cases as $case) {
        $expected += isset($query['start_date']) ? (int)($case[1] >= '2026-10-27') : (int)($case[1] <= '2026-10-27');
    }
    ensure_booking_day(native_day_read($db, $query)['meta']['total'] === $expected, 'one-sided inclusive filter');
}
$unfiltered = native_day_read($db, []);
ensure_booking_day($unfiltered['meta']['total'] === 19 && count($unfiltered['data']) === 19, 'no-filter reader retains unreadable rows');
ensure_booking_day($before === $db->query('SELECT * FROM zeiterfassung ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'fixture data unchanged');

echo json_encode([
    'passed' => true,
    'groups' => ['Berlin/report start-day agreement across seasons, DST, month/year and process zones', 'native own-day filters, count and pagination, overnight/open and unreadable records'],
    'valid_timestamp_cases' => count($cases),
    'runtime' => PHP_VERSION,
    'sqlite' => $db->query('SELECT sqlite_version()')->fetchColumn(),
    'memory_only' => true,
    'http_auth_ui_real_database_deployment_tested' => false,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
