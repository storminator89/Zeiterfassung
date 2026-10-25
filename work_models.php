<?php
require_once __DIR__.'/config.php';require_once __DIR__.'/lib/terminal_ui.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php');exit; }
$user=(int)$_SESSION['user_id'];$admin=$_SESSION['role']==='admin';$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!$admin) { abort_request(403,'Nur die Verwaltung darf Arbeitszeitmodelle festlegen.'); }
    $conn->exec('BEGIN IMMEDIATE');
    try {
        $target=(int)($_POST['user_id']??0);$day=(string)($_POST['valid_from']??'');practice_date($day);
        $reason=trim((string)($_POST['reason']??''));$minutes=[];
        foreach(range(1,7) as $n) { $v=filter_var($_POST['day'.$n]??'',FILTER_VALIDATE_INT);if($v===false||$v<0||$v>1440) { throw new AttendanceError('Sollminuten müssen zwischen 0 und 1440 liegen.',400); }$minutes[]=$v; }
        if (!att_one($conn,'SELECT id FROM users WHERE id=?',[$target])||strlen($reason)<5||strlen($reason)>1000) { throw new AttendanceError('Person und Begründung prüfen.',400); }
        $GLOBALS['audit_reason']='Arbeitszeitmodell: '.$reason;
        att_exec($conn,'INSERT INTO work_models(user_id,valid_from,minutes_json,reason,created_by,created_at) VALUES(?,?,?,?,?,?)',[$target,$day,json_encode($minutes),$reason,$user,time()]);
        $conn->exec('COMMIT');header('Location: work_models.php?saved=1');exit;
    } catch(Throwable $e) { $conn->exec('ROLLBACK');$error=$e instanceof AttendanceError?$e->getMessage():'Modell konnte nicht gespeichert werden.'; }
}
$s=$conn->prepare('SELECT m.*,u.username FROM work_models m JOIN users u ON u.id=m.user_id WHERE m.user_id=? OR CAST(? AS INTEGER)=1 ORDER BY m.valid_from DESC,m.id DESC LIMIT 200');$s->execute([$user,(int)$admin]);$models=$s->fetchAll(PDO::FETCH_ASSOC);
$users=$admin?$conn->query('SELECT id,username FROM users ORDER BY username')->fetchAll(PDO::FETCH_ASSOC):[];
$days=['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'];terminal_page('Arbeitszeitmodelle');
?>
<p>Das jüngste Modell mit passendem Gültigkeitsdatum bestimmt die Sollzeit. Feiertage aus dem hinterlegten Feiertagskalender haben null Sollminuten. Bereits erfasste Abwesenheitsgutschriften bleiben unverändert und können über einen Korrekturantrag angepasst werden.</p>
<?php if($error): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if(isset($_GET['saved'])): ?><div class="notice">Arbeitszeitmodell gespeichert.</div><?php endif; ?>
<?php if($admin): ?><section class="card"><h2>Neues Modell hinterlegen</h2><p class="muted">Ein neues Modell ersetzt keine Historie. Bei gleichem Gültigkeitsdatum gilt die zuletzt gespeicherte Version. Rückwirkende Modelle ändern Sollzeit-Auswertungen, aber keine gebuchten Gutschriften.</p><form method="post"><?= csrf_field() ?><label>Person</label><select name="user_id"><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= h($u['username']) ?></option><?php endforeach; ?></select><label>Gültig ab</label><input type="date" name="valid_from" value="<?= date('Y-m-d') ?>" required><div class="grid"><?php foreach($days as $i=>$day): ?><div><label><?= h($day) ?> (Minuten)</label><input name="day<?= $i+1 ?>" type="number" min="0" max="1440" value="0" required></div><?php endforeach; ?></div><label>Begründung</label><textarea name="reason" minlength="5" maxlength="1000" required></textarea><div class="actions"><button>Modell speichern</button></div></form></section><?php endif; ?>
<section class="card"><h2>Hinterlegte Modelle</h2><div class="table-wrap"><table><thead><tr><th>Person</th><th>Gültig ab</th><?php foreach($days as $day): ?><th><?= h(substr($day,0,2)) ?></th><?php endforeach; ?><th>Begründung</th></tr></thead><tbody><?php foreach($models as $m): ?><tr><td><?= h($m['username']) ?></td><td><?= h($m['valid_from']) ?></td><?php foreach(json_decode($m['minutes_json'],true) as $v): ?><td><?= (int)$v ?> min</td><?php endforeach; ?><td><?= h($m['reason']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php terminal_page_end(); ?>
