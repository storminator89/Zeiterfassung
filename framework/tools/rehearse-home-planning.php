<?php

// Coarse synthetic algorithm proof only: no framework bootstrap, file DB,
// migrations, HTTP, Guard, session, credentials or application acceptance.
require_once dirname(__DIR__, 2).'/lib/attendance.php';
require_once dirname(__DIR__, 2).'/lib/home_projection.php';

final class HomePlanningStatement extends PDOStatement
{
    public static int $executions = 0;

    protected function __construct() {}

    public function execute(?array $params = null): bool
    {
        self::$executions++;

        return parent::execute($params);
    }
}

$ensure = static function (bool $condition, string $message): void {
    if (! $condition) { throw new RuntimeException($message); }
};
$refuses = static function (Closure $operation, string $exception) use ($ensure): void {
    try { $operation(); }
    catch (Throwable $error) {
        $ensure($error instanceof $exception, 'Unexpected refusal: '.$error::class);

        return;
    }
    throw new RuntimeException('An invalid snapshot operation was accepted.');
};
$ensure($argc === 1, 'No connection or fixture arguments are accepted.');
$db = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_STATEMENT_CLASS => [HomePlanningStatement::class, []],
]);
$mapping = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
$tables = array_column($mapping['tables'], null, 'name');
foreach (['users', 'Feiertage', 'work_models'] as $name) { $db->exec($tables[$name]['create_sql']); }
$db->exec("INSERT INTO users(id,username,password,email,role,regelarbeitszeit) VALUES
    (1,'Synthetic model owner','not-a-usable-hash','one@example.invalid','user',7.5),
    (2,'Synthetic fallback owner','not-a-usable-hash','two@example.invalid','user',6.258),
    (3,'Synthetic negative fallback','not-a-usable-hash','three@example.invalid','user',-1),
    (4,'Synthetic capped fallback','not-a-usable-hash','four@example.invalid','user',30),
    (5,'Synthetic null fallback','not-a-usable-hash','five@example.invalid','user',NULL)");
$insertModel = $db->prepare('INSERT INTO work_models(user_id,valid_from,minutes_json,reason,created_by,created_at) VALUES(?,?,?,\'Synthetic only\',1,1)');
foreach ([
    [1, '2000-01-01', [480, 480, 480, 480, 360, 0, 0]],
    [1, '2020-01-01', [360, 360, 360, 360, 360, 0, 0]],
    [1, '2020-01-01', [390, 391, 392, 393, 394, 120, 60]], // Higher ID wins.
    [1, '2030-01-01', [10, 20, 30, 40, 50, 60, 70]],
    [2, '2031-01-01', [1, 2, 3, 4, 5, 6, 7]], // No model before this date.
] as [$owner, $from, $minutes]) { $insertModel->execute([$owner, $from, json_encode($minutes)]); }
$db->exec("INSERT INTO Feiertage(datum,name) VALUES
    ('2020-01-01','Synthetic model boundary'),
    ('2026-10-28','Synthetic <holiday>'),
    ('2026-10-31','Synthetic weekend'),
    ('2027-01-01','Synthetic year boundary')");
$source = static function () use ($db): string {
    $rows = [];
    foreach (['users', 'Feiertage', 'work_models'] as $table) {
        $rows[$table] = $db->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }

    return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
};
$before = $source();
$timezone = date_default_timezone_get();
$globals = array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION']));

// The resolver cannot start/commit a transaction, choose another owner, or
// silently answer an invalid or out-of-range date.
$refuses(static fn () => planned_minutes_snapshot($db, 1, '2026-10-01', '2026-10-31'), LogicException::class);
$ensure(! $db->inTransaction(), 'Refusal started a transaction.');
$db->beginTransaction();
$refuses(static fn () => planned_minutes_snapshot($db, 1, '2026-02-30', '2026-10-31'), AttendanceError::class);
$refuses(static fn () => planned_minutes_snapshot($db, 1, '2026-11-01', '2026-10-31'), LogicException::class);
$comparisonCount = 0;
$dates = ['1999-12-31', '2000-01-01', '2000-01-03', '2019-12-31', '2020-01-01', '2020-01-02', '2027-01-01', '2030-01-01', '2031-01-01'];
for ($date = practice_date('2026-01-01'); $date <= practice_date('2026-12-31'); $date = $date->modify('+1 day')) {
    $dates[] = $date->format('Y-m-d');
}
foreach ([1, 2, 3, 4, 5, 999] as $owner) {
    HomePlanningStatement::$executions = 0;
    $planned = planned_minutes_snapshot($db, $owner, '1999-12-31', '2031-01-01');
    $ensure(HomePlanningStatement::$executions === 3, 'Plan snapshot no longer uses three SELECT executions.');
    foreach ($dates as $day) {
        $ensure($planned($day) === planned_minutes($db, $owner, $day), 'Native plan differs for owner/date.');
        $comparisonCount++;
    }
    $refuses(static fn () => $planned('1999-12-30'), LogicException::class);
    $refuses(static fn () => $planned('2031-01-02'), LogicException::class);
    $refuses(static fn () => $planned('2026-02-30'), AttendanceError::class);
    unset($planned);
}
$ensure($db->inTransaction(), 'Resolver ended the caller-owned transaction.');
$db->rollBack();

// Stored credit, multiple shifts, overnight start-day and open-row rules are
// still supplied by the unchanged report_days algorithm, not recalculated plans.
$records = [
    ['id' => 1, 'user_id' => 1, 'startzeit' => '2026-10-26 00:00:00', 'endzeit' => '2026-10-26 04:30:00', 'pause' => 0, 'absence_kind' => 'Urlaub', 'planned_minutes' => 270],
    ['id' => 2, 'user_id' => 1, 'startzeit' => '2026-10-27 08:00:00', 'endzeit' => '2026-10-27 10:00:00', 'pause' => 0],
    ['id' => 3, 'user_id' => 1, 'startzeit' => '2026-10-27 22:00:00', 'endzeit' => '2026-10-28 06:00:00', 'pause' => 30],
    ['id' => 4, 'user_id' => 1, 'startzeit' => '2026-10-28 08:00:00', 'endzeit' => null, 'pause' => 0],
];
$days = report_days($records);
$ensure($days === ['2026-10-26' => 270, '2026-10-27' => 570], 'Stored report-day semantics changed.');
$summaryCases = 0;
foreach ([
    [1, $days, 7.0, '2026-10-28T12:00:00+01:00'],
    [1, ['2030-01-02' => 600, '1999-12-31' => 480], -2.0, '2026-10-28T12:00:00+01:00'],
    [2, ['2026-10-26' => 376], 0.0, '2026-10-28T12:00:00+01:00'],
    [3, [], 0.0, '2026-12-31T23:30:00Z'], // Berlin is in the next year/month.
    [4, [], -1.0, '2026-03-29T01:30:00Z'], // DST transition.
    [5, [], 0.0, '2026-10-25T01:30:00Z'],
] as [$owner, $reportDays, $carryIn, $instant]) {
    $now = new DateTimeImmutable($instant);
    $native = home_summary($db, $owner, $reportDays, $carryIn, $now);
    $ensure(! $db->inTransaction(), 'Native fallback began a transaction.');
    $db->beginTransaction();
    HomePlanningStatement::$executions = 0;
    $optimized = home_summary($db, $owner, $reportDays, $carryIn, $now);
    $ensure(HomePlanningStatement::$executions === 4, 'Home no longer uses four SELECT executions.');
    $ensure($optimized === $native, 'Snapshot Home fields differ from the native fallback.');
    $ensure($db->inTransaction(), 'Home ended the existing snapshot.');
    $db->rollBack();
    $summaryCases++;
}
// SELECT growth, not wall-clock thresholds: no timing claim from this proof.
$scaling = [];
$now = new DateTimeImmutable('2026-10-28T12:00:00+01:00');
foreach ([[30, 1], [365, 1], [3650, 1], [10000, 1], [3650, 2]] as [$size, $owner]) {
    $reportDays = [];
    $date = $now->modify('-'.($size - 1).' days');
    for ($index = 0; $index < $size; $index++) {
        $reportDays[$date->format('Y-m-d')] = $index % 6 === 0 ? 180 : 480;
        $date = $date->modify('+1 day');
    }
    HomePlanningStatement::$executions = 0;
    $native = home_summary($db, $owner, $reportDays, 11.0, $now);
    $nativeQueries = HomePlanningStatement::$executions;
    $db->beginTransaction();
    HomePlanningStatement::$executions = 0;
    $optimized = home_summary($db, $owner, $reportDays, 11.0, $now);
    $optimizedQueries = HomePlanningStatement::$executions;
    $ensure($optimized === $native && $optimizedQueries === 4, 'Home SELECT scaling/output invariant failed.');
    $db->rollBack();
    $scaling[] = ['report_days' => $size, 'owner' => $owner, 'native_select_executions' => $nativeQueries, 'snapshot_select_executions' => $optimizedQueries, 'identical_summary' => true];
}
$ensure($source() === $before && date_default_timezone_get() === $timezone
    && array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION'])) === $globals,
    'Planning changed source rows, timezone or audit/session globals.');

// No static/request cache: a later operation must see later committed data.
$db->exec("INSERT INTO work_models(user_id,valid_from,minutes_json,reason,created_by,created_at)
    VALUES(2,'2026-10-01','[60,60,60,60,60,0,0]','Synthetic later operation',1,1)");
$db->beginTransaction();
$next = home_summary($db, 2, ['2026-10-26' => 376], 0.0, new DateTimeImmutable('2026-10-27T12:00:00+01:00'));
$ensure($next['daily_hours_formatted'] === '01:00' && $next['total_overtime_formatted'] === '05:16', 'A later operation reused stale plans.');
$db->rollBack();
$ensure(! $db->inTransaction(), 'Caller transaction was not released.');
echo json_encode([
    'passed' => true,
    'runtime' => PHP_VERSION,
    'synthetic_memory_only' => true,
    'native_plan_comparisons' => $comparisonCount,
    'identical_home_summary_cases' => $summaryCases,
    'plan_snapshot_select_executions' => 3,
    'home_summary_select_executions' => 4,
    'scaling' => $scaling,
    'groups' => ['model precedence and future/before-first dates', 'owner/fallback rounding and clamps', 'holidays and weekend work', 'stored report semantics', 'empty/future/unsorted reports and Berlin dates', 'caller-owned snapshot/refusals and unchanged source', 'next-operation freshness'],
    'http_ui_runtime_acceptance' => false,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
