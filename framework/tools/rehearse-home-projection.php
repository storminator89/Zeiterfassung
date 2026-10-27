<?php

// One coarse synthetic projection rehearsal; no HTTP, file DB or credentials.
use App\Auth\LegacyUser;
use App\Chrono\ChronoReads;
use App\LegacyDatabase\AuditContext;
use App\LegacyDatabase\AuditFunctions;
use App\LegacyDatabase\MemoryOnly;
use App\LegacyDatabase\SourceSnapshot;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = $app->make('config');
$database = $app->make('db');
$previousConnections = $config->get('database.connections');
$previousSession = $config->get('session.driver');
$previousKey = $config->get('app.key');
$globals = array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION']));
$timezone = date_default_timezone_get();
$phase = 'inactive named-connection refusal';
$ensure = static function (bool $condition): void {
    if (! $condition) {
        throw new RuntimeException('Coarse home projection invariant failed.');
    }
};

try {
    $ensure($argc === 1 && ! $config->has('database.connections.chrono') && $database->getConnections() === []);
    $reads = $app->make(ChronoReads::class);
    try {
        $reads->homeSummary();
        throw new RuntimeException('An inactive connection was accepted.');
    } catch (LogicException) {
        $ensure($database->getConnections() === []);
    }

    $config->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    $config->set('session.driver', 'array');
    $config->set('database.connections.chrono', [
        'driver' => 'chrono_fixture', 'database' => ':memory:', 'prefix' => '',
        'foreign_key_constraints' => false, 'busy_timeout' => 5000,
        'journal_mode' => null, 'synchronous' => null, 'transaction_mode' => 'DEFERRED',
    ]);
    $pdo = $database->connection('chrono')->getPdo();
    MemoryOnly::connection($pdo);
    $mapping = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
    $build = require dirname(__DIR__).'/database/fixtures/legacy_v2.php';
    $build($pdo, $mapping, false);
    $app->make(AuditFunctions::class)->register($pdo);
    $app->make(AuditContext::class)->run('fixture:home-projection', 'Synthetic projection setup only', function () use ($pdo): void {
        $pdo->exec("INSERT INTO users(id,username,password,email,role,regelarbeitszeit,ueberstunden) VALUES
            (1,'Synthetic member','not-a-usable-password-hash','member@example.invalid','user',8,7),
            (2,'Synthetic other','not-a-usable-password-hash','other@example.invalid','user',8,NULL);
            INSERT INTO Feiertage(datum,name) VALUES('2026-10-27','Synthetic <holiday>');
            INSERT INTO work_models(user_id,valid_from,minutes_json,reason,created_by,created_at)
            VALUES(1,'2026-10-26','[360,360,360,360,360,0,0]','Synthetic dated model',2,1);
            INSERT INTO zeiterfassung(id,startzeit,endzeit,pause,beschreibung,standort,user_id,absence_kind,planned_minutes) VALUES
            (1,'2025-12-31 08:00:00','2025-12-31 17:00:00',0,'Synthetic prior year','office',1,NULL,NULL),
            (2,'2026-10-23 08:00:00','2026-10-23 17:00:00',30,'Synthetic old model','office',1,NULL,NULL),
            (3,'2026-10-26T00:00:00+01:00','2026-10-26T04:30:00+01:00',0,'Urlaub','',1,'Urlaub',270),
            (4,'2026-10-28 08:00:00',NULL,0,'Synthetic open','office',1,NULL,NULL),
            (5,'2026-10-29 09:00:30','2026-10-29 09:00:00',0,'Synthetic invalid interval','office',1,NULL,NULL),
            (6,'2026-11-02 08:00:00','2026-11-02 18:00:00',0,'Synthetic other user','office',2,NULL,NULL);");
        for ($minute = 0; $minute < 11; $minute++) {
            att_exec($pdo, 'INSERT INTO zeiterfassung(startzeit,endzeit,pause,beschreibung,standort,user_id) VALUES(?,?,0,?,?,1)', [
                sprintf('2026-11-01 08:%02d:00', $minute), sprintf('2026-11-01 08:%02d:00', $minute + 1),
                'Synthetic page-local row', 'office',
            ]);
        }
    });
    $before = SourceSnapshot::capture($pdo, $mapping);
    $row = att_one($pdo, 'SELECT * FROM users WHERE id=1');
    $row['role'] = 'admin'; // A stale guard role must not introduce a subject selector.
    $app->make('auth')->guard('web')->setUser((new LegacyUser)->newFromBuilder($row, 'chrono'));

    $phase = 'carry-in, all completed start-days and historical plans';
    $raw = $reads->reportRows();
    $days = report_days($raw);
    $fixed = home_summary($pdo, 1, $days, 7, new DateTimeImmutable('2026-10-27', new DateTimeZone('Europe/Berlin')));
    // +60 prior-year, +30 old-model, -90 stored credit/current model, +11 Sunday.
    // Missing weekdays and the open/negative rows do not debit this native metric.
    $ensure($fixed['working_days_this_month'] === 21
        && abs($fixed['total_overtime_hours'] - (7 + 11 / 60)) < 0.0000001
        && $fixed['total_overtime_formatted'] === '07:11' && $fixed['positive_overtime'] === true
        && $fixed['daily_hours_formatted'] === '00:00'
        && $fixed['current_month'] === '10' && $fixed['current_date'] === '2026-10-27'
        && $fixed['holidays_this_week'] === [['date' => '2026-10-27', 'formatted_date' => '27.10.2026', 'name' => 'Synthetic <holiday>']]
        && $days['2026-10-26'] === 270 && planned_minutes($pdo, 1, '2026-10-26') === 360
        && ! isset($days['2026-10-28'], $days['2026-10-29']));
    $ensure($reads->homeSummary() === home_summary($pdo, 1, $days, 7, new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'))));
    $other = att_one($pdo, 'SELECT * FROM users WHERE id=2');
    $app->make('auth')->guard('web')->setUser((new LegacyUser)->newFromBuilder($other, 'chrono'));
    $ensure($reads->homeSummary()['total_overtime_formatted'] === '02:00'); // NULL carry-in remains zero.

    $phase = 'ten raw rows first, page-local groups and checked invalid totals';
    $first = report_time_record_groups(array_slice($raw, 0, 10));
    $second = report_time_record_groups(array_slice($raw, 10, 10));
    $ensure(count($first) === 1 && $first[0]['work_date'] === '2026-11-01'
        && $first[0]['entry_count'] === 10 && $first[0]['total_duration'] === '00:10'
        && $second[0]['work_date'] === '2026-11-01' && $second[0]['entry_count'] === 1
        && $second[0]['total_duration'] === '00:01'
        && array_column($first[0]['records'], 'id') === array_column(array_slice($raw, 0, 10), 'id'));
    $byDay = array_column($second, null, 'work_date');
    $ensure($byDay['2026-10-29']['total_duration'] === 'Ungültig'
        && $byDay['2026-10-29']['total_pause'] === null && $byDay['2026-10-29']['invalid_count'] === 1
        && $byDay['2026-10-29']['records'][0]['duration_label'] === 'Ungültig'
        && $byDay['2026-10-28']['entry_count'] === 1 && $byDay['2026-10-28']['total_duration'] === '00:00'
        && $byDay['2026-10-28']['records'][0]['end_label'] === 'Läuft'
        && $byDay['2026-10-26']['records'][0]['duration_label'] === '270 Sollminuten'
        && $byDay['2026-10-23']['total_duration'] === '08:30' && $byDay['2026-10-23']['total_pause'] === 30);

    $phase = 'unchanged snapshot, globals and timezone';
    $ensure(SourceSnapshot::capture($pdo, $mapping) === $before
        && array_keys($database->getConnections()) === ['chrono']
        && ! $pdo->inTransaction() && $database->connection('chrono')->transactionLevel() === 0
        && date_default_timezone_get() === $timezone
        && array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION'])) === $globals);
    echo json_encode([
        'passed' => true,
        'groups' => ['inactive named-connection refusal', 'native carry-in/completed/model/credit projection', 'raw pagination and table grouping', 'unchanged source/global state'],
        'synthetic_memory_only' => true, 'http_ui_runtime_acceptance' => false,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$phase.' ('.$error::class.").\n");
    exit(1);
} finally {
    $app->make('auth')->forgetGuards();
    $database->purge('chrono');
    $config->set('database.connections', $previousConnections);
    $config->set('session.driver', $previousSession);
    $config->set('app.key', $previousKey);
    $app->forgetInstance('encrypter');
}
