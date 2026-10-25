<?php
require_once __DIR__.'/config.php';require_once __DIR__.'/lib/terminal_ui.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php');exit; }
$user=(int)$_SESSION['user_id'];$role=$_SESSION['role'];$before=max(1,(int)($_GET['before']??PHP_INT_MAX));
$s=$conn->prepare("SELECT a.* FROM audit_log a LEFT JOIN users u ON u.id=a.user_id WHERE a.id<? AND (a.user_id=? OR ?='admin' OR (?='supervisor' AND u.supervisor_id=?)) ORDER BY a.id DESC LIMIT 100");$s->execute([$before,$user,$role,$role,$user]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
terminal_page('Änderungshistorie');
?>
<p>Erfassung, Korrekturantrag, Entscheidung und Arbeitszeitmodell werden mit Zeitpunkt, Akteur und Vorher-/Nachher-Werten protokolliert. Baseline-Einträge dokumentieren den Stand beim Upgrade; frühere Änderungen sind nicht rekonstruierbar.</p>
<?php foreach($rows as $r): ?><section class="card"><p class="eyebrow">#<?= (int)$r['id'] ?> · <?= h(attendance_local((int)$r['occurred_at'])) ?></p><h2><?= h(['zeiterfassung'=>'Buchung','work_models'=>'Arbeitszeitmodell','time_adjustments'=>'Korrekturantrag','users'=>'Kontostammdaten','Feiertage'=>'Feiertagskalender','work_sessions'=>'Arbeitsintervall','break_intervals'=>'Pausenintervall'][$r['entity']]??$r['entity']) ?> #<?= (int)$r['record_id'] ?> · <?= h($r['operation']) ?></h2><p><?= h($r['actor']) ?> · <?= h($r['reason']) ?></p><details><summary>Vorher und nachher ansehen</summary><h3>Vorher</h3><pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?= h($r['before_json']??'Kein vorheriger Eintrag') ?></pre><h3>Nachher</h3><pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?= h($r['after_json']??'Storniert') ?></pre></details></section><?php endforeach; ?>
<?php if(count($rows)===100): ?><a href="history.php?before=<?= (int)end($rows)['id'] ?>">Ältere Einträge</a><?php endif; ?>
<?php terminal_page_end(); ?>
