<?php
require_once __DIR__.'/config.php';require_once __DIR__.'/lib/app_ui.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php');exit; }
$user=(int)$_SESSION['user_id'];$admin=$_SESSION['role']==='admin';$error='';$creditPreview=null;
require_once __DIR__.'/lib/work_model_credit_preview.php';
$modelForm=work_model_preview_form_values($_SERVER['REQUEST_METHOD']==='POST'?$_POST:[],date('Y-m-d'));
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!$admin) { abort_request(403,'Nur die Verwaltung darf Arbeitszeitmodelle festlegen.'); }
    $modelAction=$_POST['model_action']??'save';
    if(!is_string($modelAction)||!in_array($modelAction,['save','preview'],true)){
        $error='Modellaktion prüfen. Es wurde kein Modell gespeichert.';
    } elseif($modelAction==='preview') {
        $conn->beginTransaction();
        try {
            $candidatePreview=work_model_credit_preview_locked($conn,$user,$_SESSION['auth_version']??null,$_POST);
            if(!$conn->commit()){throw new RuntimeException('Preview snapshot could not close.');}
            $creditPreview=$candidatePreview;
        } catch(Throwable $e) { if($conn->inTransaction()){$conn->rollBack();}if($e instanceof AttendanceError&&$e->status===401){abort_request(401,'Bitte erneut anmelden.');}$error=$e instanceof AttendanceError?$e->getMessage():'Vorschau nicht verfügbar. Es wurde kein Modell gespeichert.'; }
    } else {
    $conn->exec('BEGIN IMMEDIATE');
    try {
        practice_work_model_apply_locked($conn,$user,$_SESSION['role'],$_POST);
        $conn->exec('COMMIT');header('Location: work_models.php?saved=1');exit;
    } catch(Throwable $e) { $conn->exec('ROLLBACK');$error=$e instanceof AttendanceError?$e->getMessage():'Modell konnte nicht gespeichert werden.'; }
    }
}
// The version list is a fresh, paged view; it never determines the effective model.
$modelToday=(new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
$modelScope='(m.user_id=? OR CAST(? AS INTEGER)=1)';$modelParams=[$user,(int)$admin];
$s=$conn->prepare('SELECT COUNT(*) FROM work_models m JOIN users u ON u.id=m.user_id WHERE '.$modelScope);$s->execute($modelParams);$modelTotal=(int)$s->fetchColumn();
$modelPageSize=50;$modelPages=max(1,(int)ceil($modelTotal/$modelPageSize));
$requestedModelPage=filter_var($_GET['model_page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:1;
$modelPage=min($requestedModelPage,$modelPages);$modelOffset=($modelPage-1)*$modelPageSize;
$s=$conn->prepare('SELECT m.*,u.username,(SELECT e.id FROM work_models e WHERE e.user_id=m.user_id AND e.valid_from<=? ORDER BY e.valid_from DESC,e.id DESC LIMIT 1) AS effective_id FROM work_models m JOIN users u ON u.id=m.user_id WHERE '.$modelScope.' ORDER BY m.valid_from DESC,m.id DESC LIMIT '.$modelPageSize.' OFFSET '.$modelOffset);$s->execute(array_merge([$modelToday],$modelParams));$models=$s->fetchAll(PDO::FETCH_ASSOC);
$selectedModelId=0;$selectedModel=null;$modelSelectionError='';$effectiveModel=null;
if (array_key_exists('model_id',$_GET)) {
    $rawModelId=$_GET['model_id'];
    $id=is_string($rawModelId)&&preg_match('/^[1-9][0-9]*$/D',$rawModelId)?filter_var($rawModelId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
    if ($id!==false) {
        $s=$conn->prepare('SELECT m.*,u.username FROM work_models m JOIN users u ON u.id=m.user_id WHERE '.$modelScope.' AND m.id=?');$s->execute(array_merge($modelParams,[$id]));$selectedModel=$s->fetch(PDO::FETCH_ASSOC)?:null;
    }
    if ($selectedModel) { $selectedModelId=(int)$selectedModel['id']; }
    else { $modelSelectionError='Modellversion nicht verfügbar. Bitte aus den sichtbaren Modellen neu auswählen.'; }
}
if (!$modelSelectionError) {
    // The selected version's owner (or self by default), not the visible page.
    $effectiveUser=$selectedModel?(int)$selectedModel['user_id']:$user;
    $s=$conn->prepare('SELECT m.*,u.username FROM work_models m JOIN users u ON u.id=m.user_id WHERE '.$modelScope.' AND m.user_id=? AND m.valid_from<=? ORDER BY m.valid_from DESC,m.id DESC LIMIT 1');$s->execute(array_merge($modelParams,[$effectiveUser,$modelToday]));$effectiveModel=$s->fetch(PDO::FETCH_ASSOC)?:null;
}
$modelUrl=static function(int $page,?int $id=null) use ($selectedModelId): string {
    $params=['model_page'=>$page];$id??=$selectedModelId;
    if ($id) { $params['model_id']=$id; }
    return 'work_models.php?'.http_build_query($params,'','&',PHP_QUERY_RFC3986);
};
$users=$admin?$conn->query('SELECT id,username FROM users ORDER BY username')->fetchAll(PDO::FETCH_ASSOC):[];
$days=['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'];chrono_page('Arbeitszeitmodelle');
?>
<p>Das jüngste Modell mit passendem Gültigkeitsdatum bestimmt die Sollzeit. Feiertage aus dem hinterlegten Feiertagskalender haben null Sollminuten. Bereits erfasste Abwesenheitsgutschriften bleiben unverändert und können über einen Korrekturantrag angepasst werden.</p>
<?php if($error): ?><div class="alert alert-error mb-6" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if($_SERVER['REQUEST_METHOD']==='GET'&&isset($_GET['saved'])): ?><div class="alert alert-success mb-6">Arbeitszeitmodell gespeichert.</div><?php endif; ?>
<?php if($admin): ?><section class="card bg-base-100 shadow-xl p-6 mb-6"><h2 class="text-xl font-semibold mb-4">Neues Modell hinterlegen</h2><p class="opacity-70">Ein neues Modell ersetzt keine Historie. Bei gleichem Gültigkeitsdatum gilt die zuletzt gespeicherte Version. Rückwirkende Modelle ändern Sollzeit-Auswertungen, aber keine gebuchten Gutschriften.</p><form method="post"><?= csrf_field() ?><label class="block text-sm font-semibold mt-4 mb-2" for="user_id">Person</label><select class="select select-bordered w-full" id="user_id" name="user_id" required><option value="">Person auswählen</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $modelForm['user_id']===(string)$u['id']?'selected':'' ?>><?= h($u['username']) ?></option><?php endforeach; ?></select><label class="block text-sm font-semibold mt-4 mb-2" for="valid_from">Gültig ab</label><input class="input input-bordered w-full" type="date" id="valid_from" name="valid_from" value="<?= h($modelForm['valid_from']) ?>" required><div class="grid grid-cols-1 md:grid-cols-2 gap-6"><?php foreach($days as $i=>$day): ?><div><label for="model-day<?= $i+1 ?>" class="block text-sm font-semibold mt-4 mb-2"><?= h($day) ?> (Minuten)</label><input id="model-day<?= $i+1 ?>" class="input input-bordered w-full" name="day<?= $i+1 ?>" type="number" min="0" max="1440" value="<?= h($modelForm['day'.($i+1)]) ?>" required></div><?php endforeach; ?></div><label class="block text-sm font-semibold mt-4 mb-2" for="reason">Begründung</label><textarea class="textarea textarea-bordered w-full" id="reason" name="reason" minlength="5" maxlength="1000" required><?= h($modelForm['reason']) ?></textarea><div class="flex flex-wrap gap-3 mt-5"><button class="btn btn-outline" name="model_action" value="preview" formnovalidate>Gutschriften vorab prüfen (ohne Speichern)</button><button class="btn btn-primary" name="model_action" value="save">Modell speichern</button></div></form></section><?php endif; ?>
<?php if($creditPreview!==null): ?><section id="model-credit-preview" class="card bg-base-100 shadow-xl p-6 mb-6">
<h2 class="text-xl font-semibold mb-4">Vorschau – kein Modell gespeichert</h2>
<p><?= h($creditPreview['username']) ?> · Person #<?= (int)$creditPreview['person_id'] ?> · ab <?= h($creditPreview['from']) ?> (Europe/Berlin)<?= h($creditPreview['until']!==null?' bis vor '.$creditPreview['until'].' (nächstes vorhandenes Modell)':'') ?></p>
<p class="text-sm opacity-70 mt-3">Hypothetisches Tagessoll des eingegebenen Modells im Vergleich zur gespeicherten Netto-Gutschrift. Bereits erfasste Gutschriften werden weder neu berechnet noch geändert. Die Abweichung ist kein Kontosaldo und kein automatischer Korrekturauftrag. Nur bereits gespeicherte Feiertage werden berücksichtigt; deren Vollständigkeit ist nicht bestätigt.</p>
<?php if($creditPreview['truncated']||$creditPreview['unknown']): ?><p class="mt-3" role="status">Die Vorschau ist begrenzt oder enthält nicht auswertbare Gutschriften. Unbekannte Werte sind nicht null Minuten; es gibt keine abschließende Summe.</p><?php endif; ?>
<?php if(!$creditPreview['items']): ?><p class="mt-3" role="status">Keine zuordenbaren gespeicherten Abwesenheiten im betrachteten Modellabschnitt gefunden. Dies ist kein Vollständigkeits- oder Datenqualitätsnachweis.</p><?php else: ?>
<p class="text-sm mt-3">Bis zu 100 gespeicherte Gutschriften, nach Berliner Beginn sortiert. Nicht zuordenbare Datumswerte stehen zuerst.</p>
<div class="table-responsive mt-3"><table class="table"><thead><tr><th>Buchung / Historie (neuer Tab)</th><th>Tag (Berlin)</th><th>Gespeicherte Netto-Gutschrift</th><th>Hypothetisches Tagessoll</th><th>Tagessoll minus Gutschrift</th></tr></thead><tbody>
<?php foreach($creditPreview['items'] as $credit): ?><tr><td><a class="link link-primary" target="_blank" rel="noopener noreferrer" href="<?= h('history.php?'.http_build_query(['entity'=>'zeiterfassung','user_id'=>$creditPreview['person_id'],'record_id'=>$credit['id']])) ?>">#<?= (int)$credit['id'] ?></a></td><td><?= h($credit['day']??'Nicht zuordenbar') ?></td><td><?= $credit['stored_minutes']===null?'Nicht auswertbar':(int)$credit['stored_minutes'].' min' ?></td><td><?= $credit['hypothetical_target']===null?'Unbekannt':(int)$credit['hypothetical_target'].' min' ?></td><td><?= $credit['difference']===null?'Unbekannt':(int)$credit['difference'].' min' ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<p class="text-sm opacity-70 mt-3">Die bestehende Buchung bleibt maßgeblich. Eine erforderliche Gutschriftenkorrektur muss die betroffene Person separat beantragen; die Vier-Augen-Freigabe bleibt erhalten. Speichern des Modells ist ein eigener bewusster Schritt im vorhandenen Speicherweg; die Vorschau friert dessen Datenstand nicht ein.</p>
<p class="mt-3"><a class="link link-primary" href="#user_id">Zur Modellmaske – Eingaben bleiben erhalten</a></p>
</section><?php endif; ?>
<?php if($modelSelectionError): ?><div class="alert alert-error mb-6" role="alert"><?= h($modelSelectionError) ?> <a href="<?= h($modelUrl($modelPage)) ?>">Auswahl zurücksetzen</a></div>
<?php else: ?><section class="card bg-base-100 shadow-xl p-6 mb-6">
<h2 class="text-xl font-semibold mb-4"><?= $selectedModel?'Ausgewählte Version und heute maßgebliches Modell':'Heute maßgebliches eigenes Modell' ?></h2>
<p class="text-sm opacity-70 mb-4">Maßgeblich am <?= h($modelToday) ?> (Europe/Berlin), unabhängig von der Listenseite. Die Auswahl dient nur der Ansicht und ändert kein Modell.</p>
<?php if(!$effectiveModel): ?><p class="mb-4" role="status">Für <?= $selectedModel?h($selectedModel['username']):'Ihr Konto' ?> ist heute noch kein Modell gültig.</p><?php endif; ?>
<?php $detailModels=[];if($selectedModel) { $detailModels[]=['label'=>'Ausgewählt','model'=>$selectedModel]; }if($effectiveModel) { $detailModels[]=['label'=>'Heute maßgeblich','model'=>$effectiveModel]; } ?>
<?php if($detailModels): ?><div class="table-responsive"><table class="table"><thead><tr><th>Ansicht</th><th>Version</th><th>Person</th><th>Gültig ab</th><?php foreach($days as $day): ?><th><?= h(substr($day,0,2)) ?></th><?php endforeach; ?><th>Begründung</th></tr></thead><tbody>
<?php foreach($detailModels as $detail): $m=$detail['model']; ?><tr><td><?= h($detail['label']) ?></td><td>#<?= (int)$m['id'] ?></td><td><?= h($m['username']) ?></td><td><?= h($m['valid_from']) ?></td><?php foreach(json_decode($m['minutes_json'],true) as $v): ?><td><?= (int)$v ?> min</td><?php endforeach; ?><td><?= h($m['reason']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<?php if($selectedModel): ?><p class="mt-4"><a href="<?= h('work_models.php?'.http_build_query(['model_page'=>$modelPage])) ?>">Zur eigenen heutigen Ansicht</a></p><?php endif; ?>
</section><?php endif; ?>
<section class="card bg-base-100 shadow-xl p-6 mb-6"><h2 class="text-xl font-semibold mb-4">Hinterlegte Modelle</h2>
<p class="text-sm opacity-70 mb-4"><?= $modelTotal ?> Modellversionen · Seite <?= $modelPage ?> von <?= $modelPages ?> · <?= $modelPageSize ?> je Seite innerhalb Ihrer bisherigen Sichtrechte. Neue Versionen können die Seiten verschieben.</p>
<?php if(!$models): ?><p role="status">Keine sichtbaren Modellversionen.</p><?php else: ?>
<div class="table-responsive"><table class="table"><thead><tr><th>Version / Ansicht</th><th>Person</th><th>Gültig ab</th><?php foreach($days as $day): ?><th><?= h(substr($day,0,2)) ?></th><?php endforeach; ?><th>Begründung</th></tr></thead><tbody>
<?php foreach($models as $m): ?><tr><td><a href="<?= h($modelUrl($modelPage,(int)$m['id'])) ?>">#<?= (int)$m['id'] ?> ansehen</a><?php if((int)$m['id']===$selectedModelId): ?><small class="block">Ausgewählt</small><?php endif; ?><small class="block opacity-70"><?= (int)$m['id']===(int)$m['effective_id']?'Heute maßgeblich':($m['valid_from']>$modelToday?'Künftiges Gültigkeitsdatum':'Frühere Version') ?></small></td><td><?= h($m['username']) ?></td><td><?= h($m['valid_from']) ?></td><?php foreach(json_decode($m['minutes_json'],true) as $v): ?><td><?= (int)$v ?> min</td><?php endforeach; ?><td><?= h($m['reason']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<?php if($modelPages>1): ?><nav class="flex flex-wrap gap-3 mt-5" aria-label="Modellversionsseiten">
<?php if($modelPage>1): ?><a class="btn btn-outline" href="<?= h($modelUrl($modelPage-1)) ?>">Neuere Modellversionen</a><?php endif; ?>
<?php if($modelPage<$modelPages): ?><a class="btn btn-outline" href="<?= h($modelUrl($modelPage+1)) ?>">Ältere Modellversionen</a><?php endif; ?>
</nav><?php endif; ?></section>
<?php chrono_page_end(); ?>
