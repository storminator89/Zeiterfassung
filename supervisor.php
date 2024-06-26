<?php
session_start();
include 'config.php';

// Sprachdateien laden
$lang = $_SESSION['lang'] ?? 'de';
$langFile = "languages/$lang.php";

if (file_exists($langFile)) {
    require_once $langFile;
} else {
    die("Sprachdatei nicht gefunden!");
}

// Überprüfen, ob der Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';
$theme_mode = $_SESSION['theme_mode'] ?? 'system'; // Default to system preference

// Zeiten der unterstellten Benutzer aus der Datenbank abrufen
$zeiten = [];
$ueberstundenListe = [];
if ($user_role === 'admin' || $user_role === 'supervisor') { // Je nach Bedarf kann der Admin hier auch berücksichtigt werden
    $stmt = $conn->prepare("SELECT z.*, u.username, u.id as user_id, u.regelarbeitszeit, strftime('%Y-%m-%d', z.startzeit) AS day, strftime('%W', z.startzeit) AS weekNumber 
                            FROM zeiterfassung z
                            JOIN users u ON z.user_id = u.id
                            WHERE u.supervisor_id = ?");
    $stmt->execute([$user_id]);
    $zeiten = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Berechnung der Überstunden
    $workHoursByUser = [];
    foreach ($zeiten as $zeit) {
        $userId = $zeit['user_id'];
        $day = $zeit['day'];
        $regelarbeitszeit = $zeit['regelarbeitszeit'] ?? 8.0; // Standard: 8 Stunden

        if (!isset($workHoursByUser[$userId])) {
            $workHoursByUser[$userId] = [
                'username' => $zeit['username'],
                'days' => [],
                'regelarbeitszeit' => $regelarbeitszeit
            ];
        }

        if (!isset($workHoursByUser[$userId]['days'][$day])) {
            $workHoursByUser[$userId]['days'][$day] = 0;
        }

        $start = new DateTime($zeit['startzeit']);
        $end = new DateTime($zeit['endzeit']);
        $interval = $start->diff($end);
        $pauseMinuten = intval($zeit['pause']) ?: 0;

        $gesamtMinuten = ($interval->h * 60 + $interval->i) - $pauseMinuten;
        $workHoursByUser[$userId]['days'][$day] += $gesamtMinuten;
    }

    // Berechnung der Gesamtüberstunden pro Benutzer
    foreach ($workHoursByUser as $userId => $data) {
        $totalOverMinutes = 0;
        $regelarbeitszeit = $data['regelarbeitszeit'];
        foreach ($data['days'] as $day => $totalMinutes) {
            $regularWorkingMinutesPerDay = $regelarbeitszeit * 60; // Regelarbeitszeit pro Tag in Minuten

            $overMinutes = $totalMinutes - $regularWorkingMinutesPerDay;
            $totalOverMinutes += $overMinutes;
        }

        $isNegative = $totalOverMinutes < 0;
        $totalOverHours = floor(abs($totalOverMinutes) / 60);
        $totalOverMinutes = abs($totalOverMinutes % 60);

        // Formatierung der Überstunden
        $ueberstundenListe[$userId] = [
            'username' => $data['username'],
            'ueberstunden' => ($isNegative ? '-' : '') . sprintf("%02d:%02d", $totalOverHours, $totalOverMinutes)
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SUPERVISOR_TIMES_TITLE ?></title>
    <link rel="icon" href="assets/kolibri_icon.png" type="image/png">
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="./assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.flash.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    <script src="./assets/js/main.js"></script>
</head>

<body class="<?= $theme_mode === 'dark' ? 'dark-mode' : '' ?>">
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom pl-3">
        <a class="navbar-brand" href="index.php">
            <img class="pl-3" src="<?= $theme_mode === 'dark' ? 'assets/kolibri_icon_weiß.png' : 'assets/kolibri_icon.png' ?>" alt="Time Tracking" height="50">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item active">
                    <a class="nav-link" href="index.php"><i class="fas fa-home mr-1"></i> <?= NAV_HOME ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt mr-1"></i> <?= NAV_DASHBOARD ?></a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-cog"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="settingsDropdown">
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog mr-1"></i> <?= NAV_SETTINGS ?></a></li>
                        <?php if ($user_role === 'admin') : ?>
                            <li><a class="dropdown-item" href="admin.php"><i class="fas fa-user-shield mr-1"></i> Admin</a></li>
                        <?php endif; ?>
                        <?php if ($user_role === 'supervisor' || $user_role === 'admin') : ?>
                            <li><a class="dropdown-item" href="supervisor.php"><i class="fas fa-user-tie mr-1"></i> <?= NAV_SUPERVISOR ?></a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#aboutModal"><i class="fas fa-info-circle mr-1"></i> <?= NAV_ABOUT ?></a></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt mr-1"></i> <?= NAV_LOGOUT ?></a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
    <div class="container mt-5 p-5">
        <h2><?= SUPERVISOR_TIMES_TITLE ?></h2>
        <?php if ($user_role !== 'supervisor' && $user_role !== 'admin') : ?>
            <div class="alert alert-warning" role="alert">
                <?= NOT_SUPERVISOR_MESSAGE ?>
            </div>
        <?php else : ?>
            <h3 class="mt-4"><i class="fas fa-clock mr-2"></i> <?= ACTUAL_WORKED_TIMES ?></h3>
            <table class="table table-striped table-bordered" id="zeitenTable">
                <thead>
                    <tr>
                        <th><?= TABLE_HEADER_ID ?></th>
                        <th><?= TABLE_HEADER_USERNAME ?></th>
                        <th><?= TABLE_HEADER_WEEK ?></th>
                        <th><?= TABLE_HEADER_START_TIME ?></th>
                        <th><?= TABLE_HEADER_END_TIME ?></th>
                        <th><?= TABLE_HEADER_DURATION ?></th>
                        <th><?= TABLE_HEADER_BREAK ?></th>
                        <th><?= TABLE_HEADER_LOCATION ?></th>
                        <th><?= TABLE_HEADER_COMMENT ?></th>
                        <th><?= TABLE_HEADER_OVERTIME ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($zeiten)) : ?>
                        <tr>
                            <td colspan="11"><?= NO_ENTRIES_FOUND ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($zeiten as $zeit) : ?>
                            <?php
                            $start = new DateTime($zeit['startzeit']);
                            $end = new DateTime($zeit['endzeit']);
                            $interval = $start->diff($end);
                            $pauseMinuten = intval($zeit['pause']) ?: 0;

                            $gesamtMinuten = ($interval->h * 60 + $interval->i) - $pauseMinuten;
                            $stunden = floor($gesamtMinuten / 60);
                            $minuten = $gesamtMinuten % 60;
                            $dauer = "{$stunden} " . LABEL_HOURS . " {$minuten} " . LABEL_MINUTES;

                            $regelarbeitszeit = $zeit['regelarbeitszeit'] ?? 8.0; // Standard: 8 Stunden
                            $regularWorkingMinutesPerDay = $regelarbeitszeit * 60; // Regelarbeitszeit pro Tag in Minuten
                            $ueberstunden = $gesamtMinuten - $regularWorkingMinutesPerDay;
                            $ueberstundenStunden = floor(abs($ueberstunden) / 60);
                            $ueberstundenMinuten = abs($ueberstunden % 60);
                            $ueberstundenFormat = ($ueberstunden < 0 ? '-' : '') . sprintf("%02d:%02d", $ueberstundenStunden, $ueberstundenMinuten);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($zeit['id']) ?></td>
                                <td><?= htmlspecialchars($zeit['username']) ?></td>
                                <td><?= htmlspecialchars($zeit['weekNumber']) ?></td>
                                <td><?= date($lang === 'de' ? "d.m.Y H:i:s" : "d/m/Y H:i:s", strtotime($zeit['startzeit'])) ?></td>
                                <td><?= $zeit['endzeit'] ? date($lang === 'de' ? "d.m.Y H:i:s" : "d/m/Y H:i:s", strtotime($zeit['endzeit'])) : '-' ?></td>
                                <td><?= htmlspecialchars($dauer) ?></td>
                                <td><?= htmlspecialchars($zeit['pause']) ?></td>
                                <td><?= htmlspecialchars($zeit['standort']) ?></td>
                                <td><?= htmlspecialchars($zeit['beschreibung']) ?></td>
                                <td><?= htmlspecialchars($ueberstundenFormat) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h3 class="mt-4"><i class="fas fa-hourglass mr-2"></i> <?= TOTAL_OVERTIME_TITLE ?></h3>
            <table class="table table-striped table-bordered" id="ueberstundenTable">
                <thead>
                    <tr>
                        <th><?= TABLE_HEADER_USERNAME ?></th>
                        <th><?= TABLE_HEADER_TOTAL_OVERTIME ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (is_array($ueberstundenListe) && !empty($ueberstundenListe)) : ?>
                        <?php foreach ($ueberstundenListe as $userId => $data) : ?>
                            <tr>
                                <td><?= htmlspecialchars($data['username']) ?></td>
                                <td><?= htmlspecialchars($data['ueberstunden']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="2"><?= NO_ENTRIES_FOUND ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <script>
        $(document).ready(function() {
            <?php if (!empty($zeiten)) : ?>
                $('#zeitenTable').DataTable({
                    dom: 'Bfrtip',
                    buttons: [
                        'copy', 'csv', 'excel', 'pdf', 'print'
                    ],
                    columnDefs: [{
                        className: "text-center",
                        "targets": "_all"
                    }],
                    order: [
                        [3, 'desc']
                    ],
                    paging: true
                });
            <?php endif; ?>
        });
    </script>

</body>

</html>
