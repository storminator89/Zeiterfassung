<?php
include 'header.php';

// Überprüfen, ob der Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

// Zeiten der unterstellten Benutzer aus der Datenbank abrufen
$zeiten = [];
$ueberstundenListe = [];
$detaillierteDaten = [];
$reportAvailable = false;
$invalidRecords = [];
$absenceConflicts = [];
$teamStatus = null;
$teamStatusUnavailable = false;
$userSearch = isset($_GET['user_search']) && is_string($_GET['user_search']) ? trim($_GET['user_search']) : '';
$tableQuery = ['lang' => $lang, 'user_search' => $userSearch];
$pageUrl = static fn (int $page): string => '?' . htmlspecialchars(http_build_query(array_merge($tableQuery, ['page' => $page])), ENT_QUOTES, 'UTF-8');
if ($user_role === 'admin' || $user_role === 'supervisor') {
    require_once __DIR__.'/lib/team_status.php';
    $currentTeamRole = null;
    try {
        $teamStatus = chrono_native_team_status($conn, (int) $user_id, (string) ($_SESSION['auth_version'] ?? ''), $currentTeamRole);
    } catch (Throwable) {
        // Never turn an auth/SQL/snapshot error into absence or an empty team.
        $teamStatusUnavailable = true;
    }
    // Use the role actually checked above, never a now-known stale session role.
    // NULL means the new read could not establish current authorization.
    $user_role = $currentTeamRole ?? '';
    if ($user_role === 'admin' || $user_role === 'supervisor') {
    require_once __DIR__.'/lib/report_ordering.php';
    report_register_start_order($conn);
    $stmt = $conn->prepare("SELECT z.*, u.username, u.id as user_id, u.regelarbeitszeit, u.ueberstunden as vorherige_ueberstunden, substr(z.startzeit,1,10) AS day, NULL AS weekNumber
                            FROM zeiterfassung z
                            JOIN users u ON z.user_id = u.id
                            WHERE u.supervisor_id = ? OR ?='admin'
                            ORDER BY chrono_report_start_epoch(z.startzeit) DESC, z.id DESC");
    $stmt->execute([$user_id,$user_role]);
    $zeiten = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Diagnose the complete authorized source BEFORE dates, plans or rollups.
    // Invalid and conflicting rows are not zero-minute work or a partial total.
    foreach ($zeiten as $rowIndex => $row) {
        $calculation = report_record_calculation($row);
        if ($calculation['status'] === 'invalid') {
            $invalidRecords[$rowIndex] = $calculation['error'];
        }
    }
    $absenceConflicts = report_absence_conflicts($zeiten);
    $reportAvailable = $invalidRecords === [] && $absenceConflicts === [];

    if ($reportAvailable) {
    foreach ($zeiten as &$entry) { $entry['weekNumber']=substr(report_week($entry),5);$entry['day']=report_day($entry);$entry['net_minutes']=report_minutes($entry);$entry['planned_minutes_day']=planned_minutes($conn,(int)$entry['user_id'],$entry['day']); } unset($entry);
    // Berechnung der Überstunden und detaillierte Daten
    $workHoursByUser = [];
    foreach ($zeiten as $zeit) {
        $userId = $zeit['user_id'];
        $day = $zeit['day'];
        $regelarbeitszeit = $zeit['regelarbeitszeit'] ?? 8.0;
        $vorherige_ueberstunden = $zeit['vorherige_ueberstunden'] ?? 0.0;

        if (!isset($workHoursByUser[$userId])) {
            $workHoursByUser[$userId] = [
                'username' => $zeit['username'],
                'days' => [],
                'regelarbeitszeit' => $regelarbeitszeit,
                'vorherige_ueberstunden' => $vorherige_ueberstunden,
                'total_hours' => 0,
                'total_days' => 0
            ];
        }

        if ($zeit['net_minutes']===null) { continue; }
        if (!isset($workHoursByUser[$userId]['days'][$day])) {
            $workHoursByUser[$userId]['days'][$day] = 0;
            $workHoursByUser[$userId]['total_days']++;
        }

        $start = new DateTime($zeit['startzeit']);
        $end = new DateTime($zeit['endzeit']);
        $interval = $start->diff($end);
        $pauseMinuten = intval($zeit['pause']) ?: 0;

        $gesamtMinuten = $zeit['net_minutes'];
        $workHoursByUser[$userId]['days'][$day] += $gesamtMinuten;
        $workHoursByUser[$userId]['total_hours'] += $gesamtMinuten / 60;
    }

    // Berechnung der Gesamtüberstunden pro Benutzer und Vorbereitung der detaillierten Daten
    foreach ($workHoursByUser as $userId => $data) {
        $totalOverMinutes = 0;
        $regelarbeitszeit = $data['regelarbeitszeit'];
        foreach ($data['days'] as $day => $totalMinutes) {
            $regularWorkingMinutesPerDay = planned_minutes($conn,(int)$userId,$day);

            $overMinutes = $totalMinutes - $regularWorkingMinutesPerDay;
            $totalOverMinutes += $overMinutes;
        }

        $totalOverMinutes += (int) round($data['vorherige_ueberstunden'] * 60);
        $formattedOvertime = report_format_hours($totalOverMinutes / 60);

        $ueberstundenListe[$userId] = [
            'username' => $data['username'],
            'overtime_minutes' => $totalOverMinutes,
            'ueberstunden' => $formattedOvertime
        ];

        $detaillierteDaten[$userId] = [
            'username' => $data['username'],
            'regelarbeitszeit' => $data['regelarbeitszeit'],
            'total_hours' => round($data['total_hours'], 2),
            'total_days' => $data['total_days'],
            'avg_hours_per_day' => ($data['total_days']>0 ? round($data['total_hours'] / $data['total_days'], 2) : 0),
            'overtime_minutes' => $totalOverMinutes,
            'ueberstunden' => $formattedOvertime
        ];
    }
    }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" data-theme="<?= $theme_mode ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SUPERVISOR_TIMES_TITLE ?></title>
    <link rel="icon" href="assets/kolibri_icon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Remove the specific background-color from [data-theme="dark"] as it's not needed */
        [data-theme="dark"] {
            color: hsl(var(--bc));
        }

        /* Existing styles */
        .modal-box {
            width: 90vw;
            max-width: 70vw;
            height: 70vh;
            max-height: 90vh;
        }

        th {
            text-align: left !important;
        }

        /* Card animations */
        .stats-card {
            transition: transform 0.2s ease-in-out;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        /* Loading animation */
        .loading-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 200px;
        }

        /* Search bar styles */
        .search-container {
            margin-bottom: 1rem;
        }

        .search-input {
            width: 100%;
            max-width: 300px;
        }

        /* Filter badges */
        .filter-badge {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-badge:hover {
            transform: scale(1.05);
        }

        /* Chart container */
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 2rem;
        }
    </style>
</head>

<body>
    <!-- Remove the bg-base-200 class from body and add it to a wrapper div -->
    <div class="min-h-screen bg-base-100">

        <div class="container mx-auto px-4 py-8">
            <h2 class="text-4xl font-bold mb-8 text-center"><?= SUPERVISOR_TIMES_TITLE ?></h2>

            <?php if ($user_role !== 'supervisor' && $user_role !== 'admin') : ?>
                <div class="alert alert-warning shadow-lg">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span><?= NOT_SUPERVISOR_MESSAGE ?></span>
                    </div>
                </div>
            <?php else : ?>
                <?php if ($teamStatusUnavailable) : ?>
                    <div class="alert alert-warning mb-4" role="alert"><span><?= h(TEAM_STATUS_TITLE) ?>: <?= h(TEAM_STATUS_UNAVAILABLE) ?></span></div>
                <?php elseif ($teamStatus !== null) : ?>
                    <section class="card bg-base-100 shadow-xl mb-8" aria-labelledby="nativeTeamStatusTitle">
                        <div class="card-body">
                            <h3 id="nativeTeamStatusTitle" class="card-title"><?= h(TEAM_STATUS_TITLE) ?></h3>
                            <p class="text-sm opacity-70"><?= h(TEAM_STATUS_SNAPSHOT_NOTE) ?> <time datetime="<?= h($teamStatus['loaded_at']) ?>"><?= h($teamStatus['loaded_at']) ?></time></p>
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead><tr><th><?= h(TABLE_HEADER_USERNAME) ?></th><th><?= h(TEAM_STATUS_STATE) ?></th></tr></thead>
                                    <tbody>
                                    <?php foreach ($teamStatus['people'] as $person) : ?>
                                        <?php $teamStateLabel = match ($person['state']) {
                                            'working' => TEAM_STATUS_WORKING,
                                            'paused' => TEAM_STATUS_PAUSED,
                                            'absent' => TEAM_STATUS_ABSENT,
                                            default => TEAM_STATUS_UNKNOWN,
                                        }; ?>
                                        <tr><td><?= h($person['username']) ?></td><td><?= h($teamStateLabel) ?></td></tr>
                                    <?php endforeach; ?>
                                    <?php if ($teamStatus['people'] === []) : ?>
                                        <tr><td colspan="2" class="opacity-70"><?= h(TEAM_STATUS_EMPTY) ?></td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
                <?php if (!$reportAvailable) : ?>
                    <?php if ($absenceConflicts !== []) : ?>
                    <div class="alert alert-warning mb-4" role="alert"><span><strong>Klärung offen.</strong> Arbeitszeit und ganztägige Abwesenheit überschneiden sich im freigegebenen Berichtsumfang. Diese Auswertung enthält deshalb keine abschließenden Summen. Bitte die betroffenen Originaleinträge in der <a class="link" href="history.php">Historie</a> prüfen und die Korrektur durch die betroffene Person mit anschließender Vier-Augen-Freigabe klären. Die <a class="link" href="index.php">eigene Arbeitszeitliste</a> und <a class="link" href="adjustments.php">Korrekturanträge</a> bleiben verfügbar; es wurden keine Zeiten gelöscht oder gekürzt.</span></div>
                    <?php endif; ?>
                    <?php if ($invalidRecords !== []) : ?>
                    <div class="alert alert-warning mb-4" role="alert"><span><strong><?= htmlspecialchars(sprintf(SUPERVISOR_INVALID_RECORDS, count($invalidRecords)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong> <?= htmlspecialchars(SUPERVISOR_TOTALS_UNAVAILABLE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> <a class="link" href="history.php">Historie</a> · <a class="link" href="index.php">eigene Arbeitszeitliste</a> · <a class="link" href="adjustments.php">Korrekturanträge</a></span></div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($reportAvailable) : ?>
                <!-- Search and Filter Section -->
                <div class="mb-6">
                    <div class="flex flex-wrap gap-4 items-center justify-between">
                        <div class="search-container">
                            <input type="text"
                                id="searchInput"
                                class="input input-bordered search-input"
                                placeholder="Suche nach Mitarbeiter...">
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <span class="badge badge-primary filter-badge" data-filter="all">Alle</span>
                            <span class="badge badge-secondary filter-badge" data-filter="overtime">Überstunden</span>
                            <span class="badge badge-warning filter-badge" data-filter="undertime">Minusstunden</span>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="mb-8">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="card bg-base-100 shadow-xl">
                            <div class="card-body">
                                <h3 class="card-title">Überstunden Übersicht</h3>
                                <p class="text-sm opacity-70 mt-2" style="white-space: normal;"><?= h(DASHBOARD_OVERTIME_INFO) ?></p>
                                <div class="chart-container">
                                    <canvas id="overtimeChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="card bg-base-100 shadow-xl">
                            <div class="card-body">
                                <h3 class="card-title">Regelarbeitszeit pro Mitarbeiter</h3>
                                <div class="chart-container">
                                    <canvas id="regularHoursChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Overtime Cards Section -->
                <div class="mb-12">
                    <h3 class="text-2xl font-semibold mb-6">
                        <i class="fas fa-hourglass mr-2"></i><?= TOTAL_OVERTIME_TITLE ?>
                    </h3>
                    <p class="text-sm opacity-70 mt-2" style="white-space: normal;"><?= h(DASHBOARD_OVERTIME_INFO) ?></p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="overtimeCards">
                        <?php if (is_array($ueberstundenListe) && !empty($ueberstundenListe)) : ?>
                            <?php foreach ($ueberstundenListe as $userId => $data) : ?>
                                <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow duration-300 fade-in stats-card" data-overtime-minutes="<?= $data['overtime_minutes'] ?>">
                                    <div class="card-body">
                                        <h2 class="card-title text-lg"><?= htmlspecialchars($data['username']) ?></h2>
                                        <p class="text-3xl font-bold <?= substr($data['ueberstunden'], 0, 1) === '-' ? 'text-error' : 'text-success' ?>">
                                            <?= htmlspecialchars($data['ueberstunden']) ?>
                                        </p>
                                        <div class="card-actions justify-end">
                                            <button class="btn btn-primary btn-sm" onclick="showDetails('<?= $userId ?>')">Details</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="col-span-full text-center opacity-70"><?= NO_ENTRIES_FOUND ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php endif; ?>

                <!-- Time Records Table Section -->
                <div class="mb-8">
                    <h3 class="text-2xl font-semibold mb-6">
                        <i class="fas fa-clock mr-2"></i><?= ACTUAL_WORKED_TIMES ?>
                    </h3>
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body">
                            <?php if (!$reportAvailable) : ?>
                            <p class="text-sm opacity-70 mb-4"><?= htmlspecialchars(SUPERVISOR_SOURCE_VALUES, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <!-- Search and Filter Bar -->
                            <form method="get" action="supervisor.php" class="flex flex-wrap gap-4 mb-4">
                                <input type="hidden" name="lang" value="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="form-control w-full max-w-xs">
                                    <div class="input-group">
                                        <input type="search"
                                            id="userSearchInput"
                                            name="user_search"
                                            value="<?= htmlspecialchars($userSearch, ENT_QUOTES, 'UTF-8') ?>"
                                            class="input input-bordered w-full"
                                            aria-label="<?= SEARCH_USERS ?>"
                                            placeholder="Mitarbeiter suchen...">
                                        <button type="submit" class="btn btn-square" aria-label="<?= SEARCH_USERS ?>">
                                            <i class="fas fa-search" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <div class="overflow-x-auto">
                                <table class="table w-full">
                                    <thead>
                                        <?php if ($reportAvailable) : ?>
                                        <tr>
                                            <th class="w-8"></th> <!-- Expand/Collapse Icon -->
                                            <th><?= TABLE_HEADER_USERNAME ?></th>
                                            <th><?= TABLE_HEADER_DATE ?></th>
                                            <th><?= TABLE_HEADER_TOTAL_DURATION ?></th>
                                            <th><?= TABLE_HEADER_TOTAL_BREAK ?></th>
                                            <th><?= TABLE_HEADER_LOCATION ?></th>
                                            <th><?= TABLE_HEADER_OVERTIME ?></th>
                                        </tr>
                                        <?php else : ?>
                                        <tr>
                                            <th><?= TABLE_HEADER_USERNAME ?></th>
                                            <th><?= TABLE_HEADER_START_TIME ?></th>
                                            <th><?= TABLE_HEADER_END_TIME ?></th>
                                            <th><?= TABLE_HEADER_BREAK ?></th>
                                            <th><?= TABLE_HEADER_LOCATION ?></th>
                                            <th><?= TABLE_HEADER_COMMENT ?></th>
                                        </tr>
                                        <?php endif; ?>
                                    </thead>
                                    <tbody id="timeRecordsBody">
                                        <?php
                                        if ($reportAvailable) {
                                        // Group entries by user and date
                                        $groupedEntries = [];
                                        foreach ($zeiten as $zeit) {
                                            $userId = $zeit['user_id'];
                                            $date = date('Y-m-d', strtotime($zeit['startzeit']));
                                            $key = $userId . '_' . $date;

                                            if (!isset($groupedEntries[$key])) {
                                                $groupedEntries[$key] = [
                                                    'user_id' => $userId,
                                                    'username' => $zeit['username'],
                                                    'date' => $date,
                                                    'regelarbeitszeit' => $zeit['regelarbeitszeit'],
                                                    'entries' => [],
                                                    'total_duration' => 0,
                                                    'total_break' => 0,
                                                    'completed' => false
                                                ];
                                            }

                                            // Calculate duration for this entry
                                            $start = new DateTime($zeit['startzeit']);
                                            $end = $zeit['endzeit'] ? new DateTime($zeit['endzeit']) : null;
                                            $duration = $zeit['net_minutes']===null ? 0 : $zeit['net_minutes']+(int)$zeit['pause'];
                                            $break = $zeit['net_minutes']===null ? 0 : intval($zeit['pause']);
                                            if ($zeit['net_minutes']!==null) { $groupedEntries[$key]['completed']=true; }

                                            // Add entry with its individual duration minus break
                                            $groupedEntries[$key]['entries'][] = [
                                                'startzeit' => $zeit['startzeit'],
                                                'endzeit' => $zeit['endzeit'],
                                                'duration' => $duration - $break, // Individual duration minus break
                                                'pause' => $break,
                                                'standort' => $zeit['standort'],
                                                'beschreibung' => $zeit['beschreibung'] ?? ''
                                            ];

                                            // Update totals
                                            $groupedEntries[$key]['total_duration'] += $duration - $break; // Add duration minus break
                                            $groupedEntries[$key]['total_break'] += $break;
                                        }

                                        // Search the authorized groups before slicing, so matches on other pages are included.
                                        if ($userSearch !== '') {
                                            $groupedEntries = array_filter($groupedEntries, static fn (array $group): bool => mb_stripos($group['username'], $userSearch) !== false);
                                        }

                                        // This is the only table render/pagination path.
                                        $itemsPerPage = 10;
                                        $totalRecords = count($groupedEntries);
                                        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
                                        $currentPage = isset($_GET['page']) && is_string($_GET['page']) ? (int)$_GET['page'] : 1;
                                        $currentPage = max(1, min($currentPage, $totalPages));
                                        $start = ($currentPage - 1) * $itemsPerPage;
                                        $pagedEntries = array_slice($groupedEntries, $start, $itemsPerPage, true);

                                        } else {
                                            // Raw source rows need no guessed day/group or calculated value.
                                            $sourceRows = $zeiten;
                                            if ($userSearch !== '') {
                                                $sourceRows = array_filter($sourceRows, static fn (array $row): bool => mb_stripos($row['username'], $userSearch) !== false);
                                            }
                                            $itemsPerPage = 10;
                                            $totalRecords = count($sourceRows);
                                            $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
                                            $currentPage = isset($_GET['page']) && is_string($_GET['page']) ? (int)$_GET['page'] : 1;
                                            $currentPage = max(1, min($currentPage, $totalPages));
                                            $start = ($currentPage - 1) * $itemsPerPage;
                                            $pagedEntries = array_slice($sourceRows, $start, $itemsPerPage, true);
                                        }

                                        if ($totalRecords === 0) {
                                            echo '<tr><td colspan="' . ($reportAvailable ? 7 : 6) . '" class="text-center opacity-70">' . NO_ENTRIES_FOUND . '</td></tr>';
                                        }

                                        if ($reportAvailable) {
                                        foreach ($pagedEntries as $group) {
                                            $totalDuration = $group['total_duration']; // Already includes break deduction
                                            $hours = floor($totalDuration / 60);
                                            $minutes = $totalDuration % 60;
                                            $overtime = $group['completed'] ? $totalDuration - planned_minutes($conn,(int)$group['user_id'],$group['date']) : 0;
                                            $overtimeHours = floor(abs($overtime) / 60);
                                            $overtimeMinutes = abs($overtime % 60);

                                            $detailId = 'time-details-' . $group['user_id'] . '-' . $group['date'];

                                            // Summary row
                                            echo '<tr class="group-header hover:bg-base-200 cursor-pointer" data-user-id="' . $group['user_id'] . '" data-date="' . $group['date'] . '">
                                                    <td><button type="button" class="btn btn-ghost btn-xs" aria-expanded="false" aria-controls="' . $detailId . '" aria-label="Details"><i class="fas fa-chevron-right transition-transform duration-200" aria-hidden="true"></i></button></td>
                                                    <td>' . htmlspecialchars($group['username']) . '</td>
                                                    <td>' . date('d.m.Y', strtotime($group['date'])) . '</td>
                                                    <td>' . sprintf("%02d:%02d", $hours, $minutes) . '</td>
                                                    <td>' . $group['total_break'] . ' min</td>
                                                    <td>' . (count($group['entries']) > 1 ? 'Mehrere' : htmlspecialchars($group['entries'][0]['standort'])) . '</td>
                                                    <td class="' . ($overtime >= 0 ? 'text-success' : 'text-error') . '">
                                                        ' . ($overtime >= 0 ? '+' : '-') . sprintf("%02d:%02d", $overtimeHours, $overtimeMinutes) . '
                                                    </td>
                                                </tr>';

                                            // Detail rows (initially hidden)
                                            echo '<tr id="' . $detailId . '" class="detail-row hidden" data-parent="' . $group['user_id'] . '_' . $group['date'] . '">
                                                    <td colspan="7" class="p-0">
                                                        <div class="bg-base-200/50 p-4">
                                                            <table class="table w-full">
                                                                <thead>
                                                                    <tr>
                                                                        <th>' . TABLE_HEADER_START_TIME . '</th>
                                                                        <th>' . TABLE_HEADER_END_TIME . '</th>
                                                                        <th>' . TABLE_HEADER_DURATION . '</th>
                                                                        <th>' . TABLE_HEADER_BREAK . '</th>
                                                                        <th>' . TABLE_HEADER_LOCATION . '</th>
                                                                        <th>' . TABLE_HEADER_COMMENT . '</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>';

                                            foreach ($group['entries'] as $entry) {
                                                $entryStart = new DateTime($entry['startzeit']);
                                                $entryEnd = $entry['endzeit'] ? new DateTime($entry['endzeit']) : null;
                                                $entryHours = floor($entry['duration'] / 60);
                                                $entryMinutes = $entry['duration'] % 60;

                                                echo '<tr>
                                                        <td>' . $entryStart->format('H:i') . '</td>
                                                        <td>' . ($entryEnd?->format('H:i') ?? 'Läuft') . '</td>
                                                        <td>' . sprintf("%02d:%02d", $entryHours, $entryMinutes) . '</td>
                                                        <td>' . $entry['pause'] . ' min</td>
                                                        <td>' . htmlspecialchars($entry['standort']) . '</td>
                                                        <td>' . htmlspecialchars($entry['beschreibung'] ?? '') . '</td>
                                                    </tr>';
                                            }

                                            echo '</tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>';
                                        }
                                        } else {
                                            // Escaped source values only: no date parsing, duration math or record IDs.
                                            foreach ($pagedEntries as $rowIndex => $row) {
                                                echo '<tr><td>' . htmlspecialchars((string)$row['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                                if (isset($invalidRecords[$rowIndex])) {
                                                    echo '<div class="mt-1"><span class="badge badge-warning">' . htmlspecialchars(SUPERVISOR_INVALID_ROW, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span> <span class="text-sm">' . htmlspecialchars($invalidRecords[$rowIndex], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span></div>';
                                                }
                                                echo '</td><td>' . htmlspecialchars((string)($row['startzeit'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                                                    . '</td><td>' . htmlspecialchars((string)($row['endzeit'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                                                    . '</td><td>' . htmlspecialchars((string)($row['pause'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                                                    . '</td><td>' . htmlspecialchars((string)($row['standort'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                                                    . '</td><td>' . htmlspecialchars((string)($row['beschreibung'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td></tr>';
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php if ($totalRecords > 0) : ?>
                    <div class="flex justify-center mt-4">
                        <div class="btn-group">
                            <?php
                            if ($currentPage > 1) {
                                echo '<a href="' . $pageUrl($currentPage - 1) . '" class="btn btn-sm">&laquo;</a>';
                            } else {
                                echo '<span class="btn btn-sm btn-disabled" aria-disabled="true">&laquo;</span>';
                            }

                            $maxVisible = 5;
                            $start = max(1, min($currentPage - (int) floor($maxVisible / 2), $totalPages - $maxVisible + 1));
                            $end = min($start + $maxVisible - 1, $totalPages);

                            if ($start > 1) {
                                echo '<a href="' . $pageUrl(1) . '" class="btn btn-sm">1</a>';
                                if ($start > 2) {
                                    echo '<span class="btn btn-sm btn-disabled">...</span>';
                                }
                            }

                            for ($i = $start; $i <= $end; $i++) {
                                $activeClass = $i === $currentPage ? ' btn-active' : '';
                                $currentAttribute = $i === $currentPage ? ' aria-current="page"' : '';
                                echo '<a href="' . $pageUrl($i) . '" class="btn btn-sm' . $activeClass . '"' . $currentAttribute . '>' . $i . '</a>';
                            }

                            if ($end < $totalPages) {
                                if ($end < $totalPages - 1) {
                                    echo '<span class="btn btn-sm btn-disabled">...</span>';
                                }
                                echo '<a href="' . $pageUrl($totalPages) . '" class="btn btn-sm">' . $totalPages . '</a>';
                            }

                            if ($currentPage < $totalPages) {
                                echo '<a href="' . $pageUrl($currentPage + 1) . '" class="btn btn-sm">&raquo;</a>';
                            } else {
                                echo '<span class="btn btn-sm btn-disabled" aria-disabled="true">&raquo;</span>';
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($reportAvailable) : ?>
    <!-- Enhanced Modal -->
    <dialog id="details_modal" class="modal">
        <div class="modal-box w-11/12 max-w-7xl bg-base-200">
            <div class="modal-header flex justify-between items-center mb-6">
                <h3 class="font-bold text-2xl" id="modal_title">Detaillierte Übersicht</h3>
                <form method="dialog">
                    <button class="btn btn-circle btn-ghost">✕</button>
                </form>
            </div>
            <p class="text-sm opacity-70 mt-2" style="white-space: normal;"><?= h(DASHBOARD_OVERTIME_INFO) ?></p>
            <div id="modal_content" class="space-y-6">
                <!-- Content will be dynamically inserted here -->
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>Schließen</button>
        </form>
    </dialog>
    <?php endif; ?>

    <script>
function escapeHTML(value) {
    const node = document.createElement('span');
    node.textContent = String(value ?? '');
    return node.innerHTML;
}

        const detaillierteDaten = <?= json_encode($detaillierteDaten, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        function showDetails(userId) {
            const modal = document.getElementById('details_modal');
            const modalContent = document.getElementById('modal_content');
            const modalTitle = document.getElementById('modal_title');
            const userData = detaillierteDaten[userId];

            if (userData) {
                modalTitle.textContent = `Übersicht für ${userData.username}`;
                modalContent.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="stat bg-base-100 rounded-box shadow-lg">
                    <div class="stat-figure text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                    </div>
                    <div class="stat-title">Regelarbeitszeit</div>
                    <div class="stat-value">${escapeHTML(userData.regelarbeitszeit)}h</div>
                    <div class="stat-desc">pro Tag</div>
                </div>

                <div class="stat bg-base-100 rounded-box shadow-lg">
                    <div class="stat-figure text-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                    </div>
                    <div class="stat-title">Gearbeitete Tage</div>
                    <div class="stat-value">${userData.total_days}</div>
                    <div class="stat-desc">in diesem Zeitraum</div>
                </div>

                <div class="stat bg-base-100 rounded-box shadow-lg">
                    <div class="stat-figure text-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <div class="stat-title">Gesamtarbeitsstunden</div>
                    <div class="stat-value">${userData.total_hours.toFixed(2)}</div>
                    <div class="stat-desc">Stunden insgesamt</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="stat bg-base-100 rounded-box shadow-lg">
                    <div class="stat-figure text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                    </div>
                    <div class="stat-title">Durchschnitt pro Tag</div>
                    <div class="stat-value">${userData.avg_hours_per_day.toFixed(2)}h</div>
                    <div class="stat-desc">Arbeitsstunden</div>
                </div>

                <div class="stat bg-base-100 rounded-box shadow-lg">
                    <div class="stat-figure ${userData.ueberstunden.startsWith('-') ? 'text-error' : 'text-success'}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div class="stat-title">Überstunden</div>
                    <div class="stat-value ${userData.ueberstunden.startsWith('-') ? 'text-error' : 'text-success'}">${userData.ueberstunden}</div>
                    <div class="stat-desc">${userData.ueberstunden.startsWith('-') ? 'Minusstunden' : 'Überstunden'}</div>
                </div>
            </div>

            <div class="alert alert-info shadow-lg mt-6">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <h3 class="font-bold">Hinweis</h3>
                    <div class="text-xs">Diese Übersicht basiert auf den aktuellen Daten und kann sich täglich ändern.</div>
                </div>
            </div>
        `;
            } else {
                modalContent.innerHTML = '<div class="alert alert-warning">Keine detaillierten Daten verfügbar.</div>';
            }

            modal.showModal();
        }

        // Initialize Charts
        function initializeCharts() {
            const overtimeCanvas = document.getElementById('overtimeChart');
            const regularHoursCanvas = document.getElementById('regularHoursChart');
            if (typeof Chart === 'undefined' || !overtimeCanvas || !regularHoursCanvas) return;

            // Convert the PHP array to the correct format for charts
            const overtimeData = Object.values(<?= json_encode($ueberstundenListe, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>).map(user => ({
                label: user.username,
                value: user.overtime_minutes / 60
            }));

            // Overtime Chart
            new Chart(overtimeCanvas, {
                type: 'bar',
                data: {
                    labels: overtimeData.map(d => d.label),
                    datasets: [{
                        label: 'Überstunden',
                        data: overtimeData.map(d => d.value),
                        backgroundColor: overtimeData.map(d => d.value >= 0 ?
                            'rgba(72, 187, 120, 0.7)' : 'rgba(245, 101, 101, 0.7)'),
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Stunden'
                            }
                        }
                    }
                }
            });

            // Convert the PHP array to the correct format for charts
            const workingHoursData = Object.values(<?= json_encode($detaillierteDaten, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>).map(user => ({
                label: user.username,
                value: user.total_hours
            }));

            // Working Hours Chart
            new Chart(regularHoursCanvas, {
                type: 'bar',
                data: {
                    labels: Object.values(<?= json_encode($detaillierteDaten, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>).map(d => d.username),
                    datasets: [{
                        label: 'Regelarbeitszeit (Stunden)',
                        data: Object.values(<?= json_encode($detaillierteDaten, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>).map(d => d.regelarbeitszeit),
                        backgroundColor: 'rgba(66, 153, 225, 0.7)',
                        borderColor: 'rgba(66, 153, 225, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Stunden pro Tag'
                            }
                        }
                    }
                }
            });
        }

        let cardSearchTerm = '';
        let cardFilter = 'all';

        function filterCards() {
            document.querySelectorAll('#overtimeCards .card').forEach(card => {
                const username = card.querySelector('.card-title')?.textContent.toLowerCase() ?? '';
                const minutes = Number(card.dataset.overtimeMinutes);
                const matchesType = cardFilter === 'all' ||
                    (cardFilter === 'overtime' && minutes > 0) ||
                    (cardFilter === 'undertime' && minutes < 0);
                card.style.display = username.includes(cardSearchTerm) && matchesType ? '' : 'none';
            });
        }

        // JavaScript only enhances existing server-rendered cards and detail rows.
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                cardSearchTerm = searchInput.value.toLowerCase();
                searchInput.addEventListener('input', function() {
                    cardSearchTerm = this.value.toLowerCase();
                    filterCards();
                });
            }

            document.querySelectorAll('.filter-badge').forEach(badge => {
                badge.addEventListener('click', function() {
                    cardFilter = this.dataset.filter;
                    document.querySelectorAll('.filter-badge').forEach(item => item.classList.remove('badge-accent'));
                    this.classList.add('badge-accent');
                    filterCards();
                });
            });
            filterCards();

            const tableBody = document.getElementById('timeRecordsBody');
            if (tableBody) {
                tableBody.querySelectorAll('.group-header').forEach(header => {
                    const detailRow = header.nextElementSibling;
                    if (!detailRow || !detailRow.classList.contains('detail-row')) return;
                    const toggle = header.querySelector('button[aria-expanded]');
                    const chevron = header.querySelector('.fa-chevron-right');

                    header.addEventListener('click', function() {
                        const collapsed = detailRow.classList.toggle('hidden');
                        if (toggle) toggle.setAttribute('aria-expanded', String(!collapsed));
                        if (chevron) chevron.style.transform = collapsed ? '' : 'rotate(90deg)';
                    });
                });
            }

            initializeCharts();
        });
    </script>
</body>

</html>
