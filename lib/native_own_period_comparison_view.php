<details class="card bg-base-100 shadow-xl mt-6" id="own-period-comparison" <?= $ownPeriodComparison->requested?'open':'' ?>>
    <summary class="card-body cursor-pointer font-semibold"><?= h($ownPeriodComparison->text('title')) ?></summary>
    <div class="card-body pt-0 text-sm">
        <form method="get" action="attendance.php#own-period-comparison" class="flex flex-wrap items-end gap-2">
            <label class="form-control" for="comparison-from"><span class="label-text"><?= h($ownPeriodComparison->text('from')) ?></span><input id="comparison-from" name="compare_from" type="date" min="0001-01-01" max="9999-12-31" value="<?= h($ownPeriodComparison->from) ?>" required class="input input-bordered"></label>
            <label class="form-control" for="comparison-through"><span class="label-text"><?= h($ownPeriodComparison->text('through')) ?></span><input id="comparison-through" name="compare_through" type="date" min="0001-01-01" max="9999-12-31" value="<?= h($ownPeriodComparison->through) ?>" required class="input input-bordered"></label>
            <button class="btn btn-outline" type="submit"><?= h($ownPeriodComparison->text('check')) ?></button>
        </form>
        <?php if ($ownPeriodComparison->invalid): ?><p role="status"><?= h($ownPeriodComparison->text('invalid')) ?></p>
        <?php elseif ($ownPeriodComparison->unavailable): ?><p role="status"><?= h($ownPeriodComparison->text('unavailable')) ?></p>
        <?php elseif ($ownPeriodComparison->result!==null): $comparison=$ownPeriodComparison->result; ?>
            <?php if ($comparison['totals']===null): ?><p role="status"><?= h($ownPeriodComparison->text('blocked')) ?></p><ul class="list-disc pl-5">
                <?php foreach ($comparison['issues'] as $key=>$count): if ($count===0) { continue; } ?><li><?= h(sprintf($ownPeriodComparison->text($key==='invalid'?'invalid_rows':$key),$count)) ?></li><?php endforeach; ?>
            </ul>
            <?php else: $totals=$comparison['totals']; ?><dl class="grid sm:grid-cols-2 gap-2">
                <?php foreach (['work','credit','target','difference'] as $key): ?><div><dt class="opacity-70"><?= h($ownPeriodComparison->text($key)) ?></dt><dd class="font-semibold"><?= h($ownPeriodComparison->minutes($totals[$key.'_minutes'])) ?></dd></div><?php endforeach; ?>
            </dl><p><?= h(sprintf($ownPeriodComparison->text('missing'),$totals['unrecorded_target_days'])) ?></p>
            <details><summary class="cursor-pointer font-semibold"><?= h($ownPeriodComparison->text('details')) ?></summary><div class="overflow-x-auto"><table class="table w-full"><thead><tr><th><?= h($ownPeriodComparison->text('day')) ?></th><th><?= h($ownPeriodComparison->text('work')) ?></th><th><?= h($ownPeriodComparison->text('credit')) ?></th><th><?= h($ownPeriodComparison->text('target')) ?></th><th><?= h($ownPeriodComparison->text('records')) ?></th></tr></thead><tbody>
                <?php foreach ($comparison['days'] as $day=>$values): ?><tr><td><?= h($day) ?></td><?php foreach (['work','credit','target'] as $key): ?><td><?= h($ownPeriodComparison->minutes($values[$key.'_minutes'])) ?></td><?php endforeach; ?><td><?= (int)$values['records'] ?></td></tr><?php endforeach; ?>
            </tbody></table></div></details><?php endif; ?>
            <p class="text-xs opacity-70"><?= h($ownPeriodComparison->text('snapshot')) ?> <time datetime="<?= h($comparison['loaded_at']) ?>"><?= h($comparison['loaded_at']) ?></time></p>
        <?php endif; ?>
        <p class="opacity-70"><?= h($ownPeriodComparison->text('note')) ?></p>
        <div class="flex flex-wrap gap-3"><a class="link" href="adjustments.php"><?= h($ownPeriodComparison->text('correction')) ?></a><a class="link" href="work_models.php"><?= h($ownPeriodComparison->text('model_link')) ?></a></div>
    </div>
</details>
