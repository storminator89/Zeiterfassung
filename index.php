<?php include 'functions.php'; ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zeiterfassung Arbeit</title>
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
                    <a class="nav-link" href="#">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#aboutModal">About</a>
                </li>
            </ul>
        </div>
    </nav>
    <div class="container mt-5 p-5">
        <h2>Zeiterfassung Arbeit</h2>
        <div class="row">
            <div class="col-md-12">
                <form action="save.php" method="post">
                    <div class="row mb-3">
                        <!-- Startzeit -->
                        <div class="col">
                            <div class="form-group">
                                <label for="startzeit">Startzeit</label>
                                <input type="datetime-local" name="startzeit" class="form-control" required value="<?= date('Y-m-d\TH:i'); ?>">
                            </div>
                        </div>
                        <!-- Endzeit -->
                        <div class="col">
                            <div class="form-group">
                                <label for="endzeit">Endzeit</label>
                                <input type="datetime-local" id="endzeit" name="endzeit" class="form-control">
                            </div>
                        </div>
                        <!-- Pause Manuell -->
                        <div class="col">
                            <div class="form-group">
                                <label for="pauseManuell">Pause (Manuell)</label>
                                <input type="number" id="pauseManuell" class="form-control" placeholder="Manuell in Minuten">
                            </div>
                        </div>
                        <!-- Pause in Minuten -->
                        <div class="col">
                            <div class="form-group">
                                <label for="pauseDisplay">Pause (Minuten)</label>
                                <input type="text" id="pauseDisplay" class="form-control" placeholder="MM:SS" readonly>
                                <input type="hidden" id="pauseInput" name="pause">
                                <button id="pauseButton" type="button" class="btn btn-secondary mt-2">Pause starten/beenden</button>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <!-- Standort -->
                        <div class="col">
                            <div class="form-group">
                                <label for="standort">Standort</label>
                                <select name="standort" class="form-control" required>
                                    <option value="">-</option>
                                    <option value="Büro">Büro</option>
                                    <option value="Home Office">Home Office</option>
                                    <option value="Dienstreise">Dienstreise</option>
                                </select>
                            </div>
                        </div>
                        <!-- Beschreibung -->
                        <div class="col">
                            <div class="form-group">
                                <label for="beschreibung">Beschreibung</label>
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
                        <!-- Hinzufügen Button -->
                        <div class="col">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Hinzufügen</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="form-group">
                    <h3>Statistik Arbeitszeiten</h3>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Arbeitstage <?= $currentMonthName ?></th>
                                <th>Arbeitsstunden im <?= $currentMonthName ?></th>
                                <th>Gesamtstunden aktueller Monat</th>
                                <th>Überstunden <?= $currentMonthName ?></th>
                                <th>Überstunden dieses Jahr</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?= $workingDaysThisMonth ?></td>
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
                </div>
            </div>
        </div>
        <br>

        <h3>Arbeitszeiten</h3>
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
                <?php foreach ($records as $record) {
                    $start = new DateTime($record['startzeit']);
                    $end = new DateTime($record['endzeit']);
                    $interval = $start->diff($end);

                    // Dauer ohne Pause berechnen
                    $gesamtMinuten = ($interval->h * 60 + $interval->i) - $record['pause'];
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
                        <td><?= $record['standort'] ?></td> <!-- Zeige den Standort hier an -->
                        <td><?= $record['beschreibung'] ?></td>
                        <td>
                            <form action="delete.php" method="post">
                                <input type="hidden" name="id" value="<?= $record['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
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