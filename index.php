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

// Check if there's an active session (Kommen without Gehen)
$stmt = $conn->prepare("SELECT id, startzeit FROM zeiterfassung WHERE user_id = ? AND endzeit IS NULL ORDER BY startzeit DESC LIMIT 1");
$stmt->execute([$user_id]);
$activeSession = $stmt->fetch(PDO::FETCH_ASSOC);

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
    <style>
        #timer {
            font-size: 1.25rem;
            font-weight: bold;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-base-200 to-base-300 min-h-screen">
    

    <div class="pt-16">
        <div class="container mx-auto px-4 py-8">
            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Main Form Card -->
                <div class="lg:col-span-2 h-full">
                    <div class="card bg-base-100 shadow-xl h-full">
                        <div class="card-body p-4 sm:p-6">
                            <header class="flex items-center justify-between mb-4">
                                <div class="flex items-center space-x-3">
                                    <img src="<?= $kolibri_icon ?>" alt="Quodara Chrono Logo" class="w-10 h-10">
                                    <h1 class="text-xl font-bold text-primary"><?= TITLE ?></h1>
                                </div>
                            </header>

                            <p class="text-lg mb-6">
                                Willkommen zurück! Klicken Sie oben auf "Kommen" zum Starten oder buchen Sie hier spezielle Ereignisse.
                            </p>

                            <form id="mainForm" class="space-y-6">
                                <input type="hidden" id="action" name="action" value="">
                                <input type="datetime-local" id="startzeit" name="startzeit" class="hidden">
                                <input type="datetime-local" id="endzeit" name="endzeit" class="hidden">

                                <div>
                                    <h2 class="text-lg font-semibold mb-3">Ereignistyp</h2>
                                    <div class="grid grid-cols-3 gap-3">
                                        <label class="flex items-center justify-start p-3 border rounded transition-all hover:bg-base-200 cursor-pointer">
                                            <input type="radio" id="urlaub" name="ereignistyp" value="Urlaub" class="radio radio-primary mr-3">
                                            <i class="fas fa-umbrella-beach text-xl mr-2"></i>
                                            <span class="text-sm"><?= EVENT_VACATION ?></span>
                                        </label>
                                        <label class="flex items-center justify-start p-3 border rounded transition-all hover:bg-base-200 cursor-pointer">
                                            <input type="radio" id="feiertag" name="ereignistyp" value="Feiertag" class="radio radio-primary mr-3">
                                            <i class="fas fa-calendar-day text-xl mr-2"></i>
                                            <span class="text-sm"><?= EVENT_HOLIDAY ?></span>
                                        </label>
                                        <label class="flex items-center justify-start p-3 border rounded transition-all hover:bg-base-200 cursor-pointer">
                                            <input type="radio" id="krank" name="ereignistyp" value="Krank" class="radio radio-primary mr-3">
                                            <i class="fas fa-stethoscope text-xl mr-2"></i>
                                            <span class="text-sm"><?= EVENT_SICK ?></span>
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <h2 class="text-lg font-semibold mb-3">Datumsbereich</h2>
                                    <div class="flex space-x-3">
                                        <input type="text" id="dateRange" name="dateRange" class="input input-bordered flex-grow" placeholder="Datumsbereich auswählen" readonly>
                                        <button type="button" id="datenEintragenButton" class="btn btn-primary whitespace-nowrap">
                                            <?= BUTTON_SUBMIT_DATA ?>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>


                <!-- Statistics Card -->
                <div class="h-full">
                    <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow duration-300 h-full">
                        <div class="card-body flex flex-col justify-between">
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
                        <table class="table table-zebra w-full" id="timeRecordsTable">
                            <thead>
                                <tr>
                                    <th class="text-left"><?= TABLE_HEADER_ID ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_WEEK ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_START_TIME ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_END_TIME ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_DURATION ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_BREAK ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_LOCATION ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_COMMENT ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_ACTIONS ?></th>
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
                                            <?php if ($record['endzeit'] !== null): ?>
                                                <input type="datetime-local" class="input input-bordered w-full max-w-xs editable" data-id="<?= $record['id'] ?>" data-field="endzeit" value="<?= date('Y-m-d\TH:i', strtotime($record['endzeit'])) ?>">
                                            <?php else: ?>
                                                <span>-</span>
                                            <?php endif; ?>
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
                                            <button class="btn btn-ghost btn-sm text-black hover:bg-black hover:text-white transition-colors duration-300 deleteRow" data-id="<?= $record['id'] ?>">
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
                                <button onclick="updateTimeRecordsTable(1)" class="btn">«</button>
                                <button onclick="updateTimeRecordsTable(<?= $page - 1 ?>)" class="btn">‹</button>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            for ($i = $start; $i <= $end; $i++) :
                            ?>
                                <button onclick="updateTimeRecordsTable(<?= $i ?>)" class="btn <?= $i === $page ? 'btn-active' : '' ?>"><?= $i ?></button>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages) : ?>
                                <button onclick="updateTimeRecordsTable(<?= $page + 1 ?>)" class="btn">›</button>
                                <button onclick="updateTimeRecordsTable(<?= $totalPages ?>)" class="btn">»</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let startButton = document.getElementById('startButton');
        let endButton = document.getElementById('endButton');
        let mainForm = document.getElementById('mainForm');
        let timerElement = document.getElementById('timer');
        let timerInterval;
        let startTime;

        function formatTime(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const remainingSeconds = seconds % 60;
            return [hours, minutes, remainingSeconds]
                .map(v => v < 10 ? "0" + v : v)
                .join(":");
        }

        function updateButtonVisibility(isActive) {
            if (isActive) {
                startButton.style.display = 'none';
                endButton.style.display = '';
                timerElement.classList.remove('hidden');
            } else {
                startButton.style.display = '';
                endButton.style.display = 'none';
                timerElement.classList.add('hidden');
            }
        }

        function startTimer(initialTime = null) {
            startTime = initialTime ? new Date(initialTime).getTime() : new Date().getTime();
            updateButtonVisibility(true);
            timerInterval = setInterval(() => {
                const currentTime = new Date().getTime();
                const elapsedTime = Math.floor((currentTime - startTime) / 1000);
                timerElement.textContent = formatTime(elapsedTime);
            }, 1000);
        }

        function stopTimer() {
            clearInterval(timerInterval);
            updateButtonVisibility(false);
        }

        function submitForm(action) {
            let now = new Date();
            let localISOTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            let formData = new FormData();
            formData.append('action', action);

            if (action === 'start') {
                formData.append('startzeit', localISOTime);
            } else if (action === 'end') {
                formData.append('endzeit', localISOTime);
            }

            fetch('save.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    console.log(data);
                    updateTimeRecordsTable();
                    if (action === 'start') {
                        startTimer();
                    } else if (action === 'end') {
                        stopTimer();
                    }
                })
                .catch((error) => {
                    console.error('Error:', error);
                    alert('Error submitting form');
                });
        }

        function updateTimeRecordsTable(page = 1) {
            fetch(`get_time_records.php?page=${page}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('timeRecordsTable').innerHTML = data;
                    attachEventListeners();
                })
                .catch((error) => {
                    console.error('Error:', error);
                    alert('Error updating time records table');
                });
        }

        function attachEventListeners() {
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
                                    updateTimeRecordsTable();
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
                                updateTimeRecordsTable();
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
        }

        startButton.addEventListener('click', function(e) {
            e.preventDefault();
            submitForm('start');
        });

        endButton.addEventListener('click', function(e) {
            e.preventDefault();
            submitForm('end');
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
                            updateTimeRecordsTable();
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

        // Initial attachment of event listeners
        attachEventListeners();

        // Initial setup
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($activeSession): ?>
                startTimer('<?= $activeSession['startzeit'] ?>');
            <?php else: ?>
                updateButtonVisibility(false);
            <?php endif; ?>
        });
    </script>

</body>

</html>