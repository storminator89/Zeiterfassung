<?php
session_start();
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
$user_id = $_SESSION['user_id'];

// Pagination
$itemsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $itemsPerPage;

// Modify the query to order by date and time
$stmt = $conn->prepare("
    SELECT *, 
           strftime('%W', startzeit) AS weekNumber,
           DATE(startzeit) as workDate
    FROM zeiterfassung 
    WHERE user_id = ? 
    ORDER BY startzeit DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$user_id, $itemsPerPage, $offset]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group records by date for summary calculation
$currentDate = null;
$dailyTotals = [];

// Header section with improved export button
echo '<div class="flex justify-between items-center mb-4">
    <h2 class="text-xl font-bold"><i class="fas fa-clock mr-2"></i>' . TABLE_HEADER_TIME_RECORDS . '</h2>
    <button onclick="exportToCSV()" 
            class="btn btn-outline btn-success btn-sm gap-2 hover:scale-105 transform transition-transform duration-200" 
            >
        <i class="fas fa-file-csv text-lg"></i>
        <span class="hidden sm:inline">' . BUTTON_EXPORT_CSV . '</span>
    </button>
</div>';

echo '<div id="timeRecordsTable" class="card bg-base-100 shadow-xl">
    <div class="card-body p-6">
        <h3 class="card-title text-2xl mb-6 flex items-center gap-3">
            <i class="fas fa-clock text-primary"></i>
            ' . ACTUAL_WORKED_TIMES . '
        </h3>
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

// Group records by date first
foreach ($records as $record) {
    $workDate = $record['workDate'];
    if (!isset($dailyRecords[$workDate])) {
        $dailyRecords[$workDate] = [];
        $dailyTotals[$workDate] = [
            'totalMinutes' => 0,
            'totalPause' => 0,
            'entries' => 0
        ];
    }
    
    $dailyRecords[$workDate][] = $record;
    
    if ($record['endzeit']) {
        $start = new DateTime($record['startzeit']);
        $end = new DateTime($record['endzeit']);
        $interval = $start->diff($end);
        $minutes = ($interval->h * 60) + $interval->i;
        $dailyTotals[$workDate]['totalMinutes'] += $minutes;
        $dailyTotals[$workDate]['totalPause'] += intval($record['pause']);
        $dailyTotals[$workDate]['entries']++;
    }
}

// Update the summary row calculation
foreach ($dailyRecords as $workDate => $dayRecords) {
    $totals = $dailyTotals[$workDate];
    $totalMinutes = 0;
    $totalPause = 0;

    // Calculate total working time for the day
    foreach ($dayRecords as $record) {
        if ($record['endzeit']) {
            $start = new DateTime($record['startzeit']);
            $end = new DateTime($record['endzeit']);
            $diff = $end->getTimestamp() - $start->getTimestamp();
            $totalMinutes += floor($diff / 60);
            $totalPause += intval($record['pause']);
        }
    }

    // Subtract total pause from total minutes
    $totalMinutes -= $totalPause;
    
    // Format total time
    $totalHours = floor($totalMinutes / 60);
    $remainingMinutes = $totalMinutes % 60;
    $formattedTotal = sprintf("%02d:%02d", $totalHours, $remainingMinutes);
    
    $dateObj = new DateTime($workDate);
    $formattedDate = $dateObj->format('d.m.Y');

    // Update the summary row output with entry count
    echo '<tr class="summary-row hover:bg-base-200/50 cursor-pointer transition-colors" data-date="' . $workDate . '">
            <td class="px-6 py-4 w-48">
                <div class="flex items-center gap-3">
                    <div class="w-6 flex justify-center transition-transform duration-200">
                        <i class="fas fa-chevron-right text-primary"></i>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="font-medium">' . $formattedDate . '</div>
                        <div class="badge badge-sm badge-ghost" title="Anzahl der Einträge">
                            ' . count($dayRecords) . '
                        </div>
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
                    <span class="font-medium">' . $totalPause . ' min</span>
                </div>
            </td>
            <td></td>
          </tr>';

    // Output detail rows - keep full width for details
    echo '<tr class="details-row bg-base-100/50" data-date="' . $workDate . '" style="display: none;">
            <td colspan="4" class="p-0">
                <div class="pl-14 pr-6 py-4 border-l-4 border-primary/10">
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
        outputRecordRow($record);
    }
    
    echo '</tbody>
                    </table>
                </div>
            </td>
          </tr>';
}

echo '</tbody></table></div>';

// Fetch total number of records
$stmt = $conn->prepare("SELECT COUNT(*) FROM zeiterfassung WHERE user_id = ?");
$stmt->execute([$user_id]);
$totalRecords = $stmt->fetchColumn();
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

function outputRecordRow($record) {
    echo '<tr>
            <td class="hidden">' . $record['id'] . '</td>
            <td>' . $record['weekNumber'] . '</td>
            <td>
                <div class="flex items-center gap-2">
                    <span class="text-gray-600 editable-datetime" 
                          data-id="' . $record['id'] . '" 
                          data-field="startzeit" 
                          data-value="' . date('Y-m-d H:i', strtotime($record['startzeit'])) . '">
                        ' . date('d.m.Y H:i', strtotime($record['startzeit'])) . '
                    </span>
                    <button type="button" class="edit-datetime-btn btn btn-ghost btn-xs">
                        <i class="fas fa-calendar-alt"></i>
                    </button>
                </div>
            </td>
            <td>';

    if ($record['endzeit'] !== null) {
        echo '<div class="flex items-center gap-2">
                <span class="text-gray-600 editable-datetime cursor-default" data-id="' . $record['id'] . '" data-field="endzeit" data-value="' . date('Y-m-d\TH:i', strtotime($record['endzeit'])) . '">
                    ' . date('d.m.Y H:i', strtotime($record['endzeit'])) . '
                </span>
                <button type="button" class="edit-datetime-btn btn btn-ghost btn-xs">
                    <i class="fas fa-calendar-alt"></i>
                </button>
            </div>';
    } else {
        echo '<span>-</span>';
    }

    echo '</td>
            <td>' . calculateDuration($record['startzeit'], $record['endzeit'], $record['pause']) . '</td>
            <td>
                <input type="number" 
                       name="pause" 
                       class="input input-bordered input-sm w-16" 
                       value="' . $record['pause'] . '" 
                       data-id="' . $record['id'] . '">
            </td>
            <td>
                <select class="select select-bordered select-sm w-32" 
                        data-id="' . htmlspecialchars($record['id']) . '" 
                        data-field="standort">
                    <option value="' . LOCATION_OFFICE_VALUE . '" ' . ($record['standort'] == LOCATION_OFFICE_VALUE ? 'selected' : '') . '>' . LOCATION_OFFICE . '</option>
                    <option value="' . LOCATION_HOME_OFFICE_VALUE . '" ' . ($record['standort'] == LOCATION_HOME_OFFICE_VALUE ? 'selected' : '') . '>' . LOCATION_HOME_OFFICE . '</option>
                    <option value="' . LOCATION_BUSINESS_TRIP_VALUE . '" ' . ($record['standort'] == LOCATION_BUSINESS_TRIP_VALUE ? 'selected' : '') . '>' . LOCATION_BUSINESS_TRIP . '</option>
                </select>
            </td>
            <td>
                <input type="text" 
                       class="input input-bordered input-sm w-full" 
                       data-id="' . htmlspecialchars($record['id']) . '" 
                       data-field="beschreibung" 
                       value="' . htmlspecialchars($record['beschreibung']) . '">
            </td>
            <td class="w-12">
                <button class="btn btn-ghost btn-xs text-black hover:bg-black hover:text-white transition-colors duration-300 deleteRow" data-id="' . $record['id'] . '">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>';
}
?>
