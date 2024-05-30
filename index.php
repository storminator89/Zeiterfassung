<?php
session_start();

if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang = $_SESSION['lang'] ?? 'de';
require_once "languages/$lang.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'functions.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role']; // Benutzerrolle aus der Session abrufen

// Verbindung zur Datenbank herstellen
$conn = new PDO("sqlite:assets/db/timetracking.sqlite");
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <!-- Meta tags and title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= TITLE ?></title>

    <!-- Favicon and external stylesheets -->
    <link rel="icon" href="assets/kolibri_icon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.3.0/css/buttons.dataTables.min.css">
    <script src="https://cdn.datatables.net/buttons/2.3.0/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.0/js/buttons.html5.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/locale/de.js"></script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.22/sorting/datetime-moment.js"></script>

    <!-- Local stylesheets and scripts -->
    <link rel="stylesheet" type="text/css" href="./assets/css/main.css">
    <script>
        var totalHoursThisMonthFromRecords = <?= $totalHoursThisMonthFromRecords ?>;
        var workingDaysThisMonth = "<?= $workingDaysThisMonth ?>";
        var workingHoursThisMonth = <?= $workingHoursThisMonth ?>;
        var totalHoursThisWeek = <?= $totalHoursThisWeek ?>;
        var days = <?= json_encode($days) ?>;
        var hours = <?= json_encode($hours) ?>;
    </script>
    <script src="./assets/js/main.js"></script>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom pl-3">
        <a class="navbar-brand" href="index.php">
            <img class="pl-3" src="assets/kolibri_icon_weiß.png" alt="Time Tracking" height="50">
        </a>
        <!-- Sprachwahl -->
        <ul class="navbar-nav me-auto">
            <li class="nav-item">
                <a class="nav-link" href="?lang=de">DE</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="?lang=en">EN</a>
            </li>
        </ul>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item active">
                    <a class="nav-link" href="#"><i class="fas fa-home mr-1"></i> <?= NAV_HOME ?></a>
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
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#settingsModal"><i class="fas fa-cog mr-1"></i> <?= NAV_SETTINGS ?></a></li>
                        <?php if ($user_role === 'admin') : ?>
                            <li><a class="dropdown-item" href="admin.php"><i class="fas fa-user-shield mr-1"></i> Admin</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#aboutModal"><i class="fas fa-info-circle mr-1"></i> <?= NAV_ABOUT ?></a></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt mr-1"></i> <?= NAV_LOGOUT ?></a></li>
                        <li><button class="dropdown-item" onclick="toggleDarkMode()"><i class="fas fa-moon mr-1"></i> <?= NAV_DARK_MODE ?></button></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main content -->
    <div class="container mt-5 p-5">
        <h2 class="fancy-title">
            <img src="assets/kolibri_icon.png" alt="Quodara Chrono Logo" style="width: 80px; height: 80px; margin-right: 10px;">
            <?= TITLE ?>
        </h2>
        <div class="row">
            <div class="col-md-12">
                <form action="save.php" method="post" id="mainForm">
                    <!-- First row of the form -->
                    <div class="row mb-3">
                        <div class="col" style="display: none;">
                            <div class="form-group position-relative">
                                <label for="startzeit"><i class="fas fa-play mr-2"></i> <?= FORM_START_TIME ?></label>
                                <input type="datetime-local" id="startzeit" name="startzeit" class="form-control" required>
                            </div>
                        </div>

                        <div class="col" style="display: none;">
                            <div class="form-group">
                                <label for="endzeit"><i class="fas fa-stop mr-2"></i> <?= FORM_END_TIME ?></label>
                                <input type="datetime-local" id="endzeit" name="endzeit" class="form-control">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <div class="form-group mb-4">
                                    <button type="button" id="startButton" class="btn btn-primary btn-block btn-lg"><i class="fas fa-sign-in-alt"></i> <?= FORM_COME ?></button>
                                </div>
                            </div>

                            <div class="col">
                                <div class="form-group mb-4">
                                    <button type="button" id="endButton" class="btn btn-success btn-block btn-lg"><i class="fas fa-sign-out-alt"></i> <?= FORM_GO ?></button>
                                </div>
                            </div>
                        </div>

                        <div class="col">
                            <div class="form-group">
                                <label for="pauseManuell"><i class="fas fa-pause mr-2"></i> <?= FORM_BREAK_MANUAL ?></label>
                                <input type="number" id="pauseManuell" class="form-control" placeholder="<?= FORM_BREAK_MANUAL ?>">
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label for="pauseDisplay"><i class="fas fa-clock mr-2"></i> <?= FORM_BREAK_MINUTES ?></label>
                                <input type="text" id="pauseDisplay" class="form-control" placeholder="MM:SS" readonly>
                                <input type="hidden" id="pauseInput" name="pause">
                                <button id="pauseButton" type="button" class="btn btn-secondary mt-2"><i class="fas fa-pause-circle mr-1"></i> <?= FORM_START_BREAK ?></button>
                            </div>
                        </div>
                    </div>

                    <!-- Second row of the form -->
                    <div class="row mb-3">
                        <div class="col">
                            <div class="form-group">
                                <label for="standort"><i class="fas fa-map-marker-alt mr-2"></i> <?= FORM_LOCATION ?></label>
                                <select name="standort" class="form-control" required>
                                    <option value="">-</option>
                                    <option value="Büro">Büro</option>
                                    <option value="Home Office">Home Office</option>
                                    <option value="Dienstreise">Dienstreise</option>
                                </select>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label for="beschreibung"><i class="fas fa-info-circle mr-2"></i> <?= FORM_COMMENT ?></label>
                                <textarea name="beschreibung" class="form-control" rows="4" placeholder="<?= FORM_COMMENT ?>"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-check mr-2"></i> <?= FORM_EVENT_TYPE ?>:</label>
                                <div class="d-flex justify-content-start">
                                    <div class="form-check form-check-inline">
                                        <input type="radio" id="urlaub" name="ereignistyp" value="Urlaub" class="form-check-input" checked>
                                        <label class="form-check-label" for="urlaub"><i class="fas fa-umbrella-beach mr-2"></i> <?= EVENT_VACATION ?></label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input type="radio" id="feiertag" name="ereignistyp" value="Feiertag" class="form-check-input">
                                        <label class="form-check-label" for="feiertag"><i class="fas fa-gift mr-2"></i> <?= EVENT_HOLIDAY ?></label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input type="radio" id="krank" name="ereignistyp" value="Krank" class="form-check-input">
                                        <label class="form-check-label" for="krank"><i class="fas fa-bed mr-2"></i> <?= EVENT_SICK ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col">
                            <div class="form-group">
                                <label for="urlaubStart"><i class="fas fa-calendar-alt mr-2"></i> <?= FORM_START ?>:</label>
                                <input type="date" name="urlaubStart" id="urlaubStart" class="form-control" required>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label for="urlaubEnde"><i class="fas fa-calendar-alt mr-2"></i> <?= FORM_END ?>:</label>
                                <input type="date" name="urlaubEnde" id="urlaubEnde" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <button type="button" id="datenEintragenButton" class="btn btn-primary">
                        <i class="fas fa-plane-departure mr-2"></i> <?= BUTTON_SUBMIT_DATA ?>
                    </button>

                    <!-- Third row of the form -->
                    <div class="row mb-3">
                        <div class="col">
                            <div class="form-group">
                                <button type="submit" id="addButton" class="btn btn-primary" style="display: none;"><i class="fas fa-plus-circle mr-1"></i> <?= BUTTON_ADD_BOOKING ?></button>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="importModalLabel"><?= BUTTON_IMPORT_DB ?></h5>
                                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form id="importForm" enctype="multipart/form-data">
                                        <div class="form-group">
                                            <label for="dbFile"><?= BUTTON_IMPORT_DB ?></label>
                                            <input type="file" class="form-control-file" id="dbFile" name="dbFile">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= BUTTON_CLOSE ?></button>
                                            <button type="submit" class="btn btn-primary" id="importButton"><?= BUTTON_IMPORT ?></button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="isFirstWeek" value="<?php echo $isFirstWeek ? '1' : '0'; ?>">
                    <div id="firstWeekNotification" class="row mb-3"></div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="form-group">
                    <h3><i class="fas fa-chart-bar mr-2"></i> <?= STATISTICS_WORKING_TIMES ?></h3>
                    <table class="details-tablestats">
                        <thead>
                            <tr>
                                <th><?= TABLE_HEADER_WORKING_DAYS ?> <?= $currentMonthName ?></th>
                                <th><?= TABLE_HEADER_TOTAL_OVERTIME ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?= $workingDaysThisMonth ?></td>
                                <td class="<?= $totalOverHours > 0 ? 'positive-overhours' : 'negative-overhours'; ?>" style="font-weight: bold;">
                                    <?= $totalOverHoursFormatted ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <?php if (!empty($feiertageDieseWoche)) : ?>
                        <div class="rtd-infobox">
                            <div class="rtd-infobox-header"><i class="fas fa-info-circle"></i> <?= HOLIDAYS_THIS_WEEK ?>:</div>
                            <div class="rtd-infobox-content">
                                <ul>
                                    <?php foreach ($feiertageDieseWoche as $feiertag) : ?>
                                        <li><?= getGermanDayName($feiertag['datum']) ?>, <?= date("d.m.Y", strtotime($feiertag['datum'])) ?> - <?= $feiertag['name'] ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <h3 class="mt-4"><i class="fas fa-business-time mr-2"></i> <?= STATISTICS_WORKING_TIMES ?></h3>
        <table class="table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th data-name="id"><?= TABLE_HEADER_ID ?></th>
                    <th data-name="kalenderwoche"><?= TABLE_HEADER_WEEK ?></th>
                    <th data-name="startzeit"><?= TABLE_HEADER_START_TIME ?></th>
                    <th data-name="endzeit"><?= TABLE_HEADER_END_TIME ?></th>
                    <th data-name="dauer"><?= TABLE_HEADER_DURATION ?></th>
                    <th data-name="pause"><?= TABLE_HEADER_BREAK ?></th>
                    <th data-name="standort"><?= TABLE_HEADER_LOCATION ?></th>
                    <th data-name="beschreibung"><?= TABLE_HEADER_COMMENT ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Aktualisieren Sie die Abfrage, um benutzerspezifische Zeiterfassungen anzuzeigen
                $stmt = $conn->prepare("SELECT *, strftime('%W', startzeit) AS weekNumber FROM zeiterfassung WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($records as $record) {
                    $start = new DateTime($record['startzeit']);
                    $end = new DateTime($record['endzeit']);
                    $interval = $start->diff($end);
                    $pauseMinuten = intval($record['pause']);

                    $gesamtMinuten = ($interval->h * 60 + $interval->i) - $pauseMinuten;
                    $stunden = floor($gesamtMinuten / 60);
                    $minuten = $gesamtMinuten % 60;
                    $dauer = "{$stunden} Stunden {$minuten} Minuten";
                ?>
                    <tr>
                        <td><input type="checkbox" class="selectRow" data-id="<?= $record['id'] ?>"></td>
                        <td><?= $record['id'] ?></td>
                        <td><?= $record['weekNumber'] ?></td>
                        <td><?= date("d.m.Y H:i:s", strtotime($record['startzeit'])) ?></td>
                        <td><?= $record['endzeit'] ? date("d.m.Y H:i:s", strtotime($record['endzeit'])) : '-' ?></td>
                        <td><?= $dauer ?></td>
                        <td><?= $record['pause'] ?></td>
                        <td><?= $record['standort'] ?></td>
                        <td><?= $record['beschreibung'] ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <button type="button" id="deleteSelected" class="btn btn-danger"><?= BUTTON_DELETE_SELECTED ?></button>

        <div class="row">
            <div class="col-4">
                <h3 class="main-title">
                    <i class="fas fa-chevron-down mr-2"></i> Feiertage
                </h3>
                <div class="toggle-content">
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Tag</th>
                                <th>Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feiertageThisYear as $feiertag) { ?>
                                <tr>
                                    <td><?= date("d.m.Y", strtotime($feiertag['datum'])) ?></td>
                                    <td><?= getGermanDayName(date("Y-m-d", strtotime($feiertag['datum']))) ?></td>
                                    <td><?= $feiertag['name'] ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer mt-auto py-3">
            <div class="container">
                <span class="text-muted"><?= FOOTER_TEXT ?></span>
            </div>
        </footer>

        <!-- About modal -->
        <div class="modal fade" id="aboutModal" tabindex="-1" role="dialog" aria-labelledby="aboutModalLabel">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title" id="aboutModalLabel"><?= NAV_ABOUT ?></h4>
                    </div>
                    <div class="modal-body">
                        <p><?= ABOUT_TOOL_TEXT ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Setting modal -->
        <div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="settingsModalLabel"><?= NAV_SETTINGS ?></h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <a href="download.php" id="downloadDbButton" class="btn btn-secondary">
                            <i class="fas fa-download mr-2"></i>
                            <?= BUTTON_DOWNLOAD_BACKUP ?>
                        </a>
                        <button type="button" id="importDbButton" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="fas fa-file-upload mr-2"></i>
                            <?= BUTTON_IMPORT_DB ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

</body>

</html>
