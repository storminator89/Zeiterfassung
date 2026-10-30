<details class="card bg-base-100 shadow-xl mb-6" id="own-month-review" <?= $ownMonthReview->requested?'open':'' ?>>
    <summary class="card-body cursor-pointer font-semibold"><?= h($ownMonthReview->text('title')) ?></summary>
    <div class="card-body pt-0 text-sm">
        <form method="get" action="attendance.php#own-month-review" class="flex flex-wrap items-end gap-2">
            <label class="form-control" for="own-review-month"><span class="label-text"><?= h($ownMonthReview->text('month')) ?></span><input id="own-review-month" name="month_review" type="month" min="0001-01" max="9999-12" value="<?= h($ownMonthReview->month) ?>" required class="input input-bordered"></label>
            <button class="btn btn-outline" type="submit"><?= h($ownMonthReview->text('check')) ?></button>
        </form>
        <?php if ($ownMonthReview->invalid): ?><p role="status"><?= h($ownMonthReview->text('invalid')) ?></p><?php elseif ($ownMonthReview->unavailable): ?><p role="status"><?= h($ownMonthReview->text('unavailable')) ?></p><?php elseif ($ownMonthReview->result!==null): $monthReview=$ownMonthReview->result;$monthCounts=$monthReview['counts']; ?>
        <p><?= h(sprintf($ownMonthReview->text('open_counts'),$monthCounts['open_selected'],$monthCounts['open_earlier'],$monthCounts['open_unclear'])) ?></p>
        <p><?= h(sprintf($ownMonthReview->text('pending_counts'),$monthCounts['pending_selected'],$monthCounts['pending_unclear'])) ?></p>
        <?php $closedCounts=$monthReview['closed_counts']; if ($closedCounts!==null): ?><p><?= h(sprintf($ownMonthReview->text('closed_counts'),$closedCounts['selected'],$closedCounts['unclear'])) ?></p><?php endif; ?>
        <?php $monthFindings=$monthReview['findings']??null; if ($monthFindings!==null): ?><p><?= h($ownMonthReview->text('finding_order')) ?></p>
        <?php if ($monthFindings['page']>$monthFindings['total_pages']): ?><p role="status"><?= h($ownMonthReview->text('finding_outside')) ?></p><?php endif; ?><?php endif; ?>
        <?php if (array_sum($monthCounts)+array_sum($closedCounts??[])===0): ?><p role="status"><?= h($ownMonthReview->text('empty')) ?></p><?php else: ?>
        <?php if ($monthFindings===null): ?><p><?= h($ownMonthReview->text('samples')) ?></p><?php endif; ?>
        <ul class="list-disc pl-5">
            <?php foreach ($monthFindings['rows']??$monthReview['samples'] as $sample): ?><li><?= h($ownMonthReview->text('kind_'.$sample['kind'])) ?> #<?= h($sample['id']) ?> · <?= h($ownMonthReview->text($sample['relation'])) ?><?php if ($sample['kind']!=='request'): ?> · <a class="link" href="adjustments.php?record_id=<?= h(rawurlencode($sample['id'])) ?>"><?= h($ownMonthReview->text('correction')) ?></a><?php else: ?> · <a class="link" href="adjustments.php?request_status=pending&amp;request_id=<?= h(rawurlencode($sample['id'])) ?>"><?= h($ownMonthReview->text('pending_link')) ?></a><?php endif; ?></li><?php endforeach; ?>
        </ul><?php endif; ?>
        <?php if ($monthFindings!==null): ?>
        <nav class="flex flex-wrap items-center gap-3 my-3" aria-label="<?= h($ownMonthReview->text('finding_navigation')) ?>">
            <span><?= h(sprintf($ownMonthReview->text('finding_page'),$monthFindings['page'],$monthFindings['total_pages'],$monthFindings['total'])) ?></span>
            <?php if ($monthFindings['page']>1): ?><a class="btn btn-outline btn-sm" href="<?= h($ownMonthReview->findingUrl(1)) ?>"><?= h($ownMonthReview->text('finding_first')) ?></a><?php endif; ?>
            <?php if ($monthFindings['page']>1 && $monthFindings['page']<=$monthFindings['total_pages']): ?><a class="btn btn-outline btn-sm" href="<?= h($ownMonthReview->findingUrl($monthFindings['page']-1)) ?>"><?= h($ownMonthReview->text('finding_previous')) ?></a><?php endif; ?>
            <?php if ($monthFindings['page']<$monthFindings['total_pages']): ?><a class="btn btn-outline btn-sm" href="<?= h($ownMonthReview->findingUrl($monthFindings['page']+1)) ?>"><?= h($ownMonthReview->text('finding_next')) ?></a><?php endif; ?>
        </nav><p class="opacity-70"><?= h($ownMonthReview->text('finding_live')) ?></p>
        <?php else: ?>
            <?php if ($monthReview['limited']): ?><p><?= h($ownMonthReview->text('limited')) ?></p><?php endif; ?>
            <?php if ($ownMonthReview->findingsAvailable): ?><a class="btn btn-outline btn-sm my-3" href="<?= h($ownMonthReview->findingUrl(1)) ?>"><?= h($ownMonthReview->text('finding_all')) ?></a><?php endif; ?>
        <?php endif; ?>
        <p class="text-xs opacity-70"><?= h($ownMonthReview->text('snapshot')) ?> <time datetime="<?= h($monthReview['loaded_at']) ?>"><?= h($monthReview['loaded_at']) ?></time></p>
        <?php endif; ?>
        <p class="opacity-70"><?= h($ownMonthReview->text('note')) ?></p>
        <div class="flex flex-wrap gap-3"><a class="link" href="adjustments.php?request_status=pending"><?= h($ownMonthReview->text('pending_link')) ?></a><a class="link" href="attendance.php#timeRecordsContainer"><?= h($ownMonthReview->text('review_link')) ?></a></div>
    </div>
</details>
