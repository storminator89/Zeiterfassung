<?php
require __DIR__.'/../lib/attendance.php';require __DIR__.'/../lib/practice.php';
function check($name,$ok) { if (!$ok) { throw new RuntimeException($name); } echo "PASS $name\n"; }
$db=PDO::connect('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,username TEXT,regelarbeitszeit REAL,ueberstunden REAL);
CREATE TABLE Feiertage(id INTEGER PRIMARY KEY,datum TEXT,name TEXT);
CREATE TABLE pause_settings(id INTEGER PRIMARY KEY,hours_threshold INTEGER,minimum_pause INTEGER);
CREATE TABLE zeiterfassung(id INTEGER PRIMARY KEY,startzeit TEXT,endzeit TEXT,pause INTEGER,beschreibung TEXT,standort TEXT,user_id INTEGER);
INSERT INTO users VALUES(1,'old part-time',4.5,7);
INSERT INTO zeiterfassung VALUES(42,'2026-10-23 08:00:00','2026-10-23 13:00:00',30,'legacy','office',1);");
attendance_migrate($db);practice_migrate($db);
check('upgrade preserves old times',att_one($db,'SELECT pause FROM zeiterfassung WHERE id=42')['pause']===30);
check('upgrade labels prior history unknown',att_one($db,"SELECT operation FROM audit_log WHERE entity='zeiterfassung' AND record_id=42")['operation']==='BASELINE');
check('old part-time target migrated',planned_minutes($db,1,'2026-10-26')===270);
check('old weekend target zero',planned_minutes($db,1,'2026-10-25')===0);
$count=$db->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();practice_migrate($db);
check('migration idempotent',$count===$db->query('SELECT COUNT(*) FROM audit_log')->fetchColumn());
att_exec($db,'INSERT INTO work_models(user_id,valid_from,minutes_json,reason,created_by,created_at) VALUES(?,?,?,?,1,?)',[1,'2026-10-26','[240,360,0,300,180,120,0]','Teilzeit',time()]);
att_exec($db,'INSERT INTO Feiertage(datum,name) VALUES(?,?)',['2026-10-27','Fixture']);
check('weekly target includes Saturday and excludes holiday',planned_hours($db,1,'2026-10-26','2026-11-01')===14.0);
check('new model does not alter older target',planned_minutes($db,1,'2026-10-23')===270);
check('DST Sunday can be a scheduled day',planned_minutes($db,1,'2026-10-25')===0);
// A raw connection without the application's audit functions cannot silently mutate projections.
$db->createFunction('audit_actor',fn()=>throw new RuntimeException('No audit context'),0);
try { $db->exec('UPDATE zeiterfassung SET pause=0 WHERE id=42');check('audit failure rolls back write',false); } catch(Throwable $e) {}
check('audit failure preserves original row',att_one($db,'SELECT pause FROM zeiterfassung WHERE id=42')['pause']===30);
