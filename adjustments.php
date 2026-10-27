<?php
require_once __DIR__.'/config.php'; require_once __DIR__.'/lib/app_ui.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user=(int)$_SESSION['user_id'];$role=$_SESSION['role'];$error='';
function correction_time(string $text): int {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?[+-]\d{2}:\d{2}$/D',$text)) { throw new AttendanceError('Zeit mit Zeitzone angeben, z. B. 2026-10-25T09:00+01:00.',400); }
    try { $d=new DateTimeImmutable($text); } catch (Throwable $e) { throw new AttendanceError('Ungültige Zeit.',400); }
    $errors=DateTimeImmutable::getLastErrors();if ($errors && ($errors['warning_count']||$errors['error_count'])) { throw new AttendanceError('Ungültiges Datum.',400); }
    return $d->getTimestamp();
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $conn->exec('BEGIN IMMEDIATE');
    try {
        if (($_POST['action']??'')==='request') {
            $record=(int)($_POST['record_id']??0); $r=$record?att_one($conn,'SELECT * FROM zeiterfassung WHERE id=? AND user_id=?',[$record,$user]):null;
            if ($record && !$r) { throw new AttendanceError('Nur eigene Arbeitszeiten korrigieren.'); }
            if ($record && att_one($conn,'SELECT id FROM work_sessions WHERE record_id=? AND ended_at IS NULL',[$record])) { throw new AttendanceError('Laufende Arbeitszeit zuerst ausstempeln.'); }
            $cancel=($_POST['cancel']??'')==='1';
            if ($cancel && !$r) { throw new AttendanceError('Stornierung erfordert eine bestehende Buchung.'); }
            $start=$cancel?strtotime($r['startzeit']):correction_time((string)($_POST['start']??''));$end=$cancel?max($start+1,strtotime($r['endzeit']??'now')):correction_time((string)($_POST['end']??''));$pause=$cancel?0:filter_var($_POST['pause']??'',FILTER_VALIDATE_INT);$reason=trim((string)($_POST['reason']??''));
            if ((!$cancel&&($end<=$start||$end-$start>7*86400||$pause===false||$pause<0||$pause>floor(($end-$start)/60)))||strlen($reason)<5||strlen($reason)>1000) { throw new AttendanceError('Zeitraum, Pausen und Begründung prüfen.',400); }
            if (!$cancel) { booking_validate_correction($r,$start,$end,$pause,time()); }
            $GLOBALS['audit_reason']='Korrekturantrag: '.$reason;
            att_exec($conn,'INSERT INTO time_adjustments(user_id,record_id,proposed_start,proposed_end,proposed_pause,reason,original_json,created_at,cancel) VALUES(?,?,?,?,?,?,?,?,?)',[$user,$record?:null,$start,$end,$pause,$reason,$r?json_encode($r):null,time(),(int)$cancel]);
        } elseif (($_POST['action']??'')==='review') {
            $a=att_one($conn,'SELECT * FROM time_adjustments WHERE id=? AND status=?',[(int)($_POST['id']??0),'pending']);
            if (!$a || (int)$a['user_id']===$user || !($role==='admin'||($role==='supervisor'&&att_one($conn,'SELECT id FROM users WHERE id=? AND supervisor_id=?',[$a['user_id'],$user])))) { throw new AttendanceError('Keine Freigabeberechtigung.',403); }
            if (!att_one($conn,'SELECT id FROM users WHERE id=?',[$a['user_id']])) { throw new AttendanceError('Konto existiert nicht mehr.'); }
            $decision=$_POST['decision']??'';$reason=trim((string)($_POST['review_reason']??''));
            if (!in_array($decision,['approved','rejected'],true)||strlen($reason)<3||strlen($reason)>1000) { throw new AttendanceError('Entscheidung und Begründung erforderlich.',400); }
            $GLOBALS['audit_reason']='Korrektur #'.$a['id'].': '.$reason;
            if ($decision==='approved') {
                $old=$a['record_id']?att_one($conn,'SELECT * FROM zeiterfassung WHERE id=? AND user_id=?',[$a['record_id'],$a['user_id']]):null;
                if ($a['record_id'] && (!$old || json_encode($old)!==$a['original_json'])) { throw new AttendanceError('Datensatz wurde inzwischen geändert. Neuen Antrag stellen.'); }
                if ($old && att_one($conn,'SELECT id FROM work_sessions WHERE record_id=? AND ended_at IS NULL',[$a['record_id']])) { throw new AttendanceError('Laufende Arbeitszeit zuerst ausstempeln.'); }
                if (!$a['cancel']) {
                    booking_validate_correction($old,(int)$a['proposed_start'],(int)$a['proposed_end'],(int)$a['proposed_pause'],time());
                    $proposal=['startzeit'=>attendance_local((int)$a['proposed_start']),'endzeit'=>attendance_local((int)$a['proposed_end']),'absence_kind'=>$old['absence_kind']??null,'beschreibung'=>$old['beschreibung']??''];
                    [$from,$to]=booking_span($proposal);
                    booking_assert_no_overlap($conn,(int)$a['user_id'],$from,$to,(int)($a['record_id']??0));
                }
                if ($a['cancel']) {
                    att_exec($conn,'DELETE FROM zeiterfassung WHERE id=?',[$a['record_id']]);
                    att_exec($conn,'UPDATE break_intervals SET ended_at=? WHERE ended_at IS NULL AND session_id IN (SELECT id FROM work_sessions WHERE record_id=?)',[time(),$a['record_id']]);
                    att_exec($conn,'UPDATE work_sessions SET ended_at=? WHERE record_id=? AND ended_at IS NULL',[time(),$a['record_id']]);
                } elseif ($old) {
                    att_exec($conn,'UPDATE zeiterfassung SET startzeit=?,endzeit=?,pause=?,planned_minutes=? WHERE id=?',[attendance_local((int)$a['proposed_start']),attendance_local((int)$a['proposed_end']),$a['proposed_pause'],$old['absence_kind']?(int)floor(($a['proposed_end']-$a['proposed_start'])/60)-$a['proposed_pause']:null,$a['record_id']]);
                    att_exec($conn,'UPDATE break_intervals SET ended_at=? WHERE ended_at IS NULL AND session_id IN (SELECT id FROM work_sessions WHERE record_id=?)',[$a['proposed_end'],$a['record_id']]);
                    att_exec($conn,'UPDATE work_sessions SET ended_at=? WHERE record_id=? AND ended_at IS NULL',[$a['proposed_end'],$a['record_id']]);
                    if (!att_one($conn,'SELECT id FROM work_sessions WHERE record_id=?',[$a['record_id']])) { att_exec($conn,'INSERT INTO work_sessions(user_id,record_id,started_at,ended_at) VALUES(?,?,?,?)',[$a['user_id'],$a['record_id'],$a['proposed_start'],$a['proposed_end']]); }
                } else {
                    att_exec($conn,'INSERT INTO zeiterfassung(startzeit,endzeit,pause,beschreibung,standort,user_id) VALUES(?,?,?,?,?,?)',[attendance_local((int)$a['proposed_start']),attendance_local((int)$a['proposed_end']),$a['proposed_pause'],'Korrektur freigegeben','office',$a['user_id']]);
                    $id=(int)$conn->lastInsertId();
                    att_exec($conn,'INSERT INTO work_sessions(user_id,record_id,started_at,ended_at) VALUES(?,?,?,?)',[$a['user_id'],$id,$a['proposed_start'],$a['proposed_end']]);
                    att_exec($conn,'UPDATE time_adjustments SET record_id=? WHERE id=?',[$id,$a['id']]);
                }
            }
            att_exec($conn,'UPDATE time_adjustments SET status=?,reviewer_id=?,reviewed_at=?,review_reason=? WHERE id=?',[$decision,$user,time(),$reason,$a['id']]);
        } else { throw new AttendanceError('Unbekannte Aktion.',400); }
        $conn->exec('COMMIT');header('Location: adjustments.php?saved=1');exit;
    } catch (Throwable $e) { $conn->exec('ROLLBACK');$error=$e instanceof AttendanceError?$e->getMessage():'Antrag konnte nicht verarbeitet werden.'; }
}
$records=$conn->prepare('SELECT id,startzeit,endzeit FROM zeiterfassung WHERE user_id=? ORDER BY startzeit DESC LIMIT 200');$records->execute([$user]);$records=$records->fetchAll(PDO::FETCH_ASSOC);
$s=$conn->prepare("SELECT a.*,u.username FROM time_adjustments a JOIN users u ON u.id=a.user_id WHERE a.user_id=? OR ?='admin' OR (?='supervisor' AND u.supervisor_id=?) ORDER BY a.id DESC LIMIT 100");$s->execute([$user,$role,$role,$user]);$requests=$s->fetchAll(PDO::FETCH_ASSOC);
chrono_page('Korrekturanträge');
?>
<p class="opacity-70">Nachträgliche Änderungen mit Begründung und Freigabe. Originalereignisse bleiben erhalten. Eigene Anträge müssen von einer anderen berechtigten Person freigegeben werden.</p>
<?php if($error): ?><div class="alert alert-error mb-6" role="alert"><?= h($error) ?></div><?php endif; ?><?php if(isset($_GET['saved'])): ?><div class="alert alert-success mb-6">Gespeichert.</div><?php endif; ?>
<section class="card bg-base-100 shadow-xl p-6 mb-6"><h2 class="text-xl font-semibold mb-4">Arbeitszeit korrigieren oder nachtragen</h2><form method="post"><?= csrf_field() ?><input class="input input-bordered w-full" type="hidden" name="action" value="request"><label class="block text-sm font-semibold mt-4 mb-2" for="record_id">Bestehender Eintrag</label><select class="select select-bordered w-full" id="record_id" name="record_id"><option value="0">Fehlende Buchung nachtragen</option><?php foreach($records as $r): ?><option value="<?= (int)$r['id'] ?>" <?= (int)($_GET['record_id']??0)===(int)$r['id']?'selected':'' ?>>#<?= (int)$r['id'] ?> · <?= h($r['startzeit']) ?> – <?= h($r['endzeit']) ?></option><?php endforeach; ?></select><label class="block text-sm font-semibold mt-4 mb-2"><input class="checkbox checkbox-primary" type="checkbox" name="cancel" value="1"> Bestehende Buchung stornieren (Zeitfelder dann leer lassen)</label><div class="grid grid-cols-1 md:grid-cols-2 gap-6"><div><label class="block text-sm font-semibold mt-4 mb-2" for="start">Beginn mit Zeitzone</label><input class="input input-bordered w-full" id="start" name="start" placeholder="2026-10-25T09:00+01:00"></div><div><label class="block text-sm font-semibold mt-4 mb-2" for="end">Ende mit Zeitzone</label><input class="input input-bordered w-full" id="end" name="end" placeholder="2026-10-25T17:00+01:00"></div><div><label class="block text-sm font-semibold mt-4 mb-2" for="pause">Tatsächliche Pause (Minuten)</label><input class="input input-bordered w-full" id="pause" name="pause" type="number" min="0"></div></div><small class="block opacity-70 mt-3">Deutschland: Winterzeit +01:00, Sommerzeit +02:00. Abwesenheitsgutschriften beginnen um 00:00 Uhr Ortszeit; als Ende 00:00 plus Sollminuten und als Pause 0 eintragen. Arbeitsnachträge dürfen nicht in der Zukunft liegen. Bei Zeitumstellungen den Offset für jeden Zeitpunkt einzeln angeben.</small><label class="block text-sm font-semibold mt-4 mb-2" for="reason">Begründung</label><textarea class="textarea textarea-bordered w-full" id="reason" name="reason" minlength="5" maxlength="1000" required></textarea><div class="flex flex-wrap gap-3 mt-5"><button class="btn btn-primary">Antrag stellen</button></div></form></section>
<h2 class="text-xl font-semibold mb-4">Letzte Anträge</h2><?php foreach($requests as $a): ?><section class="card bg-base-100 shadow-xl p-6 mb-6"><p class="text-sm font-semibold opacity-70 mb-2">#<?= (int)$a['id'] ?> · <?= h($a['username']) ?> · <?= h(['pending'=>'Offen','approved'=>'Freigegeben','rejected'=>'Abgelehnt'][$a['status']]) ?></p><p><?= $a['cancel']?'STORNIERUNG · ':'' ?><?= h(attendance_local((int)$a['proposed_start'])) ?> – <?= h(attendance_local((int)$a['proposed_end'])) ?> · <?= (int)$a['proposed_pause'] ?> Minuten Pause</p><p><?= h($a['reason']) ?></p><?php if($a['review_reason']): ?><p class="opacity-70">Entscheidung: <?= h($a['review_reason']) ?></p><?php endif; ?><?php if($a['status']==='pending'&&(int)$a['user_id']!==$user&&in_array($role,['admin','supervisor'],true)): ?><form method="post"><?= csrf_field() ?><input class="input input-bordered w-full" type="hidden" name="action" value="review"><input class="input input-bordered w-full" type="hidden" name="id" value="<?= (int)$a['id'] ?>"><label class="block text-sm font-semibold mt-4 mb-2" for="review_reason_<?= (int)$a['id'] ?>">Begründung der Entscheidung</label><input class="input input-bordered w-full" id="review_reason_<?= (int)$a['id'] ?>" name="review_reason" minlength="3" maxlength="1000" required><div class="flex flex-wrap gap-3 mt-5"><button class="btn btn-primary" name="decision" value="approved">Freigeben</button><button name="decision" value="rejected" class="btn btn-outline">Ablehnen</button></div></form><?php endif; ?></section><?php endforeach; ?>
<?php chrono_page_end(); ?>
