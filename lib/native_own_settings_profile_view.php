<?php
// Fixed server-side display partial; $ownSettingsProfile is the minimal shared DTO.
?>
<details class="mt-8 border border-base-300 rounded-box p-3">
    <summary class="cursor-pointer font-semibold"><?= h($ownSettingsProfile->text('title')) ?></summary>
    <div class="mt-4">
        <?php if (!$ownSettingsProfile->available) : ?>
            <p role="status"><?= h($ownSettingsProfile->text('unavailable')) ?></p>
        <?php else : ?>
            <p class="text-sm opacity-70 mb-4"><?= h($ownSettingsProfile->text('as_of')) ?> <?= h($ownSettingsProfile->date) ?> · Europe/Berlin</p>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                <div><dt class="font-semibold"><?= h($ownSettingsProfile->text('department')) ?></dt><dd><?= h($ownSettingsProfile->referenceName('department')) ?></dd></div>
                <div><dt class="font-semibold"><?= h($ownSettingsProfile->text('supervisor')) ?></dt><dd><?= h($ownSettingsProfile->referenceName('supervisor')) ?></dd></div>
            </dl>
            <p class="text-sm opacity-70 mb-4"><?= h($ownSettingsProfile->text('assignment_note')) ?></p>
            <?php if ($ownSettingsProfile->model === null) : ?>
                <p><?= h($ownSettingsProfile->text('no_model')) ?></p>
            <?php else : ?>
                <p class="font-semibold"><?= h($ownSettingsProfile->text('model_from')) ?> <?= h($ownSettingsProfile->model['valid_from']) ?></p>
                <div class="overflow-x-auto mt-2">
                    <table class="table w-full">
                        <caption class="text-left"><?= h($ownSettingsProfile->text('weekly_minutes')) ?></caption>
                        <thead><tr><th><?= h($ownSettingsProfile->text('weekday')) ?></th><th><?= h($ownSettingsProfile->text('duration')) ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($ownSettingsProfile->model['weekday_minutes'] as $index => $minutes) : ?>
                                <tr><td><?= h($ownSettingsProfile->weekday($index)) ?></td><td><?= h($ownSettingsProfile->duration($minutes)) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr><th scope="row"><?= h($ownSettingsProfile->text('weekly_total')) ?></th><td><?= h($ownSettingsProfile->weeklyDuration()) ?></td></tr></tfoot>
                    </table>
                </div>
            <?php endif; ?>
            <p class="text-sm opacity-70 mt-4"><?= h($ownSettingsProfile->text('model_note')) ?> <a href="work_models.php"><?= h($ownSettingsProfile->text('models_link')) ?></a></p>
        <?php endif; ?>
    </div>
</details>
