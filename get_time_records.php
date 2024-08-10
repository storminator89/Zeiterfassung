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

// Fetch records for current page
$stmt = $conn->prepare("SELECT *, strftime('%W', startzeit) AS weekNumber FROM zeiterfassung WHERE user_id = ? ORDER BY startzeit DESC LIMIT ? OFFSET ?");
$stmt->execute([$user_id, $itemsPerPage, $offset]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Start of the timeRecordsTable div
echo '<div id="timeRecordsTable" class="card bg-base-100 shadow-xl">
    <div class="card-body">
        <h3 class="card-title text-2xl mb-4"><i class="fas fa-clock mr-2"></i>' . ACTUAL_WORKED_TIMES . '</h3>
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr>
                        <th class="text-left">' . TABLE_HEADER_ID . '</th>
                        <th class="text-left">' . TABLE_HEADER_WEEK . '</th>
                        <th class="text-left">' . TABLE_HEADER_START_TIME . '</th>
                        <th class="text-left">' . TABLE_HEADER_END_TIME . '</th>
                        <th class="text-left">' . TABLE_HEADER_DURATION . '</th>
                        <th class="text-left">' . TABLE_HEADER_BREAK . '</th>
                        <th class="text-left">' . TABLE_HEADER_LOCATION . '</th>
                        <th class="text-left">' . TABLE_HEADER_COMMENT . '</th>
                        <th class="text-left">' . TABLE_HEADER_ACTIONS . '</th>
                    </tr>
                </thead>
                <tbody>';

foreach ($records as $record) {
    echo '<tr>
            <td>' . $record['id'] . '</td>
            <td>' . $record['weekNumber'] . '</td>
            <td>
                <input type="datetime-local" class="input input-bordered w-full max-w-xs editable" data-id="' . $record['id'] . '" data-field="startzeit" value="' . date('Y-m-d\TH:i', strtotime($record['startzeit'])) . '">
            </td>
            <td>';

    if ($record['endzeit'] !== null) {
        echo '<input type="datetime-local" class="input input-bordered w-full max-w-xs editable" data-id="' . $record['id'] . '" data-field="endzeit" value="' . date('Y-m-d\TH:i', strtotime($record['endzeit'])) . '">';
    } else {
        echo '<span>-</span>';
    }

    echo '</td>
            <td>' . calculateDuration($record['startzeit'], $record['endzeit'], $record['pause']) . '</td>
            <td>
                <input type="number" class="input input-bordered w-full max-w-xs editable" data-id="' . $record['id'] . '" data-field="pause" value="' . $record['pause'] . '">
            </td>
            <td>
                <select class="select select-bordered w-full max-w-xs editable" data-id="' . $record['id'] . '" data-field="standort">
                    <option value="' . LOCATION_OFFICE_VALUE . '" ' . ($record['standort'] == LOCATION_OFFICE_VALUE ? 'selected' : '') . '>' . LOCATION_OFFICE . '</option>
                    <option value="' . LOCATION_HOME_OFFICE_VALUE . '" ' . ($record['standort'] == LOCATION_HOME_OFFICE_VALUE ? 'selected' : '') . '>' . LOCATION_HOME_OFFICE . '</option>
                    <option value="' . LOCATION_BUSINESS_TRIP_VALUE . '" ' . ($record['standort'] == LOCATION_BUSINESS_TRIP_VALUE ? 'selected' : '') . '>' . LOCATION_BUSINESS_TRIP . '</option>
                </select>
            </td>
            <td>
                <input type="text" class="input input-bordered w-full max-w-xs editable" data-id="' . $record['id'] . '" data-field="beschreibung" value="' . htmlspecialchars($record['beschreibung']) . '">
            </td>
            <td>
                <button class="btn btn-ghost btn-sm text-black hover:bg-black hover:text-white transition-colors duration-300 deleteRow" data-id="' . $record['id'] . '">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>';
}

echo '</tbody>
            </table>
        </div>';

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
?>
