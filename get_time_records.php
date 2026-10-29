<?php
require_once __DIR__ . '/security.php';
include 'config.php';

// Sprachdateien laden
$lang = $_SESSION['lang'] ?? 'de';
$langFile = "languages/$lang.php";

// Überprüfen, ob die Sprachdatei existiert, ansonsten die Standardsprache (Deutsch) laden
if (file_exists($langFile)) {
    include $langFile;
} else {
    include "languages/de.php";
}

include 'functions.php';
require_once __DIR__ . '/lib/report_ordering.php';
$user_id = $_SESSION['user_id'];
require_once __DIR__ . '/lib/time_record_review.php';
$reviewOnly = array_key_exists('review', $_GET);
if ($reviewOnly && $_GET['review'] !== '1') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Ungültige Listenansicht. Bitte Alle oder Prüfhinweise wählen.';
    exit;
}

// Pagination
$itemsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Sort actual instants before pagination; equal instants use the stable row ID.
report_register_start_order($conn);
// Page rows and full conflict/day context must describe the same own-user
// snapshot. Do not search only visible start-days: overnight counterparts can
// belong to another day or page. functions.php has finished its native reads.
$conn->beginTransaction();
try {
    $totalsStmt = $conn->prepare('SELECT * FROM zeiterfassung WHERE user_id = ?'
        . ($reviewOnly ? ' ORDER BY chrono_report_start_epoch(startzeit) DESC, id DESC' : ''));
    $totalsStmt->bindValue(1, (int)$user_id, PDO::PARAM_INT);
    $totalsStmt->execute();
    $completeDayRows = $totalsStmt->fetchAll(PDO::FETCH_ASSOC);
    $totalsStmt->closeCursor();
    $listConflicts = report_absence_conflicts($completeDayRows);
    $workOverlapLookup = time_record_work_overlap_lookup($completeDayRows);
    if ($reviewOnly) {
        // Classify the complete own snapshot BEFORE pagination; never the visible page.
        $reviewRows = time_record_review_rows($completeDayRows, $listConflicts, $workOverlapLookup);
        $totalRecords = count($reviewRows);
        $page = min($page, max(1, (int) ceil($totalRecords / $itemsPerPage)));
        $records = array_slice($reviewRows, ($page - 1) * $itemsPerPage, $itemsPerPage);
    } else {
        $totalRecords = count($completeDayRows);
        // A stale or oversized page must not hide the last existing own rows
        // or overflow OFFSET. Count and page use this same existing snapshot.
        $page = min($page, max(1, (int) ceil($totalRecords / $itemsPerPage)));
        $offset = ($page - 1) * $itemsPerPage;
    $stmt = $conn->prepare("
    SELECT *,
           NULL AS weekNumber,
           substr(startzeit,1,10) as workDate
    FROM zeiterfassung
    WHERE user_id = ?
    ORDER BY chrono_report_start_epoch(startzeit) DESC, id DESC
    LIMIT ? OFFSET ?
");
    $stmt->execute([$user_id, $itemsPerPage, $offset]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    }
    $conn->commit();
} catch (Throwable $error) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    throw $error;
}
$recordGroups = report_time_record_groups($records, $completeDayRows, $listConflicts);

// Group records by date for summary calculation
$currentDate = null;
$dailyTotals = [];

// Header section with improved export button
echo '<div class="flex justify-between items-center mb-4">
    <h2 class="text-xl font-bold"><i class="fas fa-clock mr-2"></i>' . TABLE_HEADER_TIME_RECORDS . '</h2>
    <button aria-label="' . h(BUTTON_EXPORT_CSV) . '" onclick="exportToCSV()"
            class="btn btn-outline btn-success btn-sm gap-2 hover:scale-105 transform transition-transform duration-200"
            >
        <i class="fas fa-file-csv text-lg"></i>
        <span class="hidden sm:inline">' . BUTTON_EXPORT_CSV . '</span>
    </button>
</div>';

echo '<details class="card bg-base-100 shadow-xl p-4 mb-4">
    <summary class="cursor-pointer font-semibold">Eigener CSV-Export für einen Zeitraum</summary>
    <form method="get" action="export_csv.php" class="grid sm:grid-cols-3 gap-3 mt-4 items-end">
        <input type="hidden" name="period" value="1">
        <div><label for="csv-period-from" class="block text-sm font-semibold mb-1">Von (Berliner Starttag)</label><input id="csv-period-from" name="from" type="date" min="0001-01-01" max="9999-12-31" required class="input input-bordered w-full"></div>
        <div><label for="csv-period-to" class="block text-sm font-semibold mb-1">Bis einschließlich</label><input id="csv-period-to" name="to" type="date" min="0001-01-01" max="9999-12-31" required class="input input-bordered w-full"></div>
        <button class="btn btn-outline btn-success" type="submit">Zeitraum als CSV</button>
    </form>
    <p class="text-sm opacity-70 mt-3">Nur eigene Buchungen nach Berliner Startdatum, unabhängig von Suche oder Listenseite. Nachtschichten bleiben vollständig beim Starttag. Die Qualitätsprüfung umfasst weiterhin Ihren gesamten Bestand; auch Fehler oder ungeklärte Überschneidungen außerhalb des Zeitraums können den Download blockieren. Kein Lohnsoftwareformat.</p>
</details>';

echo '<div class="flex flex-wrap items-end gap-3 mb-4"><label class="text-sm font-semibold" for="time-record-review">Listenansicht<select id="time-record-review" data-record-review-filter data-page="' . (int)$page . '" class="select select-bordered select-sm block mt-1"><option value="all"' . (!$reviewOnly ? ' selected' : '') . '>Alle eigenen Einträge</option><option value="review"' . ($reviewOnly ? ' selected' : '') . '>Vorhandene Prüfhinweise</option></select></label><p class="text-sm opacity-70">' . ($reviewOnly ? (int)$totalRecords . ' passende eigene Einträge. ' : '') . 'Ungültige Zeiten, bestehende Arbeitszeit-/Abwesenheitskonflikte, eindeutig prüfbare Überschneidungen abgeschlossener Arbeitszeiten und erkannte Berliner Zeitlücken oder doppelte Stunden ohne Offset; außerdem lesbare Zeiten außerhalb des Altzeit-Diagnoseformats. Kein vollständiger Bestandscheck oder Vollständigkeitsnachweis; ein unmarkierter Eintrag ist keine Freigabe. Bestehende Dauer- und Summenberechnungen bleiben unverändert und bestätigen keine eindeutige Altzeit. Summen berücksichtigen weiterhin den vollständigen eigenen Tageskontext.</p></div>';
if ($reviewOnly && $totalRecords === 0) {
    echo '<p class="text-sm mb-4" role="status">Keine passenden Prüfhinweise gefunden. Dies bestätigt weder einen fehlerfreien Bestand noch rekonstruierbare Altzeiten.</p>';
}

echo '<div id="timeRecordsTable" class="card bg-base-100 shadow-xl">
    <div class="card-body p-6">
        <h3 class="card-title text-2xl mb-6 flex items-center gap-3">
            <i class="fas fa-clock text-primary"></i>
            Arbeitszeiten und Abwesenheitsgutschriften
        </h3>
        <p class="text-sm opacity-70 mb-4">Datumszuordnung: Eine abgeschlossene Schicht wird mit ihrer gesamten Nettozeit nach erfasster Pause dem Berliner Startdatum zugerechnet – auch über Mitternacht, Wochen-, Monats- und Jahresgrenzen oder Zeitumstellungen hinweg. Laufende Buchungen sind noch keine abgeschlossene Zeit; es erfolgt keine tagweise Aufteilung.</p>
        <div class="overflow-x-auto rounded-lg border border-base-200">
            <table class="table w-full">
                <thead class="bg-base-200">
                    <tr>
                        <th class="text-left px-6 py-4 font-semibold text-sm uppercase tracking-wider w-48">Datum</th>
                        <th class="text-center px-6 py-4 font-semibold text-sm uppercase tracking-wider w-32">' . TABLE_HEADER_DURATION . '</th>
                        <th class="text-center px-6 py-4 font-semibold text-sm uppercase tracking-wider w-32">' . TABLE_HEADER_BREAK . '</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-base-200">';

$currentDate = null;
$dailyRecords = [];
$dailyTotals = [];

// Details stay page-local; summary duration and pause cover the complete day.
foreach ($recordGroups as $group) {
    $workDate = $group['group_key'];
    $dayRecords = $group['records'];
    $formattedTotal = $group['total_duration'];
    $totalPause = $group['total_pause'];
    $pauseLabel = $totalPause === null ? 'Ungültig' : $totalPause . ' min';
    $formattedDate = $group['formatted_date'];
    $entryCountLabel = $group['is_partial'] ? count($dayRecords) . ' / ' . $group['day_entry_count'] : (string)count($dayRecords);
    $entryCountTitle = $group['is_partial'] ? 'Sichtbare Einträge / alle Einträge des Tages' : 'Anzahl der Einträge';
    $partialDayNotice = $group['is_partial']
        ? '<p class="text-sm opacity-70 mb-3">Teilansicht: ' . $entryCountLabel . ' Einträge auf dieser Seite. Dauer und Pause berücksichtigen alle Einträge des Tages.</p>'
        : '';
    $invalidNotice = $group['invalid_count'] > 0
        ? '<p class="text-sm text-warning mb-3" role="alert">Datenqualität: ' . $group['invalid_count'] . ' ungültige Einträge. Keine verlässliche Tagessumme; bitte „Korrektur / Storno“ verwenden.</p>'
        : '';
    $conflictNotice = $group['is_unresolved']
        ? '<p class="text-sm text-warning mb-3" role="alert">Klärung offen: Arbeitszeit und ganztägige Abwesenheit überschneiden sich. Die Tagessumme ist nicht abschließend. Einzelwerte bleiben unverändert; bitte über „Korrektur / Storno“ mit Freigabe klären.</p>'
        : '';

    // Update the summary row output with entry count
    echo '<tr class="summary-row hover:bg-base-200/50 cursor-pointer transition-colors" data-date="' . $workDate . '">
            <td class="px-6 py-4 w-48">
                <div class="flex items-center gap-3">
                    <div class="w-6 flex justify-center transition-transform duration-200">
                        <i class="fas fa-chevron-right text-primary"></i>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="font-medium">' . $formattedDate . '</div>
                        <div class="badge badge-sm badge-ghost" title="' . $entryCountTitle . '">
                            ' . $entryCountLabel . ($group['is_partial'] ? ' · Teilansicht' : '') . '
                        </div>
                        ' . ($group['invalid_count'] > 0 ? '<span class="badge badge-warning badge-sm">Daten prüfen</span>' : '') . '
                        ' . ($group['is_unresolved'] ? '<span class="badge badge-warning badge-sm">Klärung offen</span>' : '') . '
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 w-32">
                <div class="flex items-center justify-center gap-2">
                    <i class="fas fa-clock text-primary/75"></i>
                    <span class="font-medium">' . $formattedTotal . '</span>
                </div>
            </td>
            <td class="px-6 py-4 w-32">
                <div class="flex items-center justify-center gap-2">
                    <i class="fas fa-pause text-primary/75"></i>
                    <span class="font-medium">' . $pauseLabel . '</span>
                </div>
            </td>
            <td></td>
          </tr>';

    // Output detail rows - keep full width for details
    echo '<tr class="details-row bg-base-100/50" data-date="' . $workDate . '" style="display: none;">
            <td colspan="4" class="p-0">
                <div class="pl-14 pr-6 py-4 border-l-4 border-primary/10">
                    ' . $partialDayNotice . '
                    ' . $invalidNotice . '
                    ' . $conflictNotice . '
                    <table class="table w-full">
                        <thead class="bg-base-200/50">
                            <tr>
                                <th class="text-left px-4 py-3 text-sm font-medium">' . TABLE_HEADER_WEEK . '</th>
                                <th class="text-left px-4 py-3 text-sm font-medium">' . TABLE_HEADER_START_TIME . '</th>
                                <th class="text-left px-4 py-3 text-sm font-medium">' . TABLE_HEADER_END_TIME . '</th>
                                <th class="text-left px-4 py-3 text-sm font-medium">' . TABLE_HEADER_DURATION . '</th>
                                <th class="text-left px-4 py-3 text-sm font-medium">' . TABLE_HEADER_BREAK . '</th>
                                <th class="text-left px-4 py-3 text-sm font-medium">' . TABLE_HEADER_LOCATION . '</th>
                                <th class="text-left px-4 py-3 text-sm font-medium">' . TABLE_HEADER_COMMENT . '</th>
                                <th class="text-left px-4 py-3 text-sm font-medium w-12">' . TABLE_HEADER_ACTIONS . '</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-200/50">';

    foreach ($dayRecords as $record) {
        outputRecordRow($record, isset($workOverlapLookup[report_conflict_key($record)]));
    }

    echo '</tbody>
                    </table>
                </div>
            </td>
          </tr>';
}

echo '</tbody></table></div>';

// Pagination count belongs to the same snapshot as the displayed page/context.
$totalPages = ceil($totalRecords / $itemsPerPage);

// Generate pagination HTML
echo '<div class="flex justify-center mt-4">
        <div class="btn-group">';

if ($page > 1) {
    echo '<button onclick="updateTimeRecordsTable(1)" class="btn">«</button>
          <button onclick="updateTimeRecordsTable(' . ($page - 1) . ')" class="btn">‹</button>';
}

$start = max(1, $page - 2);
$end = min($totalPages, $page + 2);
for ($i = $start; $i <= $end; $i++) {
    echo '<button onclick="updateTimeRecordsTable(' . $i . ')" class="btn ' . ($i === $page ? 'btn-active' : '') . '">' . $i . '</button>';
}

if ($page < $totalPages) {
    echo '<button onclick="updateTimeRecordsTable(' . ($page + 1) . ')" class="btn">›</button>
          <button onclick="updateTimeRecordsTable(' . $totalPages . ')" class="btn">»</button>';
}

echo '</div>
    </div>
  </div>
</div>';

// Ensure that the response does not include commands to display event selection fields

function outputTableHeaders() {
    echo '<thead>
            <tr>
                <th class="hidden w-12">' . TABLE_HEADER_ID . '</th>
                <th class="text-left w-12">' . TABLE_HEADER_WEEK . '</th>
                <th class="text-left w-32">' . TABLE_HEADER_START_TIME . '</th>
                <th class="text-left w-32">' . TABLE_HEADER_END_TIME . '</th>
                <th class="text-left w-20">' . TABLE_HEADER_DURATION . '</th>
                <th class="text-left w-16">' . TABLE_HEADER_BREAK . '</th>
                <th class="text-left w-32">' . TABLE_HEADER_LOCATION . '</th>
                <th class="text-left">' . TABLE_HEADER_COMMENT . '</th>
                <th class="text-left w-12">' . TABLE_HEADER_ACTIONS . '</th>
            </tr>
        </thead>';
}

function outputRecordRow($record, bool $workOverlap = false) {
    $detail = report_time_record_detail($record);
    $startLabel = $detail['start_label'];
    $endLabel = $detail['end_label'];
    $durationLabel = $detail['duration_label'];
    $qualityLabel = $detail['is_invalid'] ? '<span class="block text-sm text-warning">'.h($detail['validation_error']).'</span>' : '';
    $conflictLabel = $detail['is_unresolved'] ? '<span class="block text-sm text-warning">Klärung offen · Überschneidung mit Arbeitszeit / Abwesenheit. Einzelwert unverändert.</span>' : '';
    $workOverlapLabel = $workOverlap ? '<span class="block text-sm text-warning">Prüfhinweis · Abgeschlossene Arbeitszeiten überschneiden sich. Bruttointervalle, keine Pausenlage; keine automatische Kürzung. Dauer und Summen unverändert. Über Korrektur / Storno manuell klären.</span>' : '';
    $localTimeLabels = '';
    foreach (time_record_local_time_findings($record) as $column => $category) {
        $fieldLabel = $column === 'startzeit' ? 'Start' : 'Ende';
        if ($category === 'unsupported_timestamp_shape') {
            $localTimeLabels .= '<span class="block text-sm text-warning">'.h('Format-Prüfhinweis ('.$fieldLabel.'): lesbar, aber außerhalb des Altzeit-Diagnoseformats. Das Zeitformat wird damit nicht als ungültig bewertet; Dauer/Summen unverändert. Über Korrektur / Storno prüfen.').'</span>';
        } else {
            $findingLabel = $category === 'ambiguous_berlin_local_time'
                ? 'doppelte Berliner Stunde ohne Offset. Kein eindeutiger Zeitpunkt'
                : 'Berliner Zeitlücke ohne Offset. Kein existierender lokaler Zeitpunkt';
            $localTimeLabels .= '<span class="block text-sm text-warning">'.h('DST-Prüfhinweis ('.$fieldLabel.'): '.$findingLabel.'; Dauer/Summen nicht bestätigt.').'</span>';
        }
    }
    echo '<tr><td>'.h($record['weekNumber']).'</td><td>'.h($startLabel).'</td><td>'.h($endLabel).'</td><td>'.h($durationLabel).$qualityLabel.$conflictLabel.$workOverlapLabel.$localTimeLabels.'</td><td>'.h($record['pause']).'</td><td>'.h($record['standort']).'</td><td>'.h($record['beschreibung']).'</td><td><a class="link link-primary" href="adjustments.php?record_id='.(int)$record['id'].'">Korrektur / Storno</a></td></tr>';
}
