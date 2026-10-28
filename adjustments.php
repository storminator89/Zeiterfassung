<?php
require_once __DIR__.'/config.php'; require_once __DIR__.'/lib/app_ui.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user=(int)$_SESSION['user_id'];$role=$_SESSION['role'];$error='';
require_once __DIR__.'/lib/corrections.php';
require_once __DIR__.'/lib/correction_selection.php';
require_once __DIR__.'/lib/correction_presentation.php';
require_once __DIR__.'/lib/report_ordering.php';
report_register_start_order($conn);
$requestStatusLabels=['pending'=>'Offen','all'=>'Alle Status','approved'=>'Freigegeben','rejected'=>'Abgelehnt','withdrawn'=>'Zurückgenommen'];
$requestStatus=is_string($_GET['request_status']??null)&&isset($requestStatusLabels[$_GET['request_status']])?$_GET['request_status']:'pending';
$requestedPage=filter_var($_GET['request_page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:1;
$records=$conn->prepare('SELECT id,startzeit,endzeit FROM zeiterfassung WHERE user_id=? ORDER BY chrono_report_start_epoch(startzeit) DESC,id DESC LIMIT 200');$records->execute([$user]);$records=$records->fetchAll(PDO::FETCH_ASSOC);
$selectedRecordId=0;$selectionError='';
try {
    if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='request') {
        $selectedRecordId=correction_selection_id($_POST['record_id']??null,true);
    } elseif ($_SERVER['REQUEST_METHOD']==='GET' && array_key_exists('record_id',$_GET)) {
        $selectedRecordId=correction_selection_id($_GET['record_id']);
    }
    if ($selectedRecordId) {
        $ownRecord=null;
        if (!in_array($selectedRecordId,array_column($records,'id'),false)) {
            $s=$conn->prepare('SELECT id,startzeit,endzeit FROM zeiterfassung WHERE id=? AND user_id=?');$s->execute([$selectedRecordId,$user]);
            $ownRecord=$s->fetch(PDO::FETCH_ASSOC)?:null;$s->closeCursor();
        }
        $records=correction_selection_options($records,$selectedRecordId,$ownRecord);
    }
} catch (InvalidArgumentException $e) { $selectionError=$error=$e->getMessage(); }
$queueSelection=array_key_exists('record_id',$_GET)?['record_id'=>is_string($_GET['record_id'])?$_GET['record_id']:'invalid']:[];
if ($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='request') {
    $queueSelection=$selectionError?['record_id'=>'invalid']:($selectedRecordId?['record_id'=>$selectedRecordId]:[]);
}
$queueUrl=static fn(int $page): string=>'adjustments.php?'.http_build_query(array_merge(['request_status'=>$requestStatus,'request_page'=>$page],$queueSelection));
if ($_SERVER['REQUEST_METHOD']==='POST' && (!$selectionError || ($_POST['action']??'')!=='request')) {
    $conn->exec('BEGIN IMMEDIATE');
    try {
        correction_apply_locked($conn,$user,$role,$_POST);
        $conn->exec('COMMIT');header('Location: '.$queueUrl($requestedPage).'&saved=1');exit;
    } catch (Throwable $e) { $conn->exec('ROLLBACK');$error=$e instanceof AttendanceError?$e->getMessage():'Antrag konnte nicht verarbeitet werden.'; }
}
$requestScope="(a.user_id=? OR ?='admin' OR (?='supervisor' AND u.supervisor_id=?))";
$requestParams=[$user,$role,$role,$user];
$requestCounts=array_fill_keys(array_keys($requestStatusLabels),0);
$s=$conn->prepare('SELECT a.status,COUNT(*) AS total FROM time_adjustments a JOIN users u ON u.id=a.user_id WHERE '.$requestScope.' GROUP BY a.status');$s->execute($requestParams);
foreach($s->fetchAll(PDO::FETCH_ASSOC) as $count) { $requestCounts[$count['status']]=(int)$count['total'];$requestCounts['all']+=(int)$count['total']; }
$requestTotal=$requestCounts[$requestStatus];$requestPageSize=50;
$requestPages=max(1,(int)ceil($requestTotal/$requestPageSize));$requestPage=min($requestedPage,$requestPages);$requestOffset=($requestPage-1)*$requestPageSize;
$requestWhere=$requestScope;
if ($requestStatus!=='all') { $requestWhere.=' AND a.status=?';$requestParams[]=$requestStatus; }
$s=$conn->prepare('SELECT a.*,u.username FROM time_adjustments a JOIN users u ON u.id=a.user_id WHERE '.$requestWhere.' ORDER BY a.id DESC LIMIT '.$requestPageSize.' OFFSET '.$requestOffset);$s->execute($requestParams);$requests=$s->fetchAll(PDO::FETCH_ASSOC);
chrono_page('Korrekturanträge');
?>
<p class="opacity-70">Nachträgliche Änderungen mit Begründung und Freigabe. Originalereignisse bleiben erhalten. Eigene Anträge müssen von einer anderen berechtigten Person freigegeben werden.</p>
<?php if($error): ?><div class="alert alert-error mb-6" role="alert"><?= h($error) ?></div><?php endif; ?><?php if(isset($_GET['saved'])): ?><div class="alert alert-success mb-6">Gespeichert.</div><?php endif; ?>
<?php if(!$selectionError): ?><section class="card bg-base-100 shadow-xl p-6 mb-6"><h2 class="text-xl font-semibold mb-4">Arbeitszeit korrigieren oder nachtragen</h2><form method="post"><?= csrf_field() ?><input class="input input-bordered w-full" type="hidden" name="action" value="request"><label class="block text-sm font-semibold mt-4 mb-2" for="record_id">Bestehender Eintrag</label><select class="select select-bordered w-full" id="record_id" name="record_id"><option value="0">Fehlende Buchung nachtragen</option><?php foreach($records as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $selectedRecordId===(int)$r['id']?'selected':'' ?>>#<?= (int)$r['id'] ?> · <?= h($r['startzeit']) ?> – <?= h($r['endzeit']) ?></option><?php endforeach; ?></select><label class="block text-sm font-semibold mt-4 mb-2"><input class="checkbox checkbox-primary" type="checkbox" name="cancel" value="1"> Bestehende Buchung stornieren (Zeitfelder dann leer lassen)</label><div class="grid grid-cols-1 md:grid-cols-2 gap-6"><div><label class="block text-sm font-semibold mt-4 mb-2" for="start">Beginn mit Zeitzone</label><input class="input input-bordered w-full" id="start" name="start" placeholder="2026-10-25T09:00+01:00"></div><div><label class="block text-sm font-semibold mt-4 mb-2" for="end">Ende mit Zeitzone</label><input class="input input-bordered w-full" id="end" name="end" placeholder="2026-10-25T17:00+01:00"></div><div><label class="block text-sm font-semibold mt-4 mb-2" for="pause">Tatsächliche Pause (Minuten)</label><input class="input input-bordered w-full" id="pause" name="pause" type="number" min="0"></div></div><small class="block opacity-70 mt-3">Deutschland: Winterzeit +01:00, Sommerzeit +02:00. Abwesenheitsgutschriften beginnen um 00:00 Uhr Ortszeit; als Ende 00:00 plus Sollminuten und als Pause 0 eintragen. Arbeitsnachträge dürfen nicht in der Zukunft liegen. Bei Zeitumstellungen den Offset für jeden Zeitpunkt einzeln angeben.</small><label class="block text-sm font-semibold mt-4 mb-2" for="reason">Begründung</label><textarea class="textarea textarea-bordered w-full" id="reason" name="reason" minlength="5" maxlength="1000" required></textarea><div class="flex flex-wrap gap-3 mt-5"><button class="btn btn-primary">Antrag stellen</button></div></form></section><?php else: ?><p class="mb-6"><a class="btn btn-outline" href="adjustments.php">Eigene Buchung neu auswählen</a></p><?php endif; ?>
<section class="card bg-base-100 shadow-xl p-6 mb-6">
<h2 class="text-xl font-semibold mb-4">Anträge filtern</h2>
<p class="mb-4">Offene Anträge: <strong><?= $requestCounts['pending'] ?></strong> · innerhalb Ihrer bisherigen Sichtrechte.</p>
<form method="get" action="adjustments.php">
<?php if($queueSelection): ?><input type="hidden" name="record_id" value="<?= h($queueSelection['record_id']) ?>"><?php endif; ?>
<label class="block text-sm font-semibold mt-4 mb-2" for="request_status">Status</label>
<select class="select select-bordered w-full" id="request_status" name="request_status"><?php foreach($requestStatusLabels as $status=>$label): ?><option value="<?= h($status) ?>" <?= $requestStatus===$status?'selected':'' ?>><?= h($label) ?> (<?= $requestCounts[$status] ?>)</option><?php endforeach; ?></select>
<div class="flex flex-wrap gap-3 mt-5"><button class="btn btn-primary">Filtern</button></div>
</form></section>
<h2 class="text-xl font-semibold mb-4">Anträge: <?= h($requestStatusLabels[$requestStatus]) ?></h2>
<p class="text-sm opacity-70 mb-4"><?= $requestTotal ?> Anträge · Seite <?= $requestPage ?> von <?= $requestPages ?></p>
<?php if(!$requests): ?><p class="mb-6" role="status">Keine sichtbaren Anträge für diesen Status.</p><?php endif; ?>
<?php foreach($requests as $a): $preview=chrono_correction_preview($a); ?><section class="card bg-base-100 shadow-xl p-6 mb-6"><p class="text-sm font-semibold opacity-70 mb-2">#<?= (int)$a['id'] ?> · <?= h($a['username']) ?> · <?= h(['pending'=>'Offen','approved'=>'Freigegeben','rejected'=>'Abgelehnt','withdrawn'=>'Zurückgenommen'][$a['status']]) ?></p><?php if($a['cancel']): ?><p>STORNIERUNG · kein neuer Zeitwert</p><?php elseif($preview['proposed_usable']): ?><p><?= h(attendance_local((int)$a['proposed_start'])) ?> – <?= h(attendance_local((int)$a['proposed_end'])) ?> · <?= (int)$a['proposed_pause'] ?> Minuten Pause</p><?php else: ?><p>Beantragte Zeitangaben nicht auswertbar.</p><?php endif; ?>
<div class="text-sm my-3"><p class="font-semibold">Gespeicherte Netto-Buchungsdauer · kein Kontosaldo</p>
<p>Vorher: <?= h($preview['before']) ?> · Beantragt: <?= h($preview['after']) ?></p>
<p><?= h($preview['difference']===null?'Keine verlässliche Dauer-Differenz verfügbar.':'Dauer-Differenz: '.$preview['difference']) ?></p>
<p class="opacity-70">Nur ausgewählte Zeitfelder des gespeicherten Originals und Antrags. Keine Freigabe- oder Saldozusage; die Entscheidung prüft den aktuellen Datensatz erneut.</p></div><p><?= h($a['reason']) ?></p><?php if($a['review_reason']): ?><p class="opacity-70"><?= $a['status']==='withdrawn'?'Rücknahme':'Entscheidung' ?>: <?= h($a['review_reason']) ?></p><?php endif; ?><?php if($a['status']==='pending'&&(int)$a['user_id']!==$user&&in_array($role,['admin','supervisor'],true)): ?><form method="post"><?= csrf_field() ?><input class="input input-bordered w-full" type="hidden" name="action" value="review"><input class="input input-bordered w-full" type="hidden" name="id" value="<?= (int)$a['id'] ?>"><label class="block text-sm font-semibold mt-4 mb-2" for="review_reason_<?= (int)$a['id'] ?>">Begründung der Entscheidung</label><input class="input input-bordered w-full" id="review_reason_<?= (int)$a['id'] ?>" name="review_reason" minlength="3" maxlength="1000" required><div class="flex flex-wrap gap-3 mt-5"><button class="btn btn-primary" name="decision" value="approved">Freigeben</button><button name="decision" value="rejected" class="btn btn-outline">Ablehnen</button></div></form><?php endif; ?></section><?php endforeach; ?>
<?php if($requestPages>1): ?><nav class="flex flex-wrap gap-3 mb-6" aria-label="Antragsseiten">
<?php if($requestPage>1): ?><a class="btn btn-outline" href="<?= h($queueUrl($requestPage-1)) ?>">Neuere Anträge</a><?php endif; ?>
<?php if($requestPage<$requestPages): ?><a class="btn btn-outline" href="<?= h($queueUrl($requestPage+1)) ?>">Ältere Anträge</a><?php endif; ?>
</nav><?php endif; ?>
<?php chrono_page_end(); ?>
