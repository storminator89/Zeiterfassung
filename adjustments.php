<?php
require_once __DIR__.'/config.php'; require_once __DIR__.'/lib/terminal_ui.php';
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
            if (!attendance_enabled($conn) && !att_one($conn,'SELECT id FROM work_sessions LIMIT 1')) { throw new AttendanceError('Modul ist deaktiviert.'); }
            $record=(int)($_POST['record_id']??0); $r=$record?att_one($conn,'SELECT * FROM zeiterfassung WHERE id=? AND user_id=?',[$record,$user]):null;
            if ($record && (!$r || !$r['endzeit'])) { throw new AttendanceError('Nur eigene abgeschlossene Arbeitszeiten korrigieren.'); }
            $start=correction_time((string)($_POST['start']??''));$end=correction_time((string)($_POST['end']??''));$pause=filter_var($_POST['pause']??'',FILTER_VALIDATE_INT);$reason=trim((string)($_POST['reason']??''));
            if ($end<=$start||$end-$start>7*86400||$pause===false||$pause<0||$pause>floor(($end-$start)/60)||strlen($reason)<5||strlen($reason)>1000) { throw new AttendanceError('Zeitraum, Pausen und Begründung prüfen.',400); }
            att_exec($conn,'INSERT INTO time_adjustments(user_id,record_id,proposed_start,proposed_end,proposed_pause,reason,original_json,created_at) VALUES(?,?,?,?,?,?,?,?)',[$user,$record?:null,$start,$end,$pause,$reason,$r?json_encode($r):null,time()]);
        } elseif (($_POST['action']??'')==='review') {
            $a=att_one($conn,'SELECT * FROM time_adjustments WHERE id=? AND status=?',[(int)($_POST['id']??0),'pending']);
            if (!$a || (int)$a['user_id']===$user || !($role==='admin'||($role==='supervisor'&&att_one($conn,'SELECT id FROM users WHERE id=? AND supervisor_id=?',[$a['user_id'],$user])))) { throw new AttendanceError('Keine Freigabeberechtigung.',403); }
            if (!att_one($conn,'SELECT id FROM users WHERE id=?',[$a['user_id']])) { throw new AttendanceError('Konto existiert nicht mehr.'); }
            $decision=$_POST['decision']??'';$reason=trim((string)($_POST['review_reason']??''));
            if (!in_array($decision,['approved','rejected'],true)||strlen($reason)<3||strlen($reason)>1000) { throw new AttendanceError('Entscheidung und Begründung erforderlich.',400); }
            if ($decision==='approved') {
                $old=$a['record_id']?att_one($conn,'SELECT * FROM zeiterfassung WHERE id=? AND user_id=?',[$a['record_id'],$a['user_id']]):null;
                if ($a['record_id'] && (!$old || json_encode($old)!==$a['original_json'])) { throw new AttendanceError('Datensatz wurde inzwischen geändert. Neuen Antrag stellen.'); }
                $s=$conn->prepare('SELECT * FROM zeiterfassung WHERE user_id=? AND id<>?');$s->execute([$a['user_id'],$a['record_id']??0]);$others=$s->fetchAll(PDO::FETCH_ASSOC);$s->closeCursor();
                foreach($others as $other) {
                    $begin=(new DateTimeImmutable($other['startzeit'],new DateTimeZone('Europe/Berlin')))->getTimestamp();
                    $end=$other['endzeit']?(new DateTimeImmutable($other['endzeit'],new DateTimeZone('Europe/Berlin')))->getTimestamp():PHP_INT_MAX;
                    if ($begin<$a['proposed_end']&&$end>$a['proposed_start']) { throw new AttendanceError('Zeitraum überschneidet sich mit einer anderen Buchung.'); }
                }
                if ($old) {
                    att_exec($conn,'UPDATE zeiterfassung SET startzeit=?,endzeit=?,pause=? WHERE id=?',[attendance_local((int)$a['proposed_start']),attendance_local((int)$a['proposed_end']),$a['proposed_pause'],$a['record_id']]);
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
$records=$conn->prepare('SELECT id,startzeit,endzeit FROM zeiterfassung WHERE user_id=? AND endzeit IS NOT NULL ORDER BY startzeit DESC LIMIT 200');$records->execute([$user]);$records=$records->fetchAll(PDO::FETCH_ASSOC);
$s=$conn->prepare("SELECT a.*,u.username FROM time_adjustments a JOIN users u ON u.id=a.user_id WHERE a.user_id=? OR ?='admin' OR (?='supervisor' AND u.supervisor_id=?) ORDER BY a.id DESC LIMIT 100");$s->execute([$user,$role,$role,$user]);$requests=$s->fetchAll(PDO::FETCH_ASSOC);
terminal_page('Korrekturanträge');
?>
<p class="muted">Nachträgliche Änderungen mit Begründung und Freigabe. Originalereignisse bleiben erhalten. Eigene Anträge müssen von einer anderen berechtigten Person freigegeben werden.</p>
<?php if($error): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?><?php if(isset($_GET['saved'])): ?><div class="notice">Gespeichert.</div><?php endif; ?>
<section class="card"><h2>Arbeitszeit korrigieren oder nachtragen</h2><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="request"><label>Bestehender Eintrag</label><select name="record_id"><option value="0">Fehlende Buchung nachtragen</option><?php foreach($records as $r): ?><option value="<?= (int)$r['id'] ?>" <?= (int)($_GET['record_id']??0)===(int)$r['id']?'selected':'' ?>>#<?= (int)$r['id'] ?> · <?= h($r['startzeit']) ?> – <?= h($r['endzeit']) ?></option><?php endforeach; ?></select><div class="grid"><div><label>Beginn mit Zeitzone</label><input name="start" placeholder="2026-10-25T09:00+01:00" required></div><div><label>Ende mit Zeitzone</label><input name="end" placeholder="2026-10-25T17:00+01:00" required></div><div><label>Tatsächliche Pause (Minuten)</label><input name="pause" type="number" min="0" required></div></div><small>Deutschland: Winterzeit +01:00, Sommerzeit +02:00. Bei Zeitumstellungen den Offset für jeden Zeitpunkt einzeln angeben.</small><label>Begründung</label><textarea name="reason" minlength="5" maxlength="1000" required></textarea><div class="actions"><button>Antrag stellen</button></div></form></section>
<h2>Letzte Anträge</h2><?php foreach($requests as $a): ?><section class="card"><p class="eyebrow">#<?= (int)$a['id'] ?> · <?= h($a['username']) ?> · <?= h(['pending'=>'Offen','approved'=>'Freigegeben','rejected'=>'Abgelehnt'][$a['status']]) ?></p><p><?= h(attendance_local((int)$a['proposed_start'])) ?> – <?= h(attendance_local((int)$a['proposed_end'])) ?> · <?= (int)$a['proposed_pause'] ?> Minuten Pause</p><p><?= h($a['reason']) ?></p><?php if($a['review_reason']): ?><p class="muted">Entscheidung: <?= h($a['review_reason']) ?></p><?php endif; ?><?php if($a['status']==='pending'&&(int)$a['user_id']!==$user&&in_array($role,['admin','supervisor'],true)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="review"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><label>Begründung der Entscheidung</label><input name="review_reason" minlength="3" maxlength="1000" required><div class="actions"><button name="decision" value="approved">Freigeben</button><button name="decision" value="rejected" class="quiet">Ablehnen</button></div></form><?php endif; ?></section><?php endforeach; ?>
<?php terminal_page_end(); ?>
