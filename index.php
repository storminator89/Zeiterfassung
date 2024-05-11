<?php
session_start();
require_once 'functions.php';
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <!-- Meta tags and title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quodara Chrono - Time Tracking</title>

    <!-- Favicon -->
    <link rel="icon" href="assets/kolibri_icon.png" type="image/png">

    <!-- External stylesheets and scripts -->
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
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Navigation items -->
            <ul class="navbar-nav ml-auto">
                <li class="nav-item active">
                    <a class="nav-link" href="#"><i class="fas fa-home mr-1"></i> Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt mr-1"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#settingsModal"><i class="fas fa-cog mr-1"></i> Einstellungen</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#aboutModal"><i class="fas fa-info-circle mr-1"></i> About</a>
                </li>
            </ul>
            <button class="dark-mode-toggle me-3" onclick="toggleDarkMode()">
                <i class="fas fa-moon fa-2x"></i>
            </button>
        </div>
    </nav>

    <!-- Main content -->
    <div class="container mt-5 p-5">
        <h2 class="fancy-title">
            <img src="assets/kolibri_icon.png" alt="Quodora Chrono Logo" style="width: 80px; height: 80px; margin-right: 10px;">
            Quodara Chrono - Zeiterfassung
        </h2>
        <div class="row">
            <div class="col-md-12">
                <!-- Main form -->
                <form action="save.php" method="post" id="mainForm">
                    <!-- First row of the form -->
                    <div class="row mb-3">
                        <div class="col" style="display: none;">
                            <div class="form-group position-relative">
                                <label for="startzeit"><i class="fas fa-play mr-2"></i> Startzeit</label>
                                <input type="datetime-local" id="startzeit" name="startzeit" class="form-control" required>
                            </div>
                        </div>

                        <div class="col" style="display: none;">
                            <div class="form-group">
                                <label for="endzeit"><i class="fas fa-stop mr-2"></i> Endzeit</label>
                                <input type="datetime-local" id="endzeit" name="endzeit" class="form-control">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <div class="form-group mb-4">
                                    <button type="button" id="startButton" class="btn btn-primary btn-block btn-lg"><i class="fas fa-sign-in-alt"></i> Kommen</button>
                                </div>
                            </div>

                            <div class="col">
                                <div class="form-group mb-4">
                                    <button type="button" id="endButton" class="btn btn-success btn-block btn-lg"><i class="fas fa-sign-out-alt"></i> Gehen</button>
                                </div>
                            </div>
                        </div>

                        <div class="col">
                            <div class="form-group">
                                <label for="pauseManuell"><i class="fas fa-pause mr-2"></i> Pause (manuell)</label>
                                <input type="number" id="pauseManuell" class="form-control" placeholder="Manuelle Eingabe (Minuten)">
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label for="pauseDisplay"><i class="fas fa-clock mr-2"></i> Pause (Minuten)</label>
                                <input type="text" id="pauseDisplay" class="form-control" placeholder="MM:SS" readonly>
                                <input type="hidden" id="pauseInput" name="pause">
                                <button id="pauseButton" type="button" class="btn btn-secondary mt-2"><i class="fas fa-pause-circle mr-1"></i> Start/Ende Pause</button>
                            </div>
                        </div>
                    </div>

                    <!-- Second row of the form -->
                    <div class="row mb-3">
                        <div class="col">
                            <div class="form-group">
                                <label for="standort"><i class="fas fa-map-marker-alt mr-2"></i> Standort</label>
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
                                <label for="beschreibung"><i class="fas fa-info-circle mr-2"></i> Kommentar</label>
                                <textarea name="beschreibung" class="form-control" rows="4" placeholder="Hier können Sie einen Kommentar hinterlassen..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-check mr-2"></i> Ereignistyp:</label>
                                <div class="d-flex justify-content-start">
                                    <div class="form-check form-check-inline">
                                        <input type="radio" id="urlaub" name="ereignistyp" value="Urlaub" class="form-check-input" checked>
                                        <label class="form-check-label" for="urlaub"><i class="fas fa-umbrella-beach mr-2"></i> Urlaub</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input type="radio" id="feiertag" name="ereignistyp" value="Feiertag" class="form-check-input">
                                        <label class="form-check-label" for="feiertag"><i class="fas fa-gift mr-2"></i> Feiertag</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input type="radio" id="krank" name="ereignistyp" value="Krank" class="form-check-input">
                                        <label class="form-check-label" for="krank"><i class="fas fa-bed mr-2"></i> Krank</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col">
                            <div class="form-group">
                                <label for="urlaubStart"><i class="fas fa-calendar-alt mr-2"></i> Beginn:</label>
                                <input type="date" name="urlaubStart" id="urlaubStart" class="form-control" required>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label for="urlaubEnde"><i class="fas fa-calendar-alt mr-2"></i> Ende:</label>
                                <input type="date" name="urlaubEnde" id="urlaubEnde" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <button type="button" id="datenEintragenButton" class="btn btn-primary">
                        <i class="fas fa-plane-departure mr-2"></i> Daten eintragen
                    </button>



                    <!-- Third row of the form -->
                    <div class="row mb-3">
                        <div class="col">
                            <div class="form-group">
                                <button type="submit" id="addButton" class="btn btn-primary" style="display: none;"><i class="fas fa-plus-circle mr-1"></i> Buchen</button>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="importModalLabel">Datenbank importieren</h5>
                                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form id="importForm" enctype="multipart/form-data">
                                        <div class="form-group">
                                            <label for="dbFile">Datenbankdatei auswählen</label>
                                            <input type="file" class="form-control-file" id="dbFile" name="dbFile">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                                            <button type="submit" class="btn btn-primary" id="importButton">Importieren</button>
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
                    <h3><i class="fas fa-chart-bar mr-2"></i> Statistik Arbeitszeiten</h3>
                    <table class="details-tablestats">
                        <thead>
                            <tr>
                                <th>Arbeitstage <?= $currentMonthName ?></th>
                                <th>Gesamtüberstunden</th>
                                <!-- Ausgeblendete Spalten
                                <th>Arbeitsstunden diese Woche</th>
                                <th>Zu Arbeiten noch diese Woche</th>
                                <th>Arbeitsstunden im <?= $currentMonthName ?></th>
                                <th>Gesamtstunden aktueller Monat</th>
                                <th>Zu Arbeiten <?= $currentMonthName ?></th>
                                <th>Zu Arbeiten dieses Jahr</th>
                                -->
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?= $workingDaysThisMonth ?></td>
                                <td class="<?= $totalOverHours > 0 ? 'positive-overhours' : 'negative-overhours'; ?>" style="font-weight: bold;">
                                    <?= $totalOverHoursFormatted ?>
                                </td>
                                <!-- Ausgeblendete Zellen
                                <td><?= number_format($totalHoursThisWeek, 1) ?></td>
                                <td>
                                    <?php if ($overHoursThisWeek > 0) : ?>
                                        <?= $overHoursThisWeek ?> Überstunden
                                    <?php elseif ($overHoursThisWeek < 0) : ?>
                                        - <?= abs($overHoursThisWeek) ?> Stunden
                                    <?php else : ?>
                                        erwarteten Stunden
                                    <?php endif; ?>
                                </td>
                                <td><?= $workingHoursThisMonth ?></td>
                                <td><?= $totalHoursThisMonthFromRecords ?></td>
                                <td>
                                    <?php if ($overHoursThisMonth > 0) : ?>
                                        <?= $overHoursThisMonth ?> Überstunden
                                    <?php elseif ($overHoursThisMonth < 0) : ?>
                                        - <?= abs($overHoursThisMonth) ?> Stunden
                                    <?php else : ?>
                                        erwarteten Stunden
                                    <?php endif; ?>
                                </td>
                                <td><?= $overHoursThisYear ?></td>
                                -->
                            </tr>
                        </tbody>
                    </table>
                    <?php if (!empty($feiertageDieseWoche)) : ?>
                        <div class="rtd-infobox">
                            <div class="rtd-infobox-header"><i class="fas fa-info-circle"></i> Feiertage diese Woche:</div>
                            <div class="rtd-infobox-content">
                                <ul>
                                    <?php foreach ($feiertageDieseWoche as $feiertag) : ?>
                                        <li><?= getGermanDayName($feiertag['Datum']) ?>, <?= date("d.m.Y", strtotime($feiertag['Datum'])) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <h3 class="mt-4"><i class="fas fa-business-time mr-2"></i> Arbeitszeiten</h3>
        <table class="table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th data-name="id">ID</th>
                    <th data-name="kalenderwoche">KW</th>
                    <th data-name="startzeit">Startzeit</th>
                    <th data-name="endzeit">Endzeit</th>
                    <th data-name="dauer">Dauer</th>
                    <th data-name="pause">Pause (Min.)</th>
                    <th data-name="standort">Standort</th>
                    <th data-name="beschreibung">Bemerkung</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($records as $record) {
                    $start = new DateTime($record['startzeit']);
                    $end = new DateTime($record['endzeit']);
                    $interval = $start->diff($end);
                    $pauseMinuten = intval($record['pause']);

                    // Dauer ohne Pause berechnen
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

        <button type="button" id="deleteSelected" class="btn btn-danger">Ausgewählte löschen</button>

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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feiertageThisYear as $feiertag) { ?>
                                <tr>
                                    <td><?= date("d.m.Y", strtotime($feiertag['Datum'])) ?></td>
                                    <td><?= getGermanDayName($feiertag['Datum']) ?></td>
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
                <span class="text-muted">© 2023 Quodara Chrono - Zeiterfassung</span>
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
                        <h4 class="modal-title" id="aboutModalLabel">About</h4>
                    </div>
                    <div class="modal-body">
                        <p>Das Tool hilft für die Arbeitszeiterfassung</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Setting modal -->
        <div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="settingsModalLabel">Einstellungen</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <a href="download.php" id="downloadDbButton" class="btn btn-secondary">
                            <i class="fas fa-download mr-2"></i>
                            Datenbank Backup Download
                        </a>
                        <button type="button" id="importDbButton" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="fas fa-file-upload mr-2"></i>
                            Datenbank importieren
                        </button>
                    </div>
                </div>
            </div>
        </div>

</body>

</html>