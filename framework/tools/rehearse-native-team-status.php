<?php

// Coarse synthetic algorithm/presentation proof only, not a native request or
// Laravel/HTTP/browser acceptance. No bootstrap, migrations, Guard or real data.
require_once dirname(__DIR__, 2).'/lib/team_status.php';
$ensure = static function (bool $condition, string $message): void {
    if (! $condition) { throw new RuntimeException($message); }
};
$refuses = static function (Closure $operation, string $class, ?int $status = null) use ($ensure): void {
    try { $operation(); }
    catch (Throwable $error) {
        $ensure($error instanceof $class && ($status === null || ($error instanceof AttendanceError && $error->status === $status)), 'Unexpected refusal.');

        return;
    }
    throw new RuntimeException('Expected refusal did not occur.');
};
$ensure($argc === 1, 'No fixture or connection arguments are accepted.');
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$mapping = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
$tables = array_column($mapping['tables'], null, 'name');
foreach (['users', 'zeiterfassung', 'work_sessions', 'break_intervals'] as $name) { $db->exec($tables[$name]['create_sql']); }
// No migration/bootstrap: synthetic missing V2 columns are not used by these reads.
$db->exec("INSERT INTO users(id,username,password,email,role,supervisor_id) VALUES
    (1,'Synthetic admin','not-a-usable-hash','a@example.invalid','admin',NULL),
    (2,'Synthetic supervisor','not-a-usable-hash','s@example.invalid','supervisor',NULL),
    (3,'Synthetic other supervisor','not-a-usable-hash','o@example.invalid','supervisor',NULL),
    (4,'<img src=x onerror=synthetic>','not-a-usable-hash','4@example.invalid','user',2),
    (5,'Paused','not-a-usable-hash','5@example.invalid','user',2),
    (6,'No history','not-a-usable-hash','6@example.invalid','user',2),
    (7,'Ambiguous sessions','not-a-usable-hash','7@example.invalid','user',2),
    (8,'Unreadable current start','not-a-usable-hash','8@example.invalid','user',2),
    (9,'Legacy open record','not-a-usable-hash','9@example.invalid','user',2),
    (10,'Empty end marker','not-a-usable-hash','10@example.invalid','user',2),
    (11,'Future current start','not-a-usable-hash','11@example.invalid','user',2),
    (12,'Dangling pause','not-a-usable-hash','12@example.invalid','user',2),
    (13,'Other team','not-a-usable-hash','13@example.invalid','user',3),
    (14,'Grandchild','not-a-usable-hash','14@example.invalid','user',4),
    (15,'Unassigned','not-a-usable-hash','15@example.invalid','user',NULL),
    (16,'Bad history only','not-a-usable-hash','16@example.invalid','user',2),
    (17,'Mismatched session','not-a-usable-hash','17@example.invalid','user',2),
    (18,'Future pause','not-a-usable-hash','18@example.invalid','user',2)");
$loaded = new DateTimeImmutable('2026-10-25T01:30:00Z');
$epoch = $loaded->getTimestamp();
$record = $db->prepare('INSERT INTO zeiterfassung(id,user_id,startzeit,endzeit,pause,standort,beschreibung) VALUES(?,?,?,?,0,\'Synthetic private location\',\'Synthetic private note\')');
$session = $db->prepare('INSERT INTO work_sessions(id,user_id,record_id,started_at,ended_at) VALUES(?,?,?,?,?)');
foreach ([4, 5, 7, 8, 9, 10, 11, 12, 17, 18] as $owner) {
    $start = $owner === 8 ? 'tomorrow' : attendance_local($owner === 11 ? $epoch + 60 : $epoch - 3600);
    $end = $owner === 10 ? '' : ($owner === 12 ? attendance_local($epoch - 1) : null);
    $record->execute([$owner, $owner, $start, $end]);
    if (in_array($owner, [4, 5, 7, 12, 17, 18], true)) {
        $session->execute([$owner, $owner, $owner, $owner === 17 ? $epoch - 3599 : $epoch - 3600, $owner === 12 ? $epoch - 1 : null]);
    }
}
$record->execute([70, 7, attendance_local($epoch - 3600), null]);
$session->execute([70, 7, 70, $epoch - 3600, null]);
$record->execute([160, 16, 'unreadable historical source', 'unreadable historical end']);
$pause = $db->prepare('INSERT INTO break_intervals(id,session_id,started_at,ended_at) VALUES(?,?,?,NULL)');
foreach ([[5, 5, $epoch - 100], [12, 12, $epoch - 200], [18, 18, $epoch + 60]] as $row) { $pause->execute($row); }
$source = static function () use ($db): string {
    $rows = [];
    foreach (['users', 'zeiterfassung', 'work_sessions', 'break_intervals'] as $table) {
        $rows[$table] = $db->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }

    return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
};
$before = $source();
$timezone = date_default_timezone_get();
$globals = array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION']));
$refuses(static fn () => chrono_team_status_snapshot($db, 2, 'supervisor', $loaded), LogicException::class);
$db->beginTransaction();
$refuses(static fn () => chrono_team_status_snapshot($db, 4, 'user', $loaded), AttendanceError::class, 403);
$refuses(static fn () => chrono_native_team_status($db, 2, hash('sha256', 'not-a-usable-hash')), LogicException::class);
$ensure($db->inTransaction(), 'Idle-boundary refusal ended another caller\'s transaction.');
$teamStatus = chrono_team_status_snapshot($db, 2, 'supervisor', $loaded);
$expected = [
    '<img src=x onerror=synthetic>' => 'working', 'Paused' => 'paused', 'No history' => 'absent',
    'Ambiguous sessions' => 'unknown', 'Unreadable current start' => 'unknown',
    'Legacy open record' => 'working', 'Empty end marker' => 'unknown',
    'Future current start' => 'unknown', 'Dangling pause' => 'unknown',
    'Bad history only' => 'absent', 'Mismatched session' => 'unknown', 'Future pause' => 'unknown',
];
$actual = array_column($teamStatus['people'], 'state', 'username');
ksort($actual);
ksort($expected);
$ensure($actual === $expected, 'Current direct-team states differ.');
$ensure($teamStatus['loaded_at'] === '2026-10-25T02:30:00+01:00', 'One Berlin/DST instant was not retained.');
foreach ($teamStatus['people'] as $person) { $ensure(array_keys($person) === ['username', 'state'], 'Private detail escaped into a row.'); }
$ensure(count(chrono_team_status_snapshot($db, 1, 'admin', $loaded)['people']) === 18, 'Admin roster differs.');
$ensure(array_column(chrono_team_status_snapshot($db, 3, 'supervisor', $loaded)['people'], 'username') === ['Other team'], 'Other supervisor scope differs.');
$ensure(chrono_team_status_snapshot($db, 15, 'supervisor', $loaded)['people'] === [], 'Empty direct team was not empty.');
$ensure($db->inTransaction(), 'Shared resolver ended the caller transaction.');
$db->rollBack();

// Native boundary rechecks existing role/hash version in its own read snapshot.
$low = time();
$currentRole = 'untrusted stale Admin value';
$native = chrono_native_team_status($db, 2, hash('sha256', 'not-a-usable-hash'), $currentRole);
$high = time();
$ensure(array_column($native['people'], 'username') === array_column($teamStatus['people'], 'username')
    && (new DateTimeImmutable($native['loaded_at']))->getTimestamp() >= $low
    && (new DateTimeImmutable($native['loaded_at']))->getTimestamp() <= $high && $currentRole === 'supervisor' && ! $db->inTransaction(), 'Native identity/clock/snapshot differs.');
foreach ([[2, '', 401], [2, hash('sha256', 'stale synthetic version'), 401], [999, hash('sha256', 'not-a-usable-hash'), 401], [4, hash('sha256', 'not-a-usable-hash'), 403]] as [$actor, $version, $status]) {
    $currentRole = 'admin';
    $refuses(static function () use ($db, $actor, $version, &$currentRole): void { chrono_native_team_status($db, $actor, $version, $currentRole); }, AttendanceError::class, $status);
    $ensure($currentRole === null, 'Native refusal retained a stale current role.');
    $ensure(! $db->inTransaction(), 'Native refusal leaked a transaction.');
}
$ensure($source() === $before && date_default_timezone_get() === $timezone
    && array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION'])) === $globals,
    'Read changed source, timezone or session/audit globals.');
$db->exec('UPDATE users SET supervisor_id=3 WHERE id=6');
$ensure(! in_array('No history', array_column(chrono_native_team_status($db, 2, hash('sha256', 'not-a-usable-hash'))['people'], 'username'), true), 'A later snapshot kept a reassigned member.');
$db->exec("UPDATE users SET role='user' WHERE id=2");
$currentRole = 'admin';
$refuses(static function () use ($db, &$currentRole): void { chrono_native_team_status($db, 2, hash('sha256', 'not-a-usable-hash'), $currentRole); }, AttendanceError::class, 403);
$ensure($currentRole === null, 'Demotion retained a stale role.');
// A now-demoted Admin can neither keep its stale Admin context nor all users.
$db->exec("UPDATE users SET role='supervisor' WHERE id=1");
$currentRole = 'admin';
$demoted = chrono_native_team_status($db, 1, hash('sha256', 'not-a-usable-hash'), $currentRole);
$ensure($currentRole === 'supervisor' && $demoted['people'] === [] && array_keys($demoted) === ['loaded_at', 'people'], 'Admin demotion widened scope or leaked internal role context.');
$db->exec("UPDATE users SET role='admin' WHERE id=1");
$db->exec('DROP TABLE break_intervals');
$currentRole = null;
$refuses(static function () use ($db, &$currentRole): void { chrono_native_team_status($db, 1, hash('sha256', 'not-a-usable-hash'), $currentRole); }, PDOException::class);
$ensure($currentRole === 'admin', 'A later status query error erased the freshly checked role context.');
$ensure(! $db->inTransaction(), 'Native SQL error leaked its transaction.');

// Actual new native card fragment, with invented DTO and definition-only labels.
// Never evaluate header/config/session/bootstrap or the native request handler.
require dirname(__DIR__, 2).'/languages/de.php';
function h($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$sourceText = file_get_contents(dirname(__DIR__, 2).'/supervisor.php');
$start = strpos($sourceText, '                <?php if ($teamStatusUnavailable) : ?>');
$end = strpos($sourceText, '                <?php if (!$reportAvailable) : ?>', $start);
$ensure($start !== false && $end !== false, 'Native card fragment missing.');
$fragment = substr($sourceText, $start, $end - $start);
$render = static function (?array $teamStatus, bool $teamStatusUnavailable) use ($fragment): string {
    ob_start();
    try { eval('?>'.$fragment); return ob_get_contents(); }
    finally { ob_end_clean(); }
};
$html = $render($teamStatus, false);
$ensure(str_contains($html, '&lt;img src=x onerror=synthetic&gt;') && ! str_contains($html, '<img')
    && ! str_contains($html, 'Synthetic private') && str_contains($html, TEAM_STATUS_WORKING)
    && str_contains($html, TEAM_STATUS_PAUSED) && str_contains($html, TEAM_STATUS_UNKNOWN), 'Card escaping/status minimization failed.');
$ensure(str_contains($render(['loaded_at' => $teamStatus['loaded_at'], 'people' => []], false), TEAM_STATUS_EMPTY), 'Empty card missing.');
$errorCard = $render(null, true);
$ensure(str_contains($errorCard, TEAM_STATUS_UNAVAILABLE) && ! str_contains($errorCard, '<table'), 'Error was shown as empty/absent team.');
$ensure(! str_contains($fragment, 'id="timeRecordsBody"') && ! str_contains($fragment, 'filter-badge') && ! str_contains($fragment, 'id="overtimeCards"'), 'Card conflicts with historical controls.');
echo json_encode([
    'passed' => true, 'runtime' => PHP_VERSION, 'sqlite' => $db->getAttribute(PDO::ATTR_SERVER_VERSION),
    'synthetic_memory_only' => true, 'expected_team_people' => count($expected),
    'groups' => ['current direct-team/Admin scope and no implicit own/grandchild rows', 'canonical working/paused/absent and conservative unknown', 'one Berlin/DST instant and minimal DTO', 'native current role/version/snapshot refusals', 'unchanged source/globals and next-request reassignment', 'actual new native card escaping/empty/error rendering'],
    'native_request_laravel_http_browser_acceptance' => false,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
