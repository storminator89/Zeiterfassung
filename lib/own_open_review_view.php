<?php
// Pure escaped facts from the current-authorized own-row DTO; no mutation control.
if ($ownOpenReviewUnavailable) : ?>
<p class="text-sm opacity-70 mb-4" role="status"><?= h(OWN_OPEN_REVIEW_UNAVAILABLE) ?></p>
<?php elseif ($ownOpenReview !== null && $ownOpenReview['count'] > 0) : ?>
<section class="card bg-base-100 shadow-xl mb-6" aria-labelledby="ownOpenReviewTitle">
    <div class="card-body p-4 sm:p-6">
        <h2 id="ownOpenReviewTitle" class="card-title"><?= h(OWN_OPEN_REVIEW_TITLE) ?></h2>
        <p class="text-sm"><?= h(sprintf(OWN_OPEN_REVIEW_COUNT, $ownOpenReview['count'], $ownOpenReview['cutoff_day'])) ?></p>
        <p class="text-sm opacity-70"><?= h(OWN_OPEN_REVIEW_NOTE) ?></p>
        <p class="text-sm opacity-70"><?= h(OWN_OPEN_REVIEW_ACTION) ?></p>
        <p class="text-xs opacity-70"><?= h(OWN_OPEN_REVIEW_SNAPSHOT) ?> <time datetime="<?= h($ownOpenReview['loaded_at']) ?>"><?= h($ownOpenReview['loaded_at']) ?></time></p>
        <ul class="space-y-2 my-3">
            <?php foreach ($ownOpenReview['rows'] as $row) :
                try { $ownOpenCorrectionId = correction_selection_id($row['record_id']); }
                catch (InvalidArgumentException) { $ownOpenCorrectionId = null; }
            ?>
            <li class="text-sm"><?= h($row['start_label'] ?? OWN_OPEN_REVIEW_UNKNOWN_START) ?> · #<?= h($row['record_id']) ?>
                <?php if ($ownOpenCorrectionId !== null) : ?><a class="link link-primary" href="adjustments.php?record_id=<?= h(rawurlencode($row['record_id'])) ?>"><?= h(OWN_OPEN_REVIEW_LINK) ?></a><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($ownOpenReview['limited']) : ?><p class="text-sm opacity-70"><?= h(OWN_OPEN_REVIEW_LIMIT) ?></p><?php endif; ?>
        <a class="link text-sm" href="attendance.php"><?= h(OWN_OPEN_REVIEW_REFRESH) ?></a>
    </div>
</section>
<?php endif; ?>
