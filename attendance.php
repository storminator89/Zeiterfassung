<?php
require_once __DIR__.'/config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user=(int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD']==='GET' && ($_GET['format']??'')==='status') {
    attendance_json(function() use($conn,$user) {
        $conn->beginTransaction();
        try {
            $s=$conn->prepare('SELECT id,startzeit,endzeit,pause,absence_kind,planned_minutes FROM zeiterfassung WHERE user_id=? ORDER BY id DESC LIMIT 30');$s->execute([$user]);$records=$s->fetchAll(PDO::FETCH_ASSOC);$s->closeCursor();
            // Native presentation metadata only: raw recent rows and public
            // API/extension contracts stay unchanged. Full effective context
            // and state share this read snapshot, including off-page conflicts.
            $s=$conn->prepare('SELECT * FROM zeiterfassung WHERE user_id=?');$s->execute([$user]);$context=$s->fetchAll(PDO::FETCH_ASSOC);$s->closeCursor();
            $conflicts=report_absence_conflicts($context);
            $today=booking_local_date(time());[$weekStart,$weekEnd]=report_week_bounds($today->format('Y-m-d'));
            $finality=['all'=>$conflicts!==[],
                'week'=>report_conflicts_in_period($conflicts,$weekStart,$weekEnd),
                'month'=>report_conflicts_in_period($conflicts,$today->format('Y-m-01'),$today->format('Y-m-t')),
                'year'=>report_conflicts_in_period($conflicts,$today->format('Y-01-01'),$today->format('Y-12-31'))];
            $status=attendance_status($conn,$user);
            $snapshot=['success'=>true,'records'=>$records,'report_finality'=>$finality,
                'expected_record'=>$status['record_id']===null?null:(string)$status['record_id']]+$status;
            $conn->commit();return $snapshot;
        } catch(Throwable $e) { if($conn->inTransaction()) { $conn->rollBack(); } throw $e; }
    });
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    attendance_json(fn() => attendance_transition($conn,$user,'web:'.$user,$_POST['event_id']??'',$_POST['action']??'',($_POST['confirm_end_pause']??'')==='1',attendance_expected_record($_POST),true));
}
$strictAppUi = true;
require __DIR__ . '/header.php';
$state = $attendanceState;
?>
<main class="container mx-auto px-4 py-8 chrono-page">
    <div class="grid md:grid-cols-3 gap-6 items-start">
        <div class="md:col-span-2 min-w-0">
            <section class="card bg-base-100 shadow-xl">
                <div class="card-body p-4 sm:p-6">
                    <header class="flex items-center gap-3 mb-4">
                        <img src="<?= h($kolibri_icon) ?>" alt="" class="w-10 h-10">
                        <h1 class="text-xl font-bold text-primary mb-0"><?= TITLE ?></h1>
                    </header>
                    <p class="text-lg">Willkommen zurück, <?= h($_SESSION['username']) ?>!</p>
                    <div id="status" data-attendance-status class="text-2xl font-bold mt-2" role="status"><?= h($attendanceLabels[$state]) ?></div>
                    <p id="message" class="text-sm opacity-70" role="status" aria-live="polite">Jede Buchung wird mit Serverzeit bestätigt.</p>
                    <div id="actions" data-state="<?= h($state) ?>" class="flex flex-wrap gap-3 mt-3 mb-5" aria-label="Arbeitszeit buchen">
                        <button type="button" data-action="start" class="btn btn-primary" <?= $state !== 'absent' ? 'hidden' : '' ?>><i class="fas fa-sign-in-alt"></i>Kommen</button>
                        <button type="button" data-action="pause_start" class="btn btn-outline" <?= $state !== 'working' ? 'hidden' : '' ?>><i class="fas fa-pause"></i>Pause beginnen</button>
                        <button type="button" data-action="pause_end" class="btn btn-outline" <?= $state !== 'paused' ? 'hidden' : '' ?>><i class="fas fa-play"></i>Pause beenden</button>
                        <button type="button" data-action="end" class="btn btn-secondary" <?= $state === 'absent' ? 'hidden' : '' ?>><i class="fas fa-sign-out-alt"></i>Gehen</button>
                    </div>
                    <details class="text-sm mb-4">
                        <summary class="cursor-pointer font-semibold">Tatsächliche Pausen</summary>
                        <p class="mt-2">Start und Ende jeder Pause werden erfasst. Mindestpausen werden nicht automatisch abgezogen. Bitte die betrieblichen Pausenregeln beachten. Beim Gehen während einer Pause werden Pause und Arbeitstag nach Ihrer Bestätigung gemeinsam beendet.</p>
                    </details>
                    <a class="link link-primary text-sm" href="adjustments.php">Buchung vergessen oder Zeit korrigieren?</a>

                    <form id="absence-form" method="post" action="save.php" class="space-y-5 mt-6 border-t border-base-200 pt-5">
                        <?= csrf_field() ?>
                        <fieldset>
                            <legend class="text-lg font-semibold mb-3">Ereignistyp</legend>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="flex items-center p-3 border border-base-300 rounded-lg hover:bg-base-200 cursor-pointer">
                                    <input type="radio" name="beschreibung" value="Urlaub" class="radio radio-primary mr-3" checked required>
                                    <i class="fas fa-umbrella-beach text-xl mr-2"></i><span><?= EVENT_VACATION ?></span>
                                </label>
                                <label class="flex items-center p-3 border border-base-300 rounded-lg hover:bg-base-200 cursor-pointer">
                                    <input type="radio" name="beschreibung" value="Krankheit" class="radio radio-primary mr-3" required>
                                    <i class="fas fa-stethoscope text-xl mr-2"></i><span><?= EVENT_SICK ?></span>
                                </label>
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend class="text-lg font-semibold mb-3">Datumsbereich</legend>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div><label for="urlaubStart" class="block text-sm font-semibold mb-2">Von</label><input type="date" id="urlaubStart" name="urlaubStart" class="input input-bordered w-full" required></div>
                                <div><label for="urlaubEnde" class="block text-sm font-semibold mb-2">Bis</label><input type="date" id="urlaubEnde" name="urlaubEnde" class="input input-bordered w-full" required></div>
                            </div>
                        </fieldset>
                        <p class="text-sm opacity-70">Urlaub und Krankheit erhalten die Sollminuten Ihres Arbeitszeitmodells. Freie Tage und hinterlegte Feiertage werden ausgelassen. Gutschriften sind keine gestempelten Arbeitszeiten.</p>
                        <p id="absence-message" class="text-sm" role="status" aria-live="polite"></p>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-plus"></i>Abwesenheit erfassen</button>
                    </form>
                </div>
            </section>
        </div>
        <aside class="min-w-0">
            <section class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow duration-300">
                <div class="card-body p-4 sm:p-6">
                    <h2 class="card-title text-2xl mb-4"><i class="fas fa-chart-bar mr-2"></i><?= STATISTICS_WORKING_TIMES ?></h2>
                    <div class="stats stats-vertical shadow w-full">
                        <div class="stat"><div class="stat-figure text-primary"><i class="fas fa-calendar-day fa-2x"></i></div><div class="stat-title"><?= TABLE_HEADER_WORKING_DAYS ?></div><div class="stat-value"><?= (int)$workingDaysThisMonth ?></div><div class="stat-desc"><?= h($currentMonthName) ?></div></div>
                        <div class="stat"><div class="stat-figure text-primary"><i class="fas fa-clock fa-2x"></i></div><div class="stat-title"><?= TABLE_HEADER_TOTAL_OVERTIME ?></div><div data-report-period="all" data-report-presentation="<?= $reportTotalUnresolved ? 'unresolved' : 'current' ?>" class="stat-value <?= $reportTotalUnresolved ? '' : ($totalOverHours > 0 ? 'text-success' : 'text-error') ?>"><?= h($totalOverHoursFormatted) ?></div><p class="text-sm opacity-70 mt-2" style="white-space: normal;"><?= h(DASHBOARD_OVERTIME_INFO) ?></p></div>
                        <div class="stat"><div class="stat-figure text-primary"><i class="fas fa-business-time fa-2x"></i></div><div class="stat-title"><?= TABLE_HEADER_REGULAR_WORKING_HOURS ?></div><div class="stat-value"><?= h(home_daily_hours($conn, (int)$user, date('Y-m-d'))) ?></div><div class="stat-desc"><?= LABEL_HOURS_PER_DAY ?></div></div>
                    </div>
                    <?php if (!empty($feiertageDieseWoche)): ?><div class="alert alert-info mt-4"><div><h3 class="font-bold"><?= HOLIDAYS_THIS_WEEK ?></h3><?php foreach($feiertageDieseWoche as $holiday): ?><p class="text-sm"><?= h(date('d.m.Y', strtotime($holiday['datum']))) ?> · <?= h($holiday['name']) ?></p><?php endforeach; ?></div></div><?php endif; ?>
                    <a href="dashboard.php" class="link link-primary mt-4">Zur Auswertung</a>
                </div>
            </section>
        </aside>
    </div>
    <section id="timeRecordsContainer" class="mt-8" aria-label="Erfasste Arbeitszeiten"><p role="status">Arbeitszeiten werden geladen …</p></section>
</main>
</div>
</body>
</html>
