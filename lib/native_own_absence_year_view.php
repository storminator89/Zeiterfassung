<?php
/** Read-only existing details/table patterns; every dynamic string escaped here. */
function chrono_native_own_absence_year_view(?array $data,string $state,array $copy,array $context,string $currentYear): void
{
    $e=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $year=$data['year']??$currentYear;
    ?>
    <details id="own-absence-year" class="mb-6 text-sm border border-base-300 rounded-box p-3" <?= $state!=='idle'?'open':'' ?>>
        <summary class="cursor-pointer font-semibold"><?= $e($copy['title']) ?></summary>
        <p class="mt-3"><?= $e($copy['note']) ?></p><p class="mt-2 opacity-70"><?= $e($copy['limit']) ?></p>
        <?php if ($state==='idle'): ?>
            <a class="btn btn-outline btn-sm mt-3" href="<?= $e(chrono_own_absence_year_link($currentYear,1,$context)) ?>"><?= $e($copy['load']) ?></a>
        <?php else: ?>
            <form method="get" action="dashboard.php#own-absence-year" class="flex flex-wrap items-end gap-2 mt-3">
                <?php foreach ($context as $key=>$value): ?><input type="hidden" name="<?= $e($key) ?>" value="<?= $e($value) ?>"><?php endforeach; ?>
                <label for="own-absence-year-input"><?= $e($copy['year']) ?><input id="own-absence-year-input" type="number" name="absence_year" min="1" max="9999" step="1" required value="<?= $e($year) ?>" class="input input-bordered input-sm w-28"></label>
                <button type="submit" class="btn btn-outline btn-sm"><?= $e($copy['show']) ?></button>
            </form>
            <?php if ($state==='invalid'||$state==='unavailable'): ?><p role="status" class="mt-3"><?= $e($copy[$state]) ?></p>
            <?php elseif ($data!==null): ?>
                <p class="mt-3 opacity-70"><?= $e($copy['loaded_at'].': '.$data['loaded_at']) ?></p>
                <?php if ($data['state']!=='current'): ?><p role="status" class="alert alert-warning mt-3"><?= $e($copy['quality']) ?></p><?php endif; ?>
                <?php if ($data['unknown_date_records']>0): ?><p class="mt-3"><?= $e($copy['unknown_note']) ?> (<?= $e($data['unknown_date_records']) ?>)</p><?php endif; ?>
                <div class="overflow-x-auto mt-3"><table class="table w-full"><thead><tr><?php foreach (['kind','phase','records','days','minutes'] as $key): ?><th><?= $e($copy[$key]) ?></th><?php endforeach; ?></tr></thead><tbody>
                    <?php foreach ($data['summary'] as $kind=>$phases): foreach ($phases as $phase=>$values): ?><tr><td><?= $e($copy[$kind]) ?></td><td><?= $e($copy[$phase]) ?></td><td><?= $e($values['records']) ?></td><td><?= $e($values['positive_days']??$copy[$data['state']]) ?></td><td><?= $e($values['minutes']??$copy[$data['state']]) ?></td></tr><?php endforeach; endforeach; ?>
                </tbody></table></div>
                <h3 class="font-semibold mt-4"><?= $e($copy['details']) ?> (<?= $e($data['total_records']) ?>)</h3>
                <?php if ($data['rows']===[]): ?><p class="mt-2"><?= $e($copy['empty']) ?></p><?php else: ?>
                    <div class="overflow-x-auto mt-2"><table class="table w-full"><thead><tr><?php foreach (['record','date','kind','phase','minutes','state'] as $key): ?><th><?= $e($copy[$key]) ?></th><?php endforeach; ?></tr></thead><tbody>
                    <?php foreach ($data['rows'] as $row): ?><tr><td><?php if ($row['id']!==null): ?><a class="link" href="<?= $e('adjustments.php?record_id='.rawurlencode($row['id'])) ?>" aria-label="<?= $e($copy['review'].' #'.$row['id']) ?>">#<?= $e($row['id']) ?></a><?php else: ?>—<?php endif; ?></td><td><?= $e($row['day']??$copy['unknown']) ?></td><td><?= $e($copy[$row['kind']]) ?></td><td><?= $e($copy[$row['phase']]) ?></td><td><?= $e($row['minutes']??$copy['incomplete']) ?></td><td><?= $e($copy[$row['state']]) ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
                <nav class="flex flex-wrap gap-3 mt-3" aria-label="<?= $e($copy['details']) ?>">
                    <?php if ($data['page']>1): ?><a class="link" href="<?= $e(chrono_own_absence_year_link($year,$data['page']-1,$context)) ?>"><?= $e($copy['previous']) ?></a><?php endif; ?>
                    <span><?= $e($copy['page'].' '.$data['page'].' '.$copy['of'].' '.$data['pages']) ?></span>
                    <?php if ($data['page']<$data['pages']): ?><a class="link" href="<?= $e(chrono_own_absence_year_link($year,$data['page']+1,$context)) ?>"><?= $e($copy['next']) ?></a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </details>
    <?php
}
