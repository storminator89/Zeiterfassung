<?php
require_once __DIR__.'/config.php'; require_once __DIR__.'/lib/terminal_ui.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user=(int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    attendance_json(fn() => attendance_transition($conn,$user,'web:'.$user,$_POST['event_id']??'',$_POST['action']??'',($_POST['confirm_end_pause']??'')==='1'));
}
terminal_page('Meine Arbeitszeit');
$state=attendance_status($conn,$user)['state'];
$names=['absent'=>'Nicht angemeldet','working'=>'Arbeitszeit läuft','paused'=>'Pause läuft'];
?>
<div class="grid"><section class="card"><p class="eyebrow"><?= h($_SESSION['username']) ?></p><div class="status" id="status"><?= h($names[$state]) ?></div><p id="message" role="status" aria-live="polite">Jede Buchung wird mit Serverzeit bestätigt.</p><div class="actions" id="actions" data-state="<?= h($state) ?>"><button data-action="start">Kommen</button><button data-action="pause_start" class="quiet">Pause beginnen</button><button data-action="pause_end" class="quiet">Pause beenden</button><button data-action="end">Gehen</button></div><p><a href="adjustments.php">Buchung vergessen oder Zeit korrigieren?</a></p></section><section class="card"><h2>Tatsächliche Pausen</h2><p>Start und Ende jeder Pause werden erfasst. Mindestpausen werden nicht automatisch abgezogen. Bitte die betrieblichen Pausenregeln beachten.</p><p class="muted">Beim Gehen während einer Pause werden Pause und Arbeitstag gemeinsam beendet – nach deiner Bestätigung.</p></section></div>
<section class="card"><h2>Letzte Arbeitszeiten</h2><div class="table-wrap"><table><thead><tr><th>Art</th><th>Beginn / Gutschrift</th><th>Ende</th><th>Pause (Min.)</th><th>Korrektur</th></tr></thead><tbody><?php
$s=$conn->prepare('SELECT * FROM zeiterfassung WHERE user_id=? ORDER BY startzeit DESC LIMIT 30');$s->execute([$user]);foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r): ?><tr><td><?= h($r['absence_kind']??'Arbeitszeit') ?></td><td><?= $r['absence_kind']?h(substr($r['startzeit'],0,10).' · '.$r['planned_minutes'].' Sollminuten'):h($r['startzeit']) ?></td><td><?= h($r['absence_kind']?'Gutschrift':($r['endzeit']??'Läuft')) ?></td><td><?= h($r['pause']) ?></td><td><a href="adjustments.php?record_id=<?= (int)$r['id'] ?>">Beantragen</a></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="card"><h2>Abwesenheit erfassen</h2><p>Urlaub oder Krankheit werden mit den Sollminuten deines Arbeitszeitmodells gutgeschrieben. Freie Tage und hinterlegte Feiertage werden ausgelassen. Gutschriften sind keine gestempelten Arbeitszeiten.</p><form id="absence-form" method="post" action="save.php"><?= csrf_field() ?><div class="grid"><div><label>Von</label><input type="date" name="urlaubStart" required></div><div><label>Bis</label><input type="date" name="urlaubEnde" required></div><div><label>Art</label><select name="beschreibung"><option>Urlaub</option><option>Krankheit</option></select></div></div><p id="absence-message" role="status" aria-live="polite"></p><div class="actions"><button>Abwesenheit erfassen</button></div></form></section>
<script src="assets/js/attendance.js"></script>
<?php terminal_page_end(); ?>
