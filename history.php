<?php
require_once __DIR__.'/config.php';require_once __DIR__.'/lib/app_ui.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php');exit; }
require_once __DIR__.'/lib/history_filters.php';
require_once __DIR__.'/lib/history_presentation.php';
require_once __DIR__.'/lib/history_export.php';
$user=(int)$_SESSION['user_id'];$role=$_SESSION['role'];$filters=chrono_history_filters($_GET);$values=$filters['values'];$rows=[];
$exportMode=chrono_history_export_mode($_GET);
if ($exportMode['requested']) {
    try {
        if (!$exportMode['valid'] || $filters['errors']) { $export=chrono_history_export_failure(400); }
        elseif (chrono_history_correlated_requested($_GET)) {
            require_once __DIR__.'/lib/native_history_relations.php';
            try { $export=chrono_history_correlated_export_page(chrono_native_history_bundle($conn,$filters,$user),$values); }
            catch (AttendanceError $authorizationError) {
                if ($authorizationError->status!==401 && $authorizationError->status!==403) { throw $authorizationError; }
                $export=chrono_history_correlated_authorization_failure($authorizationError->status);
            }
        }
        else {
            $query=chrono_history_query($filters,$user,$role);$s=$conn->prepare($query['sql']);$s->execute($query['params']);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
            $export=chrono_history_export_page($rows,$values);
        }
    } catch (Throwable $exportError) { $export=chrono_history_export_failure(); }
    // Materialization/encoding succeeded or produced one fixed failure before these headers/body.
    http_response_code($export['status']);
    foreach ($export['headers'] as $name=>$value) { header($name.': '.$value); }
    echo $export['body'];exit;
}
$showRelations=($_GET['relations']??null)==='1';$relations=null;$byAudit=[];
if (array_key_exists('relations',$_GET) && !$showRelations) { $filters['errors'][]='Ungültige Auswahl der Historienbezüge.'; }
if ($filters['errors']) { http_response_code(400); }
elseif ($showRelations) {
    require_once __DIR__.'/lib/native_history_relations.php';
    try {
        $bundle=chrono_native_history_bundle($conn,$filters,$user);
        $relations=chrono_native_history_relations($bundle,$values);$rows=$bundle['rows'];
        $byAudit=array_column($relations['anchors'],null,'audit_id');
    } catch (Throwable $readError) {
        $status=$readError instanceof AttendanceError && in_array($readError->status,[401,403],true)?$readError->status:503;
        http_response_code($status);header('Content-Type: text/plain; charset=UTF-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
        echo $status===503?'Historienbezüge derzeit nicht verfügbar.':'Historienbezüge nicht berechtigt. Bitte erneut anmelden.';exit;
    }
}
else { $query=chrono_history_query($filters,$user,$role);$s=$conn->prepare($query['sql']);$s->execute($query['params']);$rows=$s->fetchAll(PDO::FETCH_ASSOC); }
$relationUrl=static fn(string $url):string=>$showRelations?chrono_native_history_relations_url($url):$url;

chrono_page('Änderungshistorie');
?>
<p>Erfassung, Korrekturantrag, Entscheidung und Arbeitszeitmodell werden mit Zeitpunkt, Akteur und Vorher-/Nachher-Werten protokolliert. Baseline-Einträge dokumentieren den Stand beim Upgrade; frühere Änderungen sind nicht rekonstruierbar.</p>
<section class="card bg-base-100 shadow-xl p-6 mb-6">
<h2 class="text-xl font-semibold mb-4">Historie filtern</h2>
<form method="get" action="history.php">
<?php if($showRelations): ?><input type="hidden" name="relations" value="1"><?php endif; ?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div><label class="block text-sm font-semibold mt-4 mb-2" for="history_user">Person-ID (optional)</label><input class="input input-bordered w-full" type="number" min="1" step="1" id="history_user" name="user_id" value="<?= h($values['user_id']) ?>"></div>
<div><label class="block text-sm font-semibold mt-4 mb-2" for="history_from">Änderung von (optional)</label><input class="input input-bordered w-full" type="date" id="history_from" name="from" value="<?= h($values['from']) ?>"></div>
<div><label class="block text-sm font-semibold mt-4 mb-2" for="history_to">Änderung bis einschließlich (optional)</label><input class="input input-bordered w-full" type="date" id="history_to" name="to" value="<?= h($values['to']) ?>"></div>
<div><label class="block text-sm font-semibold mt-4 mb-2" for="history_operation">Vorgang (optional)</label><select class="select select-bordered w-full" id="history_operation" name="operation"><option value="">Alle Vorgänge</option><?php foreach(chrono_history_operations() as $operation=>$label): ?><option value="<?= h($operation) ?>" <?= $values['operation']===$operation?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
</div>
<p class="text-sm opacity-70 mt-3">Der Zeitraum gilt für den Zeitpunkt der protokollierten Änderung in Europe/Berlin, nicht für den Buchungstag. Alle Filter gelten gemeinsam und nur innerhalb Ihrer bisherigen Sichtrechte.</p>
<details class="mt-4" <?= $values['entity']!==''||$values['record_id']!==''||$values['request_id']!==''?'open':'' ?>><summary>Datensatz oder Antrag eingrenzen</summary>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div><label class="block text-sm font-semibold mt-4 mb-2" for="history_entity">Bereich (optional)</label><select class="select select-bordered w-full" id="history_entity" name="entity"><option value="">Alle Bereiche</option><?php foreach(chrono_history_entities() as $entity=>$label): ?><option value="<?= h($entity) ?>" <?= $values['entity']===$entity?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
<div><label class="block text-sm font-semibold mt-4 mb-2" for="history_record">Datensatz-ID im Bereich (optional)</label><input class="input input-bordered w-full" type="number" min="1" step="1" id="history_record" name="record_id" value="<?= h($values['record_id']) ?>"></div>
<div><label class="block text-sm font-semibold mt-4 mb-2" for="history_request">Korrekturantrags-ID (optional)</label><input class="input input-bordered w-full" type="number" min="1" step="1" id="history_request" name="request_id" value="<?= h($values['request_id']) ?>"></div>
</div><p class="text-sm opacity-70 mt-3">Datensatz-IDs können in mehreren Bereichen vorkommen. Die Korrekturantrags-ID zeigt nur Änderungen am Antrag selbst, nicht automatisch die zugehörige Buchung oder Sitzung.</p>
</details>
<div class="flex flex-wrap gap-3 mt-5"><button class="btn btn-primary">Filtern</button><a class="btn btn-outline" href="history.php">Filter zurücksetzen</a></div>
</form></section>
<?php if($filters['errors']): ?><div class="alert alert-error mb-6" role="alert"><ul><?php foreach($filters['errors'] as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div>
<?php elseif(!$rows): ?><p class="mb-6" role="status">Keine sichtbaren Einträge für diese Filter.</p><?php endif; ?>
<?php if(!$filters['errors']&&$filters['ids']['before']!==null): ?><p class="mb-6"><a href="<?= h($relationUrl(chrono_history_url($filters))) ?>">Neueste Treffer mit diesen Filtern</a></p><?php endif; ?>
<?php if(!$filters['errors']): $toggleBase=chrono_history_url($filters,$filters['ids']['before']); ?><p class="mb-6"><a class="btn btn-outline" href="<?= h($showRelations?$toggleBase:$toggleBase.(str_contains($toggleBase,'?')?'&':'?').'relations=1') ?>"><?= $showRelations?'Bezüge ausblenden':'Bezüge anzeigen' ?></a></p><?php endif; ?>
<?php if(!$filters['errors']): ?><p class="mb-6"><a class="btn btn-outline" href="<?= h(chrono_history_export_url($values)) ?>">Aktuelle Treffer dieser Filterseite als ausgewähltes JSON (max. 100)</a></p><?php endif; ?>
<?php if(!$filters['errors']): ?><p class="mb-6"><a class="btn btn-outline" href="<?= h(chrono_history_correlated_export_url($values)) ?>">Bezüge dieser Treffer als JSON (begrenzt)</a><span class="block text-sm opacity-70 mt-2">Zugehörige Referenzen können außerhalb der Ankerfilter liegen. Aktuelle gespeicherte Beziehungen, keine verursachenden Ereigniszuweisungen oder vollständige Historie.</span></p><?php endif; ?>
<?php foreach($rows as $r): $presentation=chrono_history_presentation($r['entity'],$r['before_json']??null,$r['after_json']??null); ?>
<section class="card bg-base-100 shadow-xl p-6 mb-6"><p class="text-sm font-semibold opacity-70 mb-2">#<?= (int)$r['id'] ?> · <?= h(attendance_local((int)$r['occurred_at'])) ?></p><h2 class="text-xl font-semibold mb-4"><?= h(chrono_history_entities()[$r['entity']]??$r['entity']) ?> #<?= (int)$r['record_id'] ?> · <?= h($r['operation']) ?></h2><p><?= h($r['actor']) ?> · <?= h($r['reason']) ?></p>
<?php if($showRelations): $relation=$byAudit[(string)$r['id']]??null; ?>
<?php if($relation===null||$relation['status']==='unresolved'): ?><p class="text-sm mt-3">Bezug ungeklärt.</p>
<?php elseif($relation['status']==='not_applicable'): ?><p class="text-sm mt-3">Für diesen Bereich kein Buchungsbezug.</p>
<?php else: ?><p class="text-sm mt-3">Bezüge:
<?php foreach($relation['session_ids'] as $id): ?><a class="link mr-2" href="#history-session-<?= h($id) ?>">Sitzung #<?= h($id) ?></a><?php endforeach; ?>
<?php foreach($relation['event_ids'] as $id): ?><a class="link mr-2" href="#history-event-<?= h($id) ?>">Ereignis #<?= h($id) ?></a><?php endforeach; ?>
<?php foreach($relation['request_ids'] as $id): ?><a class="link mr-2" href="#history-request-<?= h($id) ?>">Antrag #<?= h($id) ?></a><?php endforeach; ?>
<?php if($relation['current_request_relation']): ?><span class="badge badge-ghost">heutige Antragszuordnung</span><?php endif; ?></p>
<?php endif; endif; ?>
<details><summary>Vorher und nachher ansehen</summary>
<p class="text-sm opacity-70 my-3">Feldvergleich und JSON zeigen nur ausgewählte freigegebene Felder, nicht den vollständigen Auditinhalt.</p>
<?php if($presentation['limited']): ?><p class="text-sm opacity-70 my-3">Weitere, nicht erkannte oder nicht darstellbare Inhalte wurden ausgelassen; lange Texte können gekürzt sein.</p><?php endif; ?>
<?php if($presentation['rows']): ?>
<div class="overflow-x-auto"><table class="table w-full"><thead><tr><th scope="col">Feld</th><th scope="col">Vorher</th><th scope="col">Nachher</th></tr></thead><tbody>
<?php foreach($presentation['rows'] as $change): ?><tr><th scope="row"><?= h($change['field']) ?></th><td style="white-space:pre-wrap;overflow-wrap:anywhere"><?= h($change['before']) ?></td><td style="white-space:pre-wrap;overflow-wrap:anywhere"><?= h($change['after']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php else: ?><p class="my-3"><?= h($presentation['available']?'Keine Änderungen an darstellbaren Feldern.':'Feldvergleich nicht verfügbar.') ?></p><?php endif; ?>
<h3 class="font-semibold mt-4">Vorher · ausgewählte Felder</h3><pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?= h($presentation['before']) ?></pre>
<h3 class="font-semibold mt-4">Nachher · ausgewählte Felder</h3><pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?= h($presentation['after']) ?></pre>
</details></section><?php endforeach; ?>
<?php if($relations!==null): require __DIR__.'/lib/native_history_relations_view.php'; endif; ?>
<?php if(count($rows)===100): ?><a href="<?= h($relationUrl(chrono_history_url($filters,(int)end($rows)['id']))) ?>">Ältere Einträge</a><?php endif; ?>
<?php chrono_page_end(); ?>
