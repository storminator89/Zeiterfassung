<?php if (($ownCorrectionDecisions??null)!==null): ?>
<details class="card bg-base-100 shadow-xl mt-6" id="own-correction-decisions">
    <summary class="card-body cursor-pointer font-semibold"><?= h($ownCorrectionDecisions->text('title')) ?></summary>
    <div class="card-body pt-0 text-sm">
        <?php if ($ownCorrectionDecisions->unavailable): ?><p role="status"><?= h($ownCorrectionDecisions->text('unavailable')) ?></p>
        <?php elseif ($ownCorrectionDecisions->result!==null): $decisions=$ownCorrectionDecisions->result; ?>
            <?php if ($decisions['recent']===[] && $decisions['undated']===[]): ?><p role="status"><?= h($ownCorrectionDecisions->text('empty')) ?></p><?php endif; ?>
            <?php foreach (['recent','undated'] as $decisionGroup): ?>
                <?php if ($decisions[$decisionGroup]!==[]): ?><h3 class="font-semibold"><?= h($ownCorrectionDecisions->text($decisionGroup)) ?></h3><ul class="space-y-2 my-3">
                <?php foreach ($decisions[$decisionGroup] as $decision): ?><li>
                    <a class="link" href="<?= h($decision['url']) ?>"><?= h($ownCorrectionDecisions->text('request')) ?> #<?= h($decision['request_id']) ?></a>
                    · <span class="badge badge-outline"><?= h($ownCorrectionDecisions->text('status_'.$decision['status'])) ?></span>
                    · <?php if ($decision['datetime']!==null): ?><time datetime="<?= h($decision['datetime']) ?>"><?= h($decision['time_label']) ?></time><?php else: ?><?= h($ownCorrectionDecisions->text('unknown_time')) ?><?php endif; ?>
                </li><?php endforeach; ?></ul><?php endif; ?>
                <?php if ($decisions['more_'.$decisionGroup]): ?><p><?= h($ownCorrectionDecisions->text('more_'.$decisionGroup)) ?></p><?php endif; ?>
            <?php endforeach; ?>
            <p class="text-xs opacity-70"><?= h($ownCorrectionDecisions->text('snapshot')) ?> <time datetime="<?= h($decisions['loaded_at']) ?>"><?= h($decisions['loaded_at']) ?></time></p>
        <?php endif; ?>
        <p class="opacity-70"><?= h($ownCorrectionDecisions->text('note')) ?></p>
        <a class="link" href="adjustments.php?request_status=all&amp;own_requests=1"><?= h($ownCorrectionDecisions->text('all')) ?></a>
    </div>
</details>
<?php endif; ?>
