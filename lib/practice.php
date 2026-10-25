<?php
// Database triggers capture every projection change in the same transaction.
// The application never updates or deletes journal rows. DB/server operators remain trusted.
function practice_connection(PDO $db): void {
    $db->sqliteCreateFunction('audit_actor', fn() => $GLOBALS['audit_actor'] ?? ('web:'.($_SESSION['user_id'] ?? 'system')), 0);
    $db->sqliteCreateFunction('audit_reason', fn() => $GLOBALS['audit_reason'] ?? basename($_SERVER['SCRIPT_NAME'] ?? 'migration'), 0);
}
function practice_migrate(PDO $db): void {
    practice_connection($db);
    if (att_one($db,'SELECT version FROM app_migrations WHERE version=2')) { return; }
    $db->exec('BEGIN IMMEDIATE');
    try {
        if (att_one($db,'SELECT version FROM app_migrations WHERE version=2')) { $db->exec('COMMIT'); return; }
        $db->exec("CREATE TABLE audit_log(id INTEGER PRIMARY KEY AUTOINCREMENT, occurred_at INTEGER NOT NULL, actor TEXT NOT NULL, reason TEXT NOT NULL, entity TEXT NOT NULL, record_id INTEGER NOT NULL, user_id INTEGER NOT NULL, operation TEXT NOT NULL, before_json TEXT, after_json TEXT);
CREATE TABLE work_models(id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, valid_from TEXT NOT NULL, minutes_json TEXT NOT NULL, reason TEXT NOT NULL, created_by INTEGER NOT NULL, created_at INTEGER NOT NULL);
CREATE INDEX work_models_effective ON work_models(user_id,valid_from,id);
ALTER TABLE zeiterfassung ADD COLUMN absence_kind TEXT;
ALTER TABLE zeiterfassung ADD COLUMN planned_minutes INTEGER;
ALTER TABLE time_adjustments ADD COLUMN cancel INTEGER NOT NULL DEFAULT 0;");
        $entities=[
            'users'=>['id','username','regelarbeitszeit','ueberstunden'],
            'Feiertage'=>['id','datum','name'],
            'work_sessions'=>['id','user_id','record_id','started_at','ended_at'],
            'break_intervals'=>['id','session_id','started_at','ended_at'],
            'zeiterfassung'=>['id','startzeit','endzeit','pause','beschreibung','standort','user_id','absence_kind','planned_minutes'],
            'time_adjustments'=>['id','user_id','record_id','proposed_start','proposed_end','proposed_pause','reason','status','reviewer_id','reviewed_at','review_reason','original_json','created_at','cancel'],
            'work_models'=>['id','user_id','valid_from','minutes_json','reason','created_by','created_at'],
        ];
        foreach ($entities as $table=>$columns) {
            $json=fn($prefix)=>'json_object('.implode(',',array_map(fn($c)=>"'$c',$prefix.$c",$columns)).')';
            foreach (['INSERT','UPDATE','DELETE'] as $op) {
                $before=$op==='INSERT'?'NULL':$json('OLD');$after=$op==='DELETE'?'NULL':$json('NEW');$row=$op==='DELETE'?'OLD':'NEW';
                $owner=match($table) { 'users'=>"$row.id", 'Feiertage'=>'0', 'break_intervals'=>"(SELECT user_id FROM work_sessions WHERE id=$row.session_id)", default=>"$row.user_id" };
                $db->exec("CREATE TRIGGER audit_{$table}_{$op} AFTER $op ON $table BEGIN INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,before_json,after_json) VALUES(unixepoch(),audit_actor(),audit_reason(),'$table',$row.id,$owner,'$op',$before,$after); END");
            }
            // Existing records are an explicitly labelled baseline, not reconstructed history.
            $owner=match($table) { 'users'=>'t.id', 'Feiertage'=>'0', 'break_intervals'=>'(SELECT user_id FROM work_sessions WHERE id=t.session_id)', default=>'t.user_id' };
            $db->exec("INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,after_json) SELECT unixepoch(),'system','Migration: Vorgeschichte nicht verfügbar','$table',t.id,$owner,'BASELINE',".$json('t')." FROM $table t");
        }
        foreach (['audit_log','attendance_events','work_models'] as $table) {
            foreach (['UPDATE','DELETE'] as $op) { $db->exec("CREATE TRIGGER immutable_{$table}_{$op} BEFORE $op ON $table BEGIN SELECT RAISE(ABORT,'Append-only history'); END"); }
        }
        foreach (['work_sessions','break_intervals'] as $table) {
            $db->exec("CREATE TRIGGER immutable_closed_{$table} BEFORE UPDATE ON $table WHEN OLD.ended_at IS NOT NULL BEGIN SELECT RAISE(ABORT,'Closed original interval'); END");
            $db->exec("CREATE TRIGGER immutable_delete_{$table} BEFORE DELETE ON $table BEGIN SELECT RAISE(ABORT,'Retain original interval'); END");
        }
        $db->exec("CREATE TRIGGER adjustment_no_delete BEFORE DELETE ON time_adjustments BEGIN SELECT RAISE(ABORT,'Retain correction history'); END;
CREATE TRIGGER adjustment_closed BEFORE UPDATE ON time_adjustments WHEN OLD.status <> 'pending' BEGIN SELECT RAISE(ABORT,'Decision is final'); END;");
        foreach ($db->query('SELECT id,regelarbeitszeit FROM users')->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $minutes=max(0,min(1440,(int)round((float)$u['regelarbeitszeit']*60)));
            att_exec($db,'INSERT INTO work_models(user_id,valid_from,minutes_json,reason,created_by,created_at) VALUES(?,?,?,?,0,?)',[$u['id'],'0001-01-01',json_encode([$minutes,$minutes,$minutes,$minutes,$minutes,0,0]),'Migration des bisherigen Tages-Solls; frühere Modelle unbekannt',time()]);
        }
        $db->exec("CREATE TRIGGER initial_work_model AFTER INSERT ON users BEGIN
INSERT INTO work_models(user_id,valid_from,minutes_json,reason,created_by,created_at) VALUES(NEW.id,'0001-01-01',json_array(max(0,min(1440,round(NEW.regelarbeitszeit*60))),max(0,min(1440,round(NEW.regelarbeitszeit*60))),max(0,min(1440,round(NEW.regelarbeitszeit*60))),max(0,min(1440,round(NEW.regelarbeitszeit*60))),max(0,min(1440,round(NEW.regelarbeitszeit*60))),0,0),'Startmodell aus Tages-Soll',0,unixepoch()); END");
        $db->exec('INSERT INTO app_migrations(version) VALUES(2)');
        $db->exec('COMMIT');
    } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
}
function practice_date(string $value): DateTimeImmutable {
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('Europe/Berlin'));
    if (!$date || $date->format('Y-m-d')!==$value) { throw new AttendanceError('Ungültiges Datum.',400); }
    return $date;
}
function planned_minutes(PDO $db,int $user,string $day): int {
    $date=practice_date($day);
    if (att_one($db,'SELECT id FROM Feiertage WHERE datum=?',[$day])) { return 0; }
    $model=att_one($db,'SELECT minutes_json FROM work_models WHERE user_id=? AND valid_from<=? ORDER BY valid_from DESC,id DESC LIMIT 1',[$user,$day]);
    if ($model) { return (int)json_decode($model['minutes_json'],true)[(int)$date->format('N')-1]; }
    $u=att_one($db,'SELECT regelarbeitszeit FROM users WHERE id=?',[$user]);
    return (int)$date->format('N')<=5 ? max(0,min(1440,(int)round((float)($u['regelarbeitszeit']??0)*60))) : 0;
}
function planned_hours(PDO $db,int $user,string $start,string $end): float {
    $sum=0;for($d=practice_date($start);$d<=practice_date($end);$d=$d->modify('+1 day')) { $sum+=planned_minutes($db,$user,$d->format('Y-m-d')); }return $sum/60;
}
function practice_absence(PDO $db,int $user,string $start,string $end,string $kind): array {
    $a=practice_date($start);$b=practice_date($end);
    if ($b<$a||$a->diff($b)->days>366||!in_array($kind,['Urlaub','Krankheit'],true)) { throw new AttendanceError('Zeitraum und Abwesenheitsart prüfen.',400); }
    $db->exec('BEGIN IMMEDIATE');
    try {
        $GLOBALS['audit_reason']='Abwesenheit: '.$kind;$count=0;
        foreach(new DatePeriod($a,new DateInterval('P1D'),$b->modify('+1 day')) as $date) {
            $day=$date->format('Y-m-d');$minutes=planned_minutes($db,$user,$day);if (!$minutes) { continue; }
            // One credit per day. Overlaps, including overnight work, need explicit correction.
            $from=$date->getTimestamp();$to=$date->modify('+1 day')->getTimestamp();
            booking_assert_no_overlap($db,$user,$from,$to);
            // Compatibility projection: an explicitly labelled credit interval, never a punch.
            att_exec($db,'INSERT INTO zeiterfassung(startzeit,endzeit,pause,beschreibung,standort,user_id,absence_kind,planned_minutes) VALUES(?,?,0,?,?,?,?,?)',[attendance_local($from),attendance_local($from+$minutes*60),$kind,'',$user,$kind,$minutes]);$count++;
        }
        $db->exec('COMMIT');return ['success'=>true,'message'=>"$count Abwesenheitstage nach Arbeitszeitmodell erfasst."];
    } catch (Throwable $e) { $db->exec('ROLLBACK');throw $e; }
}
