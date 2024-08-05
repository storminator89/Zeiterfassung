<?php
include 'header.php';

// Fetch user's regular working hours
try {
    $stmt = $conn->prepare('SELECT regelarbeitszeit FROM users WHERE id = :user_id');
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $userRegularWorkingHours = $stmt->fetchColumn() ?? 8; // Default to 8 hours if not set
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}

// Pagination
$itemsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $itemsPerPage;

// Fetch total number of records
$stmt = $conn->prepare("SELECT COUNT(*) FROM zeiterfassung WHERE user_id = ?");
$stmt->execute([$user_id]);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $itemsPerPage);

// Fetch records for current page
$stmt = $conn->prepare("SELECT *, strftime('%W', startzeit) AS weekNumber FROM zeiterfassung WHERE user_id = ? ORDER BY startzeit DESC LIMIT ? OFFSET ?");
$stmt->execute([$user_id, $itemsPerPage, $offset]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" data-theme="<?= $theme_mode ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= TITLE ?></title>

    <link rel="icon" href="assets/kolibri_icon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/de.js"></script>
</head>

<body class="bg-gradient-to-br from-base-200 to-base-300 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Form Card -->
            <div class="lg:col-span-2">
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title text-3xl mb-6 flex items-center">
                            <img src="<?= $kolibri_icon ?>" alt="Quodara Chrono Logo" class="w-12 h-12 mr-4">
                            <?= TITLE ?>
                        </h2>

                        <form action="save.php" method="post" id="mainForm" class="space-y-6">
                            <input type="hidden" id="action" name="action" value="">
                            <div class="hidden">
                                <input type="datetime-local" id="startzeit" name="startzeit" required>
                                <input type="datetime-local" id="endzeit" name="endzeit">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <button type="button" id="startButton" class="btn btn-primary btn-lg"><i class="fas fa-sign-in-alt mr-2"></i><?= FORM_COME ?></button>
                                <button type="button" id="endButton" class="btn btn-secondary btn-lg"><i class="fas fa-sign-out-alt mr-2"></i><?= FORM_GO ?></button>
                            </div>

                            <div class="divider"></div>

                            <div class="form-control">
                                <label class="label" for="pauseManuell">
                                    <span class="label-text"><i class="fas fa-pause mr-2"></i><?= FORM_BREAK_MANUAL ?></span>
                                </label>
                                <input type="number" id="pauseManuell" name="pause" class="input input-bordered w-full" placeholder="<?= FORM_BREAK_MANUAL ?>">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="form-control">
                                    <label class="label" for="standort">
                                        <span class="label-text"><i class="fas fa-map-marker-alt mr-2"></i><?= FORM_LOCATION ?></span>
                                    </label>
                                    <select name="standort" class="select select-bordered w-full" required>
                                        <option value="">-</option>
                                        <option value="<?= LOCATION_OFFICE_VALUE ?>"><?= LOCATION_OFFICE ?></option>
                                        <option value="<?= LOCATION_HOME_OFFICE_VALUE ?>"><?= LOCATION_HOME_OFFICE ?></option>
                                        <option value="<?= LOCATION_BUSINESS_TRIP_VALUE ?>"><?= LOCATION_BUSINESS_TRIP ?></option>
                                    </select>
                                </div>

                                <div class="form-control">
                                    <label class="label" for="beschreibung">
                                        <span class="label-text"><i class="fas fa-info-circle mr-2"></i><?= FORM_COMMENT ?></span>
                                    </label>
                                    <textarea name="beschreibung" class="textarea textarea-bordered h-24" placeholder="<?= FORM_COMMENT ?>"></textarea>
                                </div>
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text"><i class="fas fa-calendar-check mr-2"></i><?= FORM_EVENT_TYPE ?></span>
                                </label>
                                <div class="flex flex-wrap gap-4">
                                    <label class="label cursor-pointer">
                                        <input type="radio" id="urlaub" name="ereignistyp" value="Urlaub" class="radio radio-primary">
                                        <span class="label-text ml-2"><i class="fas fa-umbrella-beach mr-2"></i><?= EVENT_VACATION ?></span>
                                    </label>
                                    <label class="label cursor-pointer">
                                        <input type="radio" id="feiertag" name="ereignistyp" value="Feiertag" class="radio radio-primary">
                                        <span class="label-text ml-2"><i class="fas fa-calendar-day mr-2"></i><?= EVENT_HOLIDAY ?></span>
                                    </label>
                                    <label class="label cursor-pointer">
                                        <input type="radio" id="krank" name="ereignistyp" value="Krank" class="radio radio-primary">
                                        <span class="label-text ml-2"><i class="fas fa-stethoscope mr-2"></i><?= EVENT_SICK ?></span>
                                    </label>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="form-control">
                                    <label class="label" for="dateRange">
                                        <span class="label-text"><i class="fas fa-calendar-alt mr-2"></i>Datumsbereich</span>
                                    </label>
                                    <input type="text" id="dateRange" name="dateRange" class="input input-bordered w-full" readonly>
                                </div>

                                <div class="form-control">
                                    <label class="label" for="datenEintragenButton">
                                        <span class="label-text">&nbsp;</span>
                                    </label>
                                    <button type="button" id="datenEintragenButton" class="btn btn-primary w-full">
                                        <i class="fas fa-calendar-plus mr-2"></i><?= BUTTON_SUBMIT_DATA ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Statistics Card -->
            <div>
                <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow duration-300">
                    <div class="card-body">
                        <h3 class="card-title text-2xl mb-4"><i class="fas fa-chart-bar mr-2"></i><?= STATISTICS_WORKING_TIMES ?></h3>
                        <div class="stats stats-vertical shadow">
                            <div class="stat">
                                <div class="stat-figure text-primary">
                                    <i class="fas fa-calendar-day fa-2x"></i>
                                </div>
                                <div class="stat-title"><?= TABLE_HEADER_WORKING_DAYS ?></div>
                                <div class="stat-value"><?= $workingDaysThisMonth ?></div>
                                <div class="stat-desc"><?= $currentMonthName ?></div>
                            </div>

                            <div class="stat">
                                <div class="stat-figure text-primary">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                                <div class="stat-title"><?= TABLE_HEADER_TOTAL_OVERTIME ?></div>
                                <div class="stat-value <?= $totalOverHours > 0 ? 'text-success' : 'text-error'; ?>">
                                    <?= $totalOverHoursFormatted ?>
                                </div>
                            </div>

                            <div class="stat">
                                <div class="stat-figure text-primary">
                                    <i class="fas fa-business-time fa-2x"></i>
                                </div>
                                <div class="stat-title"><?= TABLE_HEADER_REGULAR_WORKING_HOURS ?></div>
                                <div class="stat-value"><?= $userRegularWorkingHours ?></div>
                                <div class="stat-desc"><?= LABEL_HOURS_PER_DAY ?></div>
                            </div>
                        </div>

                        <?php if (!empty($feiertageDieseWoche)) : ?>
                            <div class="alert alert-info mt-4">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <h3 class="font-bold"><?= HOLIDAYS_THIS_WEEK ?></h3>
                                    <div class="text-xs">
                                        <?php foreach ($feiertageDieseWoche as $feiertag) : ?>
                                            <?php if ($lang === 'de') : ?>
                                                <p><?= getGermanDayName($feiertag['datum']) ?>, <?= date("d.m.Y", strtotime($feiertag['datum'])) ?> - <?= $feiertag['name'] ?></p>
                                            <?php else : ?>
                                                <p><?= date("l, d/m/Y", strtotime($feiertag['datum'])) ?> - <?= $feiertag['name'] ?></p>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Time Records Table -->
        <div class="card bg-base-100 shadow-xl mt-8">
            <div class="card-body">
                <h3 class="card-title text-2xl mb-4"><i class="fas fa-clock mr-2"></i><?= ACTUAL_WORKED_TIMES ?></h3>
                <div class="overflow-x-auto">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th><?= TABLE_HEADER_ID ?></th>
                                <th><?= TABLE_HEADER_WEEK ?></th>
                                <th><?= TABLE_HEADER_START_TIME ?></th>
                                <th><?= TABLE_HEADER_END_TIME ?></th>
                                <th><?= TABLE_HEADER_DURATION ?></th>
                                <th><?= TABLE_HEADER_BREAK ?></th>
                                <th><?= TABLE_HEADER_LOCATION ?></th>
                                <th><?= TABLE_HEADER_COMMENT ?></th>
                                <th><?= TABLE_HEADER_ACTIONS ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record) : ?>
                                <tr>
                                    <td><?= $record['id'] ?></td>
                                    <td><?= $record['weekNumber'] ?></td>
                                    <td>
                                        <input type="datetime-local" class="input input-bordered w-full max-w-xs editable" data-id="<?= $record['id'] ?>" data-field="startzeit" value="<?= date('Y-m-d\TH:i', strtotime($record['startzeit'])) ?>">
                                    </td>
                                    <td>
                                        <input type="datetime-local" class="input input-bordered w-full max-w-xs editable" data-id="<?= $record['id'] ?>" data-field="endzeit" value="<?= date('Y-m-d\TH:i', strtotime($record['endzeit'])) ?>">
                                    </td>
                                    <td><?= calculateDuration($record['startzeit'], $record['endzeit'], $record['pause']) ?></td>
                                    <td>
                                        <input type="number" class="input input-bordered w-full max-w-xs editable" data-id="<?= $record['id'] ?>" data-field="pause" value="<?= $record['pause'] ?>">
                                    </td>
                                    <td>
                                        <select class="select select-bordered w-full max-w-xs editable" data-id="<?= $record['id'] ?>" data-field="standort">
                                            <option value="<?= LOCATION_OFFICE_VALUE ?>" <?= $record['standort'] == LOCATION_OFFICE_VALUE ? 'selected' : '' ?>><?= LOCATION_OFFICE ?></option>
                                            <option value="<?= LOCATION_HOME_OFFICE_VALUE ?>" <?= $record['standort'] == LOCATION_HOME_OFFICE_VALUE ? 'selected' : '' ?>><?= LOCATION_HOME_OFFICE ?></option>
                                            <option value="<?= LOCATION_BUSINESS_TRIP_VALUE ?>" <?= $record['standort'] == LOCATION_BUSINESS_TRIP_VALUE ? 'selected' : '' ?>><?= LOCATION_BUSINESS_TRIP ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="input input-bordered w-full max-w-xs editable" data-id="<?= $record['id'] ?>" data-field="beschreibung" value="<?= htmlspecialchars($record['beschreibung']) ?>">
                                    </td>
                                    <td>
                                        <button class="btn btn-error btn-sm deleteRow" data-id="<?= $record['id'] ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="flex justify-center mt-4">
                    <div class="btn-group">
                        <?php if ($page > 1) : ?>
                            <a href="?page=1" class="btn">«</a>
                            <a href="?page=<?= $page - 1 ?>" class="btn">‹</a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($i = $start; $i <= $end; $i++) :
                        ?>
                            <a href="?page=<?= $i ?>" class="btn <?= $i === $page ? 'btn-active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages) : ?>
                            <a href="?page=<?= $page + 1 ?>" class="btn">›</a>
                            <a href="?page=<?= $totalPages ?>" class="btn">»</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Time tracking functionality
        let startButton = document.getElementById('startButton');
        let endButton = document.getElementById('endButton');
        let mainForm = document.getElementById('mainForm');

        startButton.addEventListener('click', function() {
            let now = new Date();
            let localISOTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            document.getElementById('startzeit').value = localISOTime;
            document.getElementById('endzeit').value = ''; // Ensure endzeit is empty
            document.getElementById('action').value = 'start';
            mainForm.submit();
        });

        endButton.addEventListener('click', function() {
            let now = new Date();
            let localISOTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            document.getElementById('endzeit').value = localISOTime;
            document.getElementById('action').value = 'end';
            mainForm.submit();
        });

        // Delete row functionality
        document.querySelectorAll('.deleteRow').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                if (confirm('Are you sure you want to delete this record?')) {
                    fetch('save.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `delete=true&id=${id}`,
                        })
                        .then(response => response.text())
                        .then(data => {
                            if (data === "Successfully deleted") {
                                location.reload();
                            } else {
                                alert(data);
                            }
                        })
                        .catch((error) => {
                            console.error('Error:', error);
                            alert('Error deleting record');
                        });
                }
            });
        });

        // Inline editing functionality
        document.querySelectorAll('.editable').forEach(input => {
            input.addEventListener('change', function() {
                const id = this.dataset.id;
                const field = this.dataset.field;
                const value = this.value;

                fetch('save.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `update=true&id=${id}&column=${field}&data=${encodeURIComponent(value)}`,
                    })
                    .then(response => response.text())
                    .then(data => {
                        if (data === "Successfully updated") {
                            console.log('Update successful');
                        } else {
                            alert(data);
                            // Revert the input to its original value
                            this.value = this.defaultValue;
                        }
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                        alert('Error updating record');
                        // Revert the input to its original value
                        this.value = this.defaultValue;
                    });
            });
        });

        // Date range picker initialization
        flatpickr("#dateRange", {
            mode: "range",
            dateFormat: "Y-m-d",
            locale: "de",
            onClose: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    let startDate = selectedDates[0];
                    let endDate = selectedDates[1];
                    let formattedStartDate = startDate.toLocaleDateString('de-DE');
                    let formattedEndDate = endDate.toLocaleDateString('de-DE');
                    instance.input.value = `${formattedStartDate} bis ${formattedEndDate}`;
                }
            }
        });

        // Special days booking functionality
        document.getElementById('datenEintragenButton').addEventListener('click', function() {
            var dateRange = document.getElementById('dateRange').value;
            var ereignistyp = document.querySelector('input[name="ereignistyp"]:checked');

            if (!ereignistyp) {
                alert('Bitte wählen Sie einen Ereignistyp aus.');
                return;
            }

            ereignistyp = ereignistyp.value;

            if (dateRange) {
                var [start, end] = dateRange.split(' bis ');
                if (start && end) {
                    // Convert dates to YYYY-MM-DD format for the server
                    start = start.split('.').reverse().join('-');
                    end = end.split('.').reverse().join('-');

                    fetch('save.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `urlaubStart=${start}&urlaubEnde=${end}&ereignistyp=${ereignistyp}`,
                        })
                        .then(response => response.text())
                        .then(data => {
                            alert(data);
                            location.reload();
                        })
                        .catch((error) => {
                            console.error('Error:', error);
                            alert('Error submitting special days');
                        });
                } else {
                    alert('Bitte wählen Sie einen gültigen Datumsbereich aus.');
                }
            } else {
                alert('Bitte wählen Sie einen Datumsbereich aus.');
            }
        });
    </script>
</body>

</html>