<?php
// One coarse synthetic :memory: flow. No bootstrap, HTTP, account, file DB or
// clock override. The historical start is seeded after its real overlap guard.
require __DIR__.'/../lib/attendance.php';
require __DIR__.'/../lib/practice.php';
require __DIR__.'/../lib/corrections.php';
require __DIR__.'/../lib/home_projection.php';
function overnight_expect(bool $ok,string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
}
function overnight_correction(PDO $db,int $actor,string $role,array $command): void {
    $GLOBALS['audit_actor']='fixture:user:'.$actor;
    $db->exec('BEGIN IMMEDIATE');
    try { correction_apply_locked($db,$actor,$role,$command);$db->exec('COMMIT'); }
    catch (Throwable $e) { $db->exec('ROLLBACK');throw $e; }
}
$db=PDO::connect('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,username TEXT,regelarbeitszeit REAL,ueberstunden REAL,supervisor_id INTEGER);
CREATE TABLE Feiertage(id INTEGER PRIMARY KEY,datum TEXT,name TEXT);
CREATE TABLE pause_settings(id INTEGER PRIMARY KEY,hours_threshold INTEGER,minimum_pause INTEGER);
CREATE TABLE zeiterfassung(id INTEGER PRIMARY KEY AUTOINCREMENT,startzeit TEXT,endzeit TEXT,pause INTEGER,beschreibung TEXT,standort TEXT,user_id INTEGER);
INSERT INTO users VALUES(1,'synthetic-worker',8,0,NULL),(2,'synthetic-reviewer',8,0,NULL);
INSERT INTO pause_settings VALUES(1,6,30),(2,9,45);");
attendance_migrate($db);practice_migrate($db);
$now=time();$today=booking_local_date($now)->setTime(0,0);$day=$today->format('Y-m-d');
$start=$today->modify('-1 day')->setTime(22,0)->getTimestamp();
$GLOBALS['audit_actor']='fixture:user:1';
// Allow this coarse flow on weekends too, without changing an actual model.
att_exec($db,'INSERT INTO work_models(user_id,valid_from,minutes_json,reason,created_by,created_at) VALUES(1,?,?,?,?,?)',[$day,json_encode(array_fill(0,7,480)),'Synthetic all-week fixture model',2,$now]);
practice_absence($db,1,$day,$day,'Urlaub');
$credit=att_one($db,'SELECT * FROM zeiterfassung WHERE user_id=1');
booking_assert_no_overlap($db,1,$start,$start+1);
att_exec($db,'INSERT INTO zeiterfassung(startzeit,pause,beschreibung,standort,user_id) VALUES(?,0,?,?,1)',[attendance_local($start),'','office']);
$record=(int)$db->lastInsertId();
att_exec($db,'INSERT INTO work_sessions(user_id,record_id,started_at) VALUES(1,?,?)',[$record,$start]);
$session=(int)$db->lastInsertId();
att_exec($db,'INSERT INTO break_intervals(session_id,started_at) VALUES(?,?)',[$session,$now-600]);
$before=time();
$response=attendance_transition($db,1,'fixture:1','overnight-end-0001','end',true,$record);
$after=time();
$original=att_one($db,'SELECT * FROM work_sessions WHERE id=?',[$session]);
$break=att_one($db,'SELECT * FROM break_intervals WHERE session_id=?',[$session]);
$ended=(int)$original['ended_at'];
$rows=$db->query('SELECT * FROM zeiterfassung WHERE user_id=1')->fetchAll(PDO::FETCH_ASSOC);
$days=report_days($rows,$invalid,$conflicts);
$defaultRefused=false;
try { report_days($rows); } catch (UnexpectedValueException $e) { $defaultRefused=str_contains($e->getMessage(),'nicht abschließend'); }
$workDay=booking_local_date($start)->format('Y-m-d');
$refusedPeriods=0;
foreach ([[$workDay,$workDay],[$day,$day],[$workDay,$day]] as [$from,$to]) {
    try { report_days_for_period($rows,$from,$to); }
    catch (UnexpectedValueException $error) { $refusedPeriods++; }
}
$unrelatedDays=report_days_for_period($rows,'2000-01-01','2000-01-31');
overnight_expect($response['success']===true&&$response['state']==='absent'&&$ended>=$before&&$ended<=$after
    &&(int)$break['ended_at']===$ended&&isset($response['overlap_warning'])
    &&$response['overlap_record_ids']===[(int)$credit['id']]&&count($rows)===2
    &&$invalid===[]&&count($conflicts)===1&&$defaultRefused&&$refusedPeriods===3
    &&report_period_minutes($unrelatedDays,'2000-01-01','2000-01-31')===0
    &&report_conflicts_in_period($conflicts,$workDay,$workDay)
    &&report_conflicts_in_period($conflicts,$day,$day)
    &&!report_conflicts_in_period($conflicts,'2000-01-01','2000-01-31'),
    'Actual close, immutable warning or scoped unresolved reporting contract failed.');
// The off-page Monday work must still invalidate Tuesday's credit day.
$groups=report_time_record_groups([$credit],$rows,$conflicts);
overnight_expect(count($groups)===1&&$groups[0]['is_unresolved']===true
    &&$groups[0]['total_duration']==='Klärung offen'
    &&$groups[0]['records'][0]['start_label']!==''
    &&$groups[0]['records'][0]['end_label']!=='',
    'Complete-owner conflict evidence was lost at page/day projection.');
// Broad controls: same-user half-open/DST rules and malformed diagnostics stay
// separate; neither a different employee nor an adjacent interval is a conflict.
$dstCredit=['id'=>10,'user_id'=>1,'startzeit'=>'2026-10-25T00:00:00+02:00','endzeit'=>'2026-10-25T03:00:00+01:00','pause'=>0,'absence_kind'=>'Urlaub'];
$adjacent=['id'=>11,'user_id'=>1,'startzeit'=>'2026-10-24T22:00:00+02:00','endzeit'=>'2026-10-25T00:00:00+02:00','pause'=>0];
$other=['id'=>12,'user_id'=>2,'startzeit'=>'2026-10-25T02:00:00+02:00','endzeit'=>'2026-10-25T03:00:00+01:00','pause'=>0];
$bad=['id'=>13,'user_id'=>1,'startzeit'=>'unreadable','endzeit'=>'2026-10-25T03:00:00+01:00','pause'=>0];
report_days([$dstCredit,$adjacent,$other,$bad],$badRows,$controlConflicts);
$badPeriodRefused=false;
try { report_days_for_period([$bad],'2000-01-01','2000-01-31'); }
catch (UnexpectedValueException $error) { $badPeriodRefused=true; }
overnight_expect($controlConflicts===[]&&count($badRows)===1&&$badPeriodRefused,'Boundary/user/data-quality controls changed.');
// Explicit human choice in a fixture, NOT an automatic cancellation policy.
overnight_correction($db,1,'user',['action'=>'request','record_id'=>(string)$credit['id'],'cancel'=>'1','reason'=>'Synthetic explicit credit cancellation choice']);
$request=(int)$db->lastInsertId();
$pendingRows=$db->query('SELECT * FROM zeiterfassung WHERE user_id=1')->fetchAll(PDO::FETCH_ASSOC);
overnight_expect(count(report_absence_conflicts($pendingRows))===1,'A pending request must not resolve the conflict.');
overnight_correction($db,2,'admin',['action'=>'review','id'=>(string)$request,'decision'=>'approved','review_reason'=>'Synthetic separate-person decision']);
$resolved=$db->query('SELECT * FROM zeiterfassung WHERE user_id=1')->fetchAll(PDO::FETCH_ASSOC);
$replay=attendance_transition($db,1,'fixture:1','overnight-end-0001','end',true,$record);
overnight_expect(report_absence_conflicts($resolved)===[]&&count(report_days($resolved))===1
    &&att_one($db,'SELECT * FROM work_sessions WHERE id=?',[$session])===$original
    &&$replay===$response&&(int)$db->query('SELECT COUNT(*) FROM attendance_events')->fetchColumn()===1,
    'Explicit resolution or original-session/cached-replay preservation failed.');
echo json_encode(['runtime'=>PHP_VERSION,'sqlite'=>$db->query('SELECT sqlite_version()')->fetchColumn(),
    'scenario'=>'Synthetic previous-day22 start guard/seed, real-clock confirmed end, unresolved full-context reports, explicit four-eyes correction, original replay',
    'actual_end'=>attendance_local($ended),'source_rows_preserved_before_manual_choice'=>2,
    'overlap_warning_cached'=>true,'affected_days_unresolved'=>true,'unrelated_period_unchanged'=>true,
    'off_page_conflict_retained'=>true,'period_matrix'=>'work-day / absence-day / spanning refused; unrelated period unaffected; malformed data still refused',
    'source_minutes_not_zeroed'=>array_sum($days),
    'manual_resolution_current'=>true,'historical_warning_replay_unchanged'=>true,
    'not_run'=>['historical real-clock start','exact06:00 end','HTTP','auth','browser','real database','device','framework runtime','deployment']],
    JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
