<?php
include 'config.php';
include 'functions.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/work_location.php';

if (!isset($_SESSION['user_id'])) {
    die("Nicht angemeldet");
}

$user_id = $_SESSION['user_id'];

// Fetch the most recent time record
$stmt = $conn->prepare("SELECT * FROM zeiterfassung WHERE user_id = ? AND absence_kind IS NULL AND COALESCE(beschreibung,'') NOT IN ('Urlaub','Krankheit','Feiertag') ORDER BY (endzeit IS NULL) DESC, id DESC LIMIT 1");
$stmt->execute([$user_id]);
$latestRecord = $stmt->fetch(PDO::FETCH_ASSOC);

if ($latestRecord):
?>
<div class="card-body">
    <h3 class="card-title text-2xl mb-4"><i class="fas fa-clock mr-2"></i><?= LATEST_TIME_RECORD ?></h3>
    <div class="bg-base-200 p-6 rounded-lg shadow-inner">
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2 bg-primary text-primary-content p-4 rounded-lg mb-4">
                <p class="text-lg font-semibold"><?= TABLE_HEADER_DURATION ?>:</p>
                <p class="text-3xl font-bold"><?= calculateDuration($latestRecord['startzeit'], $latestRecord['endzeit'], $latestRecord['pause']) ?></p>
            </div>
            <div>
                <p class="font-semibold"><?= TABLE_HEADER_START_TIME ?>:</p>
                <p><?= date('d.m.Y H:i', strtotime($latestRecord['startzeit'])) ?></p>
            </div>
            <div>
                <p class="font-semibold"><?= TABLE_HEADER_END_TIME ?>:</p>
                <p><?= $latestRecord['endzeit'] ? date('d.m.Y H:i', strtotime($latestRecord['endzeit'])) : '-' ?></p>
            </div>
            <div>
                <p class="font-semibold"><?= TABLE_HEADER_BREAK ?>:</p>
                <p><?= h($latestRecord['pause']) ?> <?= LABEL_MINUTES ?></p>
            </div>
            <div>
                <p class="font-semibold"><?= TABLE_HEADER_LOCATION ?>:</p>
                <p><?= h(work_location_label($latestRecord['standort'])) ?></p>
            </div>
            <div class="col-span-2">
                <p class="font-semibold"><?= TABLE_HEADER_COMMENT ?>:</p>
                <p><?= htmlspecialchars($latestRecord['beschreibung']) ?></p>
            </div>
        </div>
    </div>
</div>
<?php
else:
    echo "<p>Kein Zeiteintrag gefunden.</p>";
endif;
?>
