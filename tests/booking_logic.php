<?php
require __DIR__.'/../lib/attendance.php';require __DIR__.'/../lib/practice.php';require_once __DIR__.'/../lib/reporting.php';
$passed=0;
function check(string $name,bool $ok): void { global $passed;if(!$ok){throw new RuntimeException($name);} $passed++;echo "PASS $name\n"; }
function denied(callable $action): bool { try {$action();return false;}catch(AttendanceError $e){return in_array($e->status,[400,409],true);} }
function fixture(): PDO {
    $db=PDO::connect('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,username TEXT,regelarbeitszeit REAL,ueberstunden REAL);
CREATE TABLE Feiertage(id INTEGER PRIMARY KEY,datum TEXT,name TEXT);
CREATE TABLE pause_settings(id INTEGER PRIMARY KEY,hours_threshold INTEGER,minimum_pause INTEGER);
CREATE TABLE zeiterfassung(id INTEGER PRIMARY KEY AUTOINCREMENT,startzeit TEXT,endzeit TEXT,pause INTEGER,beschreibung TEXT,standort TEXT,user_id INTEGER);
INSERT INTO users VALUES(1,'booking-fixture',8,0);
INSERT INTO pause_settings VALUES(1,6,30),(2,9,45);");
    attendance_migrate($db);practice_migrate($db);return $db;
}
function row(string $start,?string $end,int $pause=0): array { return ['startzeit'=>$start,'endzeit'=>$end,'pause'=>$pause]; }
$days=report_days([
    row('2025-12-29T08:00:00+01:00','2025-12-29T16:15:00+01:00',30),
    row('2026-01-02 09:00:00','2026-01-02 13:45:00'),
    row('2025-12-28 09:00:00','2025-12-28 10:00:00'),
    row('2026-01-05 09:00:00','2026-01-05 10:00:00'),
    row('2026-01-01 09:00:00',null),
]);
check('ISO week crosses calendar year',report_week_bounds('2026-01-01')===['2025-12-29','2026-01-04']);
check('Sunday belongs to previous Monday',report_week_bounds('2026-01-04')===['2025-12-29','2026-01-04']);
check('ISO week 53 preserved',report_week_bounds('2021-01-01')===['2020-12-28','2021-01-03']);
check('week sum includes both years only',report_period_minutes($days,'2025-12-29','2026-01-04')===750);
check('monthly minutes never truncate hours',report_period_minutes($days,'2026-01-01','2026-01-31')/60===5.75);
check('open records excluded from completed days',!isset($days['2026-01-01']));
check('same week number in different years differs',report_week(row('2025-01-02 09:00:00',null))!==report_week(row('2026-01-02 09:00:00',null)));
check('offset-free spring DST uses Berlin elapsed time',report_minutes(row('2026-03-29 01:30:00','2026-03-29 03:30:00'))===60);
check('explicit fall DST offsets are unambiguous',report_minutes(row('2026-10-25T02:15:00+02:00','2026-10-25T02:45:00+01:00',30))===60);
check('overnight shifts retain full duration',report_minutes(row('2026-01-02 22:00:00','2026-01-04 00:00:00',60))===1500);
check('format rounding carries into next hour',report_format_hours(1.999)==='02:00');
check('negative format carries into next hour',report_format_hours(-1.999)==='-02:00');
check('zero target cannot divide by zero',report_percentage(4,0)===0.0);
check('productivity uses individual target',report_percentage(3,6)===50.0);
check('invalid negative legacy totals excluded',report_minutes(row('2026-01-01 10:00:00','2026-01-01 09:00:00'))===null);
$db=fixture();$now=time();
att_exec($db,'INSERT INTO zeiterfassung(startzeit,endzeit,pause,user_id) VALUES(?,?,0,1)',[attendance_local($now-60),attendance_local($now+3600)]);
check('start refuses a closed record covering now',denied(fn()=>attendance_transition($db,1,'web:1','overlap-start-0001','start')));
check('failed start leaves no work session',(int)$db->query('SELECT COUNT(*) FROM work_sessions')->fetchColumn()===0);
$db=fixture();
att_exec($db,'INSERT INTO zeiterfassung(startzeit,endzeit,pause,user_id,absence_kind,beschreibung,planned_minutes) VALUES(?,?,0,1,?,?,240)',['2026-10-25T00:00:00+02:00','2026-10-25T03:00:00+01:00','Urlaub','Urlaub']);
check('afternoon work conflicts with morning credit interval',denied(fn()=>booking_assert_no_overlap($db,1,booking_time('2026-10-25T15:00:00+01:00'),booking_time('2026-10-25T16:00:00+01:00'))));
[$from,$to]=booking_span(['startzeit'=>'2026-10-25T00:00:00+02:00','endzeit'=>'2026-10-25T04:00:00+01:00','absence_kind'=>'Urlaub']);
check('fall absence spans full 25-hour calendar day',$to-$from===25*3600);
check('next calendar day remains bookable',!denied(fn()=>booking_assert_no_overlap($db,1,$to,$to+3600)));
check('legacy absence description also occupies whole day',booking_span(['startzeit'=>'2026-10-25 09:00:00','endzeit'=>'2026-10-25 13:00:00','beschreibung'=>'Krankheit'])===[$from,$to]);
check('future work correction refused',denied(fn()=>booking_validate_correction(null,$now,$now+3600,0,$now)));
check('past correction ending now allowed',!denied(fn()=>booking_validate_correction(null,$now-3600,$now,0,$now)));
$absence=['absence_kind'=>'Urlaub'];
check('future absence correction remains allowed',!denied(fn()=>booking_validate_correction($absence,booking_time('2030-01-07 00:00:00'),booking_time('2030-01-07 04:00:00'),0,$now)));
check('absence cannot be turned into afternoon interval',denied(fn()=>booking_validate_correction($absence,booking_time('2030-01-07 13:00:00'),booking_time('2030-01-07 17:00:00'),0,$now)));
check('absence cannot contain invented break',denied(fn()=>booking_validate_correction($absence,booking_time('2030-01-07 00:00:00'),booking_time('2030-01-07 04:00:00'),30,$now)));
// Make a gross 9h15 shift with 30 actual break minutes: net work is only 8h45.
$db=fixture();$started=time()-9*3600-15*60;
att_exec($db,'INSERT INTO zeiterfassung(startzeit,pause,user_id) VALUES(?,0,1)',[attendance_local($started)]);
att_exec($db,'INSERT INTO work_sessions(user_id,record_id,started_at) VALUES(1,1,?)',[$started]);
att_exec($db,'INSERT INTO break_intervals(session_id,started_at,ended_at) VALUES(1,?,?)',[$started+3600,$started+5400]);
$result=attendance_transition($db,1,'web:1','net-break-end-0001','end');
check('break warning uses net work, not gross attendance',!isset($result['pause_warning']));
check('real break duration is preserved',att_one($db,'SELECT pause FROM zeiterfassung WHERE id=1')['pause']===30);
// A backward server clock after a completed pause must not close work before that pause.
$db=fixture();$started=time()-3600;
att_exec($db,'INSERT INTO zeiterfassung(startzeit,pause,user_id) VALUES(?,0,1)',[attendance_local($started)]);
att_exec($db,'INSERT INTO work_sessions(user_id,record_id,started_at) VALUES(1,1,?)',[$started]);
att_exec($db,'INSERT INTO break_intervals(session_id,started_at,ended_at) VALUES(1,?,?)',[time()-300,time()+120]);
check('clock rollback after completed break rejected',denied(fn()=>attendance_transition($db,1,'web:1','clock-back-end-01','end')));
check('clock rollback keeps work open',att_one($db,'SELECT ended_at FROM work_sessions WHERE id=1')['ended_at']===null);
check('clock rollback cannot create a new overlapping break',denied(fn()=>attendance_transition($db,1,'web:1','clock-back-pause1','pause_start')));
check('rejected transitions create no punch event',(int)$db->query('SELECT COUNT(*) FROM attendance_events')->fetchColumn()===0);
echo "$passed booking/reporting assertions passed\n";
