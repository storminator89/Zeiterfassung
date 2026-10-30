<?php
// Presentation only: current-authorized minimal DTO, no query or request parsing.
if ($teamAbsenceUnavailable) : ?>
<div class="alert alert-warning mb-4" role="status"><span><?= h(TEAM_ABSENCE_TITLE) ?>: <?= h(TEAM_ABSENCE_UNAVAILABLE) ?></span></div>
<?php elseif ($teamAbsenceWeek !== null) :
$teamAbsenceUrl = static fn (string $day, int $page = 1): string => 'supervisor.php?'.http_build_query([
    'lang' => $lang, 'user_search' => $userSearch, 'page' => $planReportPage, 'team_date' => $day, 'team_page' => $page,
] + ($tablePeriodQuery ?? []), '', '&', PHP_QUERY_RFC3986);
?>
<section class="card bg-base-100 shadow-xl mb-8" aria-labelledby="nativeTeamAbsenceTitle">
    <div class="card-body">
        <h3 id="nativeTeamAbsenceTitle" class="card-title"><?= h(TEAM_ABSENCE_TITLE) ?></h3>
        <p class="text-sm opacity-70"><?= h(TEAM_ABSENCE_NOTE) ?></p>
        <p class="text-sm"><?= h($teamAbsenceWeek['from']) ?> – <?= h($teamAbsenceWeek['through']) ?> · Europe/Berlin</p>
        <form method="get" action="supervisor.php" class="flex flex-wrap gap-4 my-4">
            <input type="hidden" name="lang" value="<?= h($lang) ?>">
            <input type="hidden" name="user_search" value="<?= h($userSearch) ?>">
            <input type="hidden" name="page" value="<?= h($planReportPage) ?>">
            <?php foreach (($tablePeriodQuery ?? []) as $name => $value) : ?><input type="hidden" name="<?= h($name) ?>" value="<?= h($value) ?>"><?php endforeach; ?>
            <div class="form-control"><label for="native-team-absence-date"><?= h(TEAM_ABSENCE_DATE) ?></label><input class="input input-bordered" type="date" id="native-team-absence-date" name="team_date" value="<?= h($teamAbsenceWeek['from']) ?>"></div>
            <button class="btn btn-primary self-end"><?= h(TEAM_ABSENCE_SHOW) ?></button>
        </form>
        <nav class="flex flex-wrap gap-3 mb-4" aria-label="<?= h(TEAM_ABSENCE_NAV) ?>">
            <a class="btn btn-outline" href="<?= h($teamAbsenceUrl($teamAbsenceWeek['previous'])) ?>"><?= h(TEAM_ABSENCE_PREVIOUS) ?></a>
            <a class="btn btn-outline" href="<?= h($teamAbsenceUrl($teamAbsenceWeek['today'])) ?>"><?= h(TEAM_ABSENCE_TODAY) ?></a>
            <a class="btn btn-outline" href="<?= h($teamAbsenceUrl($teamAbsenceWeek['next'])) ?>"><?= h(TEAM_ABSENCE_NEXT) ?></a>
        </nav>
        <?php if ($teamAbsenceWeek['incomplete'] || $teamAbsenceWeek['truncated']) : ?><p class="alert alert-warning mb-4" role="status"><?= h(TEAM_ABSENCE_INCOMPLETE) ?></p><?php endif; ?>
        <div class="overflow-x-auto"><table class="table table-zebra w-full">
            <caption class="text-sm text-left mb-2"><?= h(TEAM_ABSENCE_LEGEND) ?></caption>
            <thead><tr><th scope="col"><?= h(TABLE_HEADER_USERNAME) ?></th><?php foreach ($teamAbsenceWeek['days'] as $day) : ?><th scope="col"><time datetime="<?= h($day) ?>"><?= h($day) ?></time></th><?php endforeach; ?></tr></thead>
            <tbody><?php foreach ($teamAbsenceWeek['people'] as $person) : ?><tr><th scope="row"><?= h($person['username']) ?></th><?php foreach ($person['days'] as $absent) : ?><td><?= $absent ? h(TEAM_ABSENCE_MARKER) : '—' ?></td><?php endforeach; ?></tr><?php endforeach; ?>
            <?php if ($teamAbsenceWeek['people'] === []) : ?><tr><td colspan="8"><?= h(TEAM_STATUS_EMPTY) ?></td></tr><?php endif; ?></tbody>
        </table></div>
        <p class="text-sm my-3"><?= h(TEAM_ABSENCE_PAGE) ?> <?= h($teamAbsenceWeek['page']) ?> / <?= h($teamAbsenceWeek['pages']) ?></p>
        <nav class="flex flex-wrap gap-3" aria-label="<?= h(TEAM_ABSENCE_PEOPLE) ?>">
            <?php if ($teamAbsenceWeek['page'] > 1) : ?><a class="btn btn-outline" href="<?= h($teamAbsenceUrl($teamAbsenceWeek['from'], $teamAbsenceWeek['page'] - 1)) ?>"><?= h(TEAM_ABSENCE_PEOPLE_PREVIOUS) ?></a><?php endif; ?>
            <?php if ($teamAbsenceWeek['page'] < $teamAbsenceWeek['pages']) : ?><a class="btn btn-outline" href="<?= h($teamAbsenceUrl($teamAbsenceWeek['from'], $teamAbsenceWeek['page'] + 1)) ?>"><?= h(TEAM_ABSENCE_PEOPLE_NEXT) ?></a><?php endif; ?>
        </nav>
    </div>
</section>
<?php endif; ?>
