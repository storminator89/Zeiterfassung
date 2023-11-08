<?php include 'functions.php';
session_start() ?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZeitWerk - Zeiterfassung</title>
    <link rel="icon" href="assets/logo_zeiterfassung.png" type="image/png">

    <!-- Externe Stylesheets und Skripte -->
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



    <!-- Lokale Stylesheets und Skripte -->
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
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom pl-3">
        <a class="navbar-brand" href="index.php">
            <img class="pl-3 rotating-logo" src="assets\logo_zeiterfassung.png" alt="Arbeitszeiterfassung" height="50">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item active">
                    <a class="nav-link" href="#"><i class="fas fa-home mr-1"></i> Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt mr-1"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#aboutModal"><i class="fas fa-info-circle mr-1"></i> About</a>
                </li>
            </ul>
        </div>

    </nav>
    <div class="container mt-5 p-5">
        <h2 class="fancy-title"><i class="fas fa-hourglass-start mr-2"></i> ZeitWerk - Zeiterfassung</h2>
        <div class="row">
            <div class="col-md-12">
                <form action="save.php" method="post" id="mainForm">
                    <div class="row mb-3">
                        <div class="col">
                            <div class="form-group position-relative">
                                <label for="startzeit"><i class="fas fa-play mr-2"></i> Startzeit</label>
                                <input type="datetime-local" name="startzeit" class="form-control" required value="<?= date('Y-m-d\TH:i'); ?>">
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label for="endzeit"><i class="fas fa-stop mr-2"></i> Endzeit</label>
                                <input type="datetime-local" id="endzeit" name="endzeit" class="form-control">
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label for="pauseManuell"><i class="fas fa-pause mr-2"></i> Pause (Manuell)</label>
                                <input type="number" id="pauseManuell" class="form-control" placeholder="Manuell in Minuten">
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label for="pauseDisplay"><i class="fas fa-clock mr-2"></i> Pause (Minuten)</label>
                                <input type="text" id="pauseDisplay" class="form-control" placeholder="MM:SS" readonly>
                                <input type="hidden" id="pauseInput" name="pause">
                                <button id="pauseButton" type="button" class="btn btn-secondary mt-2"><i class="fas fa-pause-circle mr-1"></i> Pause starten/beenden</button>
                            </div>
                        </div>
                    </div>

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
                                <label for="beschreibung"><i class="fas fa-info-circle mr-2"></i> Beschreibung</label>
                                <select name="beschreibung" class="form-control">
                                    <option value="">-</option>
                                    <option value="Urlaub">Urlaub</option>
                                    <option value="Feiertag">Feiertag (wird als 8h gewertet)</option>
                                    <option value="Krankheit">Krankheit (wird als 8h gewertet)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle mr-1"></i> Hinzufügen</button>
                            </div>
                        </div>
                    </div>
                </form>
                <input type="hidden" id="isFirstWeek" value="<?php echo $isFirstWeek ? '1' : '0'; ?>">
                <div id="firstWeekNotification" class="row mb-3"></div>
            </div>
        </div>


        <div class="row mt-4">
            <div class="col-12">
                <div class="form-group">
                    <h3><i class="fas fa-chart-bar mr-2"></i> Statistik Arbeitszeiten</h3>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Arbeitstage <?= $currentMonthName ?></th>
                                <th>Arbeitsstunden diese Woche</th>
                                <th>Überstunden diese Woche</th>
                                <th>Arbeitsstunden im <?= $currentMonthName ?></th>
                                <th>Gesamtstunden aktueller Monat</th>
                                <th>Überstunden <?= $currentMonthName ?></th>
                                <th>Überstunden dieses Jahr</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?= $workingDaysThisMonth ?></td>
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
                    <th data-name="id">ID</th>
                    <th data-name="kalenderwoche">Kalenderwoche</th>
                    <th data-name="startzeit">Startzeit</th>
                    <th data-name="endzeit">Endzeit</th>
                    <th data-name="dauer">Dauer</th>
                    <th data-name="pause">Pause (Min.)</th>
                    <th data-name="standort">Standort</th> <!-- Hinzugefügte Spalte für Standort -->
                    <th data-name="beschreibung">Beschreibung</th>
                    <th data-name="aktion">Aktion</th>
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
                        <td><?= $record['id'] ?></td>
                        <td><?= $record['weekNumber'] ?></td>
                        <td><?= date("d.m.Y H:i:s", strtotime($record['startzeit'])) ?></td>
                        <td><?= $record['endzeit'] ? date("d.m.Y H:i:s", strtotime($record['endzeit'])) : '-' ?></td>
                        <td><?= $dauer ?></td>
                        <td><?= $record['pause'] ?></td>
                        <td><?= $record['standort'] ?></td> 
                        <td><?= $record['beschreibung'] ?></td>
                        <td>
                            <form action="delete.php" method="post">
                                <input type="hidden" name="id" value="<?= $record['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash-alt mr-1"></i> Löschen
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

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


</body>

</html>