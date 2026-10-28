<?php // Fixed native view: only the selected public relation projection reaches this fragment. ?>
<section class="card bg-base-100 shadow-xl p-6 mb-6" aria-labelledby="history-relations-title">
<h2 class="text-xl font-semibold mb-4" id="history-relations-title">Bezüge dieser Treffer</h2>
<p class="text-sm mb-4">Begrenzte aktuelle Beziehungen, gegebenenfalls außerhalb der Ankerfilter. Kein Nachweis einer Ursache oder vollständigen Historie.</p>
<?php if(in_array(true,$relations['truncated'],true)): ?><p class="alert alert-warning mb-4" role="status">Bezüge gekürzt; fehlende Zuordnungen bleiben ungeklärt.</p><?php endif; ?>
<?php if(!$relations['sessions']&&!$relations['events']&&!$relations['requests']): ?><p role="status">Keine darstellbaren Bezüge. Das belegt nicht, dass keine weiteren bestehen.</p><?php endif; ?>
<?php if($relations['sessions']): ?>
<details class="mb-4"><summary>Sitzungen</summary><div class="overflow-x-auto"><table class="table w-full"><thead><tr><th scope="col">Sitzung</th><th scope="col">Buchung</th></tr></thead><tbody>
<?php foreach($relations['sessions'] as $row): ?><tr id="history-session-<?= h($row['id']) ?>"><th scope="row">#<?= h($row['id']) ?></th><td>#<?= h($row['record_id']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></details>
<?php endif; ?>
<?php if($relations['events']): ?>
<details class="mb-4"><summary>Ereignisse</summary><div class="overflow-x-auto"><table class="table w-full"><thead><tr><th scope="col">Ereignis</th><th scope="col">Art / Zeitpunkt</th><th scope="col">Sitzung</th></tr></thead><tbody>
<?php foreach($relations['events'] as $row): ?><tr id="history-event-<?= h($row['id']) ?>"><th scope="row">#<?= h($row['id']) ?><code class="block break-all"><?= h($row['event_id']) ?></code></th><td><?= h($row['kind_label']) ?><span class="block"><?= h($row['received_label']) ?></span></td><td><a class="link" href="#history-session-<?= h($row['session_id']) ?>">#<?= h($row['session_id']) ?></a></td></tr><?php endforeach; ?>
</tbody></table></div></details>
<?php endif; ?>
<?php if($relations['requests']): ?>
<details><summary>Korrekturanträge</summary><div class="overflow-x-auto"><table class="table w-full"><thead><tr><th scope="col">Antrag</th><th scope="col">Buchung</th><th scope="col">Status / Art</th></tr></thead><tbody>
<?php foreach($relations['requests'] as $row): ?><tr id="history-request-<?= h($row['id']) ?>"><th scope="row">#<?= h($row['id']) ?></th><td><?= h($row['record_id']===null?'ungeklärt':'#'.$row['record_id']) ?></td><td><?= h(['pending'=>'Offen','approved'=>'Freigegeben','rejected'=>'Abgelehnt','withdrawn'=>'Zurückgenommen'][$row['status']]) ?> · <?= $row['cancel']?'Storno':'Korrektur' ?></td></tr><?php endforeach; ?>
</tbody></table></div></details>
<?php endif; ?>
</section>
