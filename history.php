<?php
require_once __DIR__.'/config.php';require_once __DIR__.'/lib/app_ui.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php');exit; }
require_once __DIR__.'/lib/history_filters.php';
$user=(int)$_SESSION['user_id'];$role=$_SESSION['role'];$filters=chrono_history_filters($_GET);$values=$filters['values'];$rows=[];
if ($filters['errors']) { http_response_code(400); }
else { $query=chrono_history_query($filters,$user,$role);$s=$conn->prepare($query['sql']);$s->execute($query['params']);$rows=$s->fetchAll(PDO::FETCH_ASSOC); }
chrono_page('Änderungshistorie');
?>
<p>Erfassung, Korrekturantrag, Entscheidung und Arbeitszeitmodell werden mit Zeitpunkt, Akteur und Vorher-/Nachher-Werten protokolliert. Baseline-Einträge dokumentieren den Stand beim Upgrade; frühere Änderungen sind nicht rekonstruierbar.</p>
<section class="card bg-base-100 shadow-xl p-6 mb-6">
<h2 class="text-xl font-semibold mb-4">Historie filtern</h2>
<form method="get" action="history.php">
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div><label class="block text-sm font-semibold mt-4 mb-2" for="history_user">Person-ID (optional)</label><input class="input input-bordered w-full" type="number" min="1" step="1" id="history_user" name="user_id" value="<?= h($values['user_id']) ?>"></div>
<div><label class="block text-sm font-semibold mt-4 mb-2" for="history_from">Änderung von (optional)</label><input class="input input-bordered w-full" type="date" id="history_from" name="from" value="<?= h($values['from']) ?>"></div>
<div><label class="block text-sm font-semibold mt-4 mb-2" for="history_to">Änderung bis einschließlich (optional)</label><input class="input input-bordered w-full" type="date" id="history_to" name="to" value="<?= h($values['to']) ?>"></div>
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
<?php if(!$filters['errors']&&$filters['ids']['before']!==null): ?><p class="mb-6"><a href="<?= h(chrono_history_url($filters)) ?>">Neueste Treffer mit diesen Filtern</a></p><?php endif; ?>
<?php foreach($rows as $r): ?><section class="card bg-base-100 shadow-xl p-6 mb-6"><p class="text-sm font-semibold opacity-70 mb-2">#<?= (int)$r['id'] ?> · <?= h(attendance_local((int)$r['occurred_at'])) ?></p><h2 class="text-xl font-semibold mb-4"><?= h(chrono_history_entities()[$r['entity']]??$r['entity']) ?> #<?= (int)$r['record_id'] ?> · <?= h($r['operation']) ?></h2><p><?= h($r['actor']) ?> · <?= h($r['reason']) ?></p><details><summary>Vorher und nachher ansehen</summary><h3>Vorher</h3><pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?= h($r['before_json']??'Kein vorheriger Eintrag') ?></pre><h3>Nachher</h3><pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?= h($r['after_json']??'Storniert') ?></pre></details></section><?php endforeach; ?>
<?php if(count($rows)===100): ?><a href="<?= h(chrono_history_url($filters,(int)end($rows)['id'])) ?>">Ältere Einträge</a><?php endif; ?>
<?php chrono_page_end(); ?>
