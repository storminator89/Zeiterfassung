<?php

// One coarse integration rehearsal; no HTTP, fixture/file/credential arguments.
use App\Auth\LegacyUser;
use App\Chrono\ChronoReads;
use App\LegacyDatabase\AuditContext;
use App\LegacyDatabase\AuditFunctions;
use App\LegacyDatabase\MemoryOnly;
use App\LegacyDatabase\SourceSnapshot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = $app->make('config');
$database = $app->make('db');
$phase = 'inactive boot and named-connection refusal';
$previousConnections = $config->get('database.connections');
$previousSession = $config->get('session.driver');
$previousKey = $config->get('app.key');
$globals = array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION']));
$ensure = static function (bool $condition): void {
    if (! $condition) {
        throw new RuntimeException('Coarse read rehearsal invariant failed.');
    }
};
$refuses = static function (Closure $operation, string $type) use ($ensure): void {
    try {
        $operation();
    } catch (Throwable $error) {
        $ensure($error instanceof $type);
        return;
    }
    throw new RuntimeException('Expected refusal did not occur.');
};

try {
    $ensure($argc === 1 && ! $config->has('database.connections.chrono') && $database->getConnections() === []);
    $reads = $app->make(ChronoReads::class);
    $ensure($database->getConnections() === []);
    $refuses(fn () => $reads->attendanceStatus(), LogicException::class);
    $ensure($database->getConnections() === []);

    // Process-local throwaway framework key; never persisted, logged or reused.
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
    $build($pdo, $mapping, true);
    $app->make(AuditFunctions::class)->register($pdo);
    $app->make(AuditContext::class)->run('fixture:read-rehearsal', 'Synthetic projection setup only', function () use ($pdo): void {
        $pdo->exec("UPDATE users SET supervisor_id=2 WHERE id=1;
            UPDATE users SET role='supervisor' WHERE id=2;
            INSERT INTO users(id,username,password,email,role,regelarbeitszeit,supervisor_id) VALUES
            (3,'Synthetic admin','not-a-usable-password-hash','admin@example.invalid','admin',8,NULL),
            (4,'Synthetic outside','not-a-usable-password-hash','outside@example.invalid','user',8,3);
            INSERT INTO Feiertage(datum,name) VALUES('2026-10-27','Synthetic holiday');
            INSERT INTO work_models(user_id,valid_from,minutes_json,reason,created_by,created_at)
            VALUES(1,'2026-10-26','[360,360,360,360,360,0,0]','Synthetic dated model',3,1);
            INSERT INTO zeiterfassung(id,startzeit,endzeit,pause,beschreibung,standort,user_id,absence_kind,planned_minutes) VALUES
            (44,'2026-10-25T00:30:00+02:00','2026-10-25T04:30:00+01:00',30,'Synthetic DST shift','office',1,NULL,NULL),
            (45,'2026-10-26T00:00:00+01:00','2026-10-26T04:30:00+01:00',0,'Urlaub','',1,'Urlaub',270),
            (46,'2026-10-28T08:00:00+01:00',NULL,0,'Synthetic open shift','office',1,NULL,NULL);
            INSERT INTO work_sessions(user_id,record_id,started_at) VALUES(1,46,1793170800);
            INSERT INTO break_intervals(session_id,started_at) VALUES(last_insert_rowid(),1793174400);");
        foreach ([1, 4] as $user) {
            att_exec($pdo, 'INSERT INTO time_adjustments(user_id,record_id,proposed_start,proposed_end,proposed_pause,reason,status,original_json,created_at,cancel) VALUES(?,NULL,1,61,0,?,?,?,?,0)', [
                $user, 'Synthetic read fixture', $user === 1 ? 'approved' : 'pending', '{"fixture":"retained original"}', 1,
            ]);
        }
    });
    $before = SourceSnapshot::capture($pdo, $mapping);
    $authenticate = static function (?int $identifier, ?string $cachedRole = null) use ($app, $pdo): void {
        $row = $identifier === null ? null : att_one($pdo, 'SELECT * FROM users WHERE id=?', [$identifier]);
        if ($identifier !== null && $row === null) {
            $row = ['id' => $identifier, 'role' => 'admin'];
        }
        if ($cachedRole !== null) {
            $row['role'] = $cachedRole;
        }
        $guard = $app->make('auth')->guard('web');
        $row === null ? $guard->forgetUser() : $guard->setUser((new LegacyUser)->newFromBuilder($row, 'chrono'));
    };
    $authenticate(1);

    $phase = 'own status and Berlin DST/model/absence projection parity';
    $statement = $pdo->query('SELECT id,startzeit,endzeit,pause,absence_kind,planned_minutes FROM zeiterfassung WHERE user_id=1 ORDER BY id DESC LIMIT 30');
    $expectedStatus = ['success' => true, 'records' => $statement->fetchAll(PDO::FETCH_ASSOC),
        'report_finality' => ['all' => false, 'week' => false, 'month' => false, 'year' => false],
    ] + attendance_status($pdo, 1);
    $statement->closeCursor();
    $ensure($reads->attendanceStatus() === $expectedStatus && $expectedStatus['state'] === 'paused');
    $raw = $pdo->query('SELECT * FROM zeiterfassung WHERE user_id=1 ORDER BY startzeit DESC')->fetchAll(PDO::FETCH_ASSOC);
    $ensure($reads->reportRows() === $raw);
    $period = $reads->period('2026-10-23', '2026-10-28');
    $days = report_days($raw);
    $ensure($period['actual_minutes'] === report_period_minutes($days, '2026-10-23', '2026-10-28')
        && $period['planned_hours'] === planned_hours($pdo, 1, '2026-10-23', '2026-10-28')
        && $days['2026-10-25'] === 270 && $days['2026-10-26'] === 270
        && ! isset($days['2026-10-28']) && planned_minutes($pdo, 1, '2026-10-26') === 360
        && planned_minutes($pdo, 1, '2026-10-27') === 0
        && $period['actual_formatted'] === report_format_hours($period['actual_minutes'] / 60));
    $ensure(count($reads->corrections()) === 1 && count($reads->workModels()) === 2);
    $ensure(array_unique(array_column($reads->history(), 'user_id')) === [1]);

    $phase = 'authenticated identity, fresh role, direct-team/admin visibility';
    $refuses(fn () => $reads->period('2026-10-23', '2026-10-28', 4), AuthorizationException::class);
    $authenticate(2, 'admin'); // Stale guard role must not widen current supervisor rights.
    $ensure($reads->period('2026-10-23', '2026-10-28', 1) === $period);
    $refuses(fn () => $reads->period('2026-10-23', '2026-10-28', 4), AuthorizationException::class);
    $ensure(count($reads->corrections()) === 1 && count($reads->workModels()) === 1);
    $ensure(! in_array(4, array_column($reads->history(), 'user_id'), true));
    $authenticate(3);
    $ensure(count($reads->corrections()) === 2 && count($reads->workModels()) === 5);
    $history = $reads->history();
    $ensure(in_array(0, array_column($history, 'user_id'), true) && in_array(4, array_column($history, 'user_id'), true));
    $cursor = $history[0]['id'];
    $ensure($reads->history($cursor)[0]['id'] < $cursor);
    $authenticate(1, 'admin');
    $refuses(fn () => $reads->period('2026-10-23', '2026-10-28', 4), AuthorizationException::class);
    $authenticate(999);
    $refuses(fn () => $reads->attendanceStatus(), AuthenticationException::class);
    $authenticate(null);
    $refuses(fn () => $reads->reportRows(), AuthenticationException::class);

    $phase = 'no database/global mutation or default connection';
    $ensure(SourceSnapshot::capture($pdo, $mapping) === $before);
    $ensure(array_keys($database->getConnections()) === ['chrono']
        && ! $pdo->inTransaction() && $database->connection('chrono')->transactionLevel() === 0);
    $ensure(array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION'])) === $globals);
    echo json_encode([
        'passed' => true,
        'groups' => ['inactive named-connection refusal', 'status and DST/model/absence parity', 'fresh authenticated visibility', 'unchanged source/global state'],
        'synthetic_memory_only' => true,
        'http_ui_runtime_acceptance' => false,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    // Do not output SQL, bindings, account fields, paths, secrets or raw errors.
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
