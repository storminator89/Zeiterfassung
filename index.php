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

// Fetch the most recent time record
$stmt = $conn->prepare("SELECT * FROM zeiterfassung WHERE user_id = ? ORDER BY startzeit DESC LIMIT 1");
$stmt->execute([$user_id]);
$latestRecord = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" data-theme="auto">

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
    <!-- SweetAlert2 für schönere Alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        #timer,
        #mobileTimer {
            font-size: 1.25rem;
            font-weight: bold;
        }

        @media (max-width: 640px) {
            .event-type-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-base-200 to-base-300">
    <div class="pt-16 pl-0 md:pl-4">
        <div class="container mx-auto px-4 py-8">
            <div class="grid md:grid-cols-3 gap-6">
                <!-- Main Form Card -->
                <div class="md:col-span-2">
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body p-4">
                            <header class="flex items-center justify-between mb-4">
                                <div class="flex items-center space-x-3">
                                    <img src="<?= $kolibri_icon ?>" alt="Quodara Chrono Logo" class="w-10 h-10">
                                    <h1 class="text-xl font-bold text-primary"><?= TITLE ?></h1>
                                </div>
                            </header>

                            <!-- Mobile Timer and Buttons -->
                            <div class="lg:hidden mb-4">
                                <div id="mobileTimer" class="text-center text-2xl font-bold mb-2 <?php echo $activeSession ? '' : 'hidden'; ?>">00:00:00</div>
                                <div class="flex justify-center space-x-4">
                                    <button id="mobileStartButton" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt mr-2"></i>Kommen
                                    </button>
                                    <button id="mobileEndButton" class="btn btn-secondary" style="display: none;">
                                        <i class="fas fa-sign-out-alt mr-2"></i>Gehen
                                    </button>
                                </div>
                            </div>

                            <p class="text-lg mb-6">
                                Willkommen zurück! Klicken Sie auf "Kommen" zum Starten oder buchen Sie hier spezielle Ereignisse.
                            </p>

                            <form id="mainForm" method="POST" action="save.php" class="space-y-6">
                                <input type="hidden" id="action" name="action" value="">
                                <input type="datetime-local" id="startzeit" name="startzeit" class="hidden">
                                <input type="datetime-local" id="endzeit" name="endzeit" class="hidden">

                                <div>
                                    <h2 class="text-lg font-semibold mb-3">Ereignistyp</h2>
                                    <div class="grid event-type-grid gap-3">
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

                                <div class="event-selection-fields">
                                    <!-- Event selection elements -->
                                    <label for="standort">Standort:</label>
                                    <select name="standort" id="standort" required data-id="<?= htmlspecialchars($record['id']) ?>">
                                        <option value="">Wählen Sie einen Standort</option>
                                        <option value="Home">Home</option>
                                        <option value="Büro">Büro</option>
                                        <!-- Weitere Optionen -->
                                    </select>

                                    <label for="beschreibung">Kommentar:</label>
                                    <textarea name="beschreibung" id="beschreibung" rows="3" placeholder="Geben Sie einen Kommentar ein..." data-id="<?= htmlspecialchars($record['id']) ?>"></textarea>

                                    <button type="submit">Speichern</button>
                                </div>

                                <div>
                                    <h2 class="text-lg font-semibold mb-3">Datumsbereich</h2>
                                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                                        <input type="text" id="dateRange" name="dateRange" class="input input-bordered flex-grow" placeholder="Datumsbereich auswählen" readonly>
                                        <button type="button" id="datenEintragenButton" class="btn btn-primary whitespace-nowrap">
                                            <?= BUTTON_SUBMIT_DATA ?>
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <script>
                                // Add this script block or add to your existing scripts
                                document.addEventListener('DOMContentLoaded', function() {
                                    document.querySelector('#datenEintragenButton').addEventListener('click', function() {
                                        var selectedEreignistyp = document.querySelector('input[name="ereignistyp"]:checked');
                                        if (selectedEreignistyp) {
                                            document.querySelector('textarea[name="beschreibung"]').value = selectedEreignistyp.value;
                                        }
                                    });
                                });
                            </script>

                        </div>
                    </div>
                </div>

                <!-- Statistics Card -->
                <div>
                    <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow duration-300">
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

            <!-- Time Records Container -->
            <div id="timeRecordsContainer" class="mt-8">
                <!-- Content will be loaded here via AJAX -->
            </div>

            <!-- Latest Time Record (visible only on mobile) -->
            <div id="latestTimeRecord" class="card bg-base-100 shadow-xl mt-8 md:hidden">
                <div class="card-body">
                    <h3 class="card-title text-2xl mb-4"><i class="fas fa-clock mr-2"></i><?= LATEST_TIME_RECORD ?></h3>
                    <div class="bg-base-200 p-6 rounded-lg shadow-inner">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2 bg-primary text-primary-content p-4 rounded-lg mb-4">
                                <p class="text-lg font-semibold"><?= TABLE_HEADER_DURATION ?>:</p>
                                <p class="text-3xl font-bold"><?= calculateDuration($latestRecord['startzeit'], $latestRecord['endzeit'], $latestRecord['pause']) ?></p>
                            </div>
                            <div>
                                <p class="font-semibold"><?= TABLE_HEADER_START_TIME ?>:</p>
                                <p><?= date('d.m.Y H:i', strtotime($latestRecord['startzeit'])) ?></p>
                            </div>
                            <div>
                                <p class="font-semibold"><?= TABLE_HEADER_END_TIME ?>:</p>
                                <p><?= $latestRecord['endzeit'] ? date('d.m.Y H:i', strtotime($latestRecord['endzeit'])) : '-' ?></p>
                            </div>
                            <div>
                                <p class="font-semibold"><?= TABLE_HEADER_BREAK ?>:</p>
                                <p><?= $latestRecord['pause'] ?> <?= LABEL_MINUTES ?></p>
                            </div>
                            <div>
                                <p class="font-semibold"><?= TABLE_HEADER_LOCATION ?>:</p>
                                <p>
                                    <?php
                                    switch ($latestRecord['standort']) {
                                        case LOCATION_OFFICE_VALUE:
                                            echo LOCATION_OFFICE;
                                            break;
                                        case LOCATION_HOME_OFFICE_VALUE:
                                            echo LOCATION_HOME_OFFICE;
                                            break;
                                        case LOCATION_BUSINESS_TRIP_VALUE:
                                            echo LOCATION_BUSINESS_TRIP;
                                            break;
                                        default:
                                            echo 'Unbekannt';
                                    }
                                    ?>
                                </p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold"><?= TABLE_HEADER_COMMENT ?>:</p>
                                <p><?= htmlspecialchars($latestRecord['beschreibung']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="settingsDropdown" style="display: none;">
        <!-- Einstellungen-Inhalte hier -->
    </div>

    <script>
        let startButton = document.getElementById('startButton');
        let endButton = document.getElementById('endButton');
        let mobileStartButton = document.getElementById('mobileStartButton');
        let mobileEndButton = document.getElementById('mobileEndButton');
        let mainForm = document.getElementById('mainForm');
        let timerElement = document.getElementById('timer');
        let mobileTimerElement = document.getElementById('mobileTimer');
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
                mobileStartButton.style.display = 'none';
                mobileEndButton.style.display = '';
                timerElement.classList.remove('hidden');
                mobileTimerElement.classList.remove('hidden');
            } else {
                startButton.style.display = '';
                endButton.style.display = 'none';
                mobileStartButton.style.display = '';
                mobileEndButton.style.display = 'none';
                timerElement.classList.add('hidden');
                mobileTimerElement.classList.add('hidden');
            }
        }

        function startTimer(initialTime = null) {
            startTime = initialTime ? new Date(initialTime).getTime() : new Date().getTime();
            updateButtonVisibility(true);
            timerInterval = setInterval(() => {
                const currentTime = new Date().getTime();
                const elapsedTime = Math.floor((currentTime - startTime) / 1000);
                const formattedTime = formatTime(elapsedTime);
                timerElement.textContent = formattedTime;
                mobileTimerElement.textContent = formattedTime;
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
                formData.append('standort', document.getElementById('standort').value || 'office');
                formData.append('beschreibung', document.getElementById('beschreibung').value || '');
            } else if (action === 'end') {
                formData.append('endzeit', localISOTime);
            }

            fetch('save.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(data => {
                let jsonResponse;
                try {
                    jsonResponse = JSON.parse(data);
                    if (jsonResponse.success) {
                        if (action === 'start') {
                            startTimer();
                        } else if (action === 'end') {
                            stopTimer();
                        }
                        
                        // Warte bis die Updates abgeschlossen sind
                        return Promise.all([
                            updateTimeRecordsTable(),
                            updateLatestTimeRecord()
                        ]).then(() => {
                            Swal.fire({
                                icon: 'success',
                                title: jsonResponse.message,
                                showConfirmButton: false,
                                timer: 1500
                            });
                        });
                    } else {
                        throw new Error(jsonResponse.message || 'Ein Fehler ist aufgetreten');
                    }
                } catch (e) {
                    throw new Error(data || 'Ein Fehler ist aufgetreten');
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Fehler',
                    text: error.message,
                    confirmButtonText: 'OK'
                });
            });
        }

        function updateTimeRecordsView() {
            var timeRecordsTable = document.getElementById('timeRecordsTable');
            var latestTimeRecord = document.getElementById('latestTimeRecord');

            if (window.innerWidth >= 768) { // 768px is typically the breakpoint for tablet view
                if (timeRecordsTable) timeRecordsTable.style.display = 'block';
                if (latestTimeRecord) latestTimeRecord.style.display = 'none';
            } else {
                if (timeRecordsTable) timeRecordsTable.style.display = 'none';
                if (latestTimeRecord) latestTimeRecord.style.display = 'block';
            }
        }

        function updateTimeRecordsTable(page = 1) {
            return fetch(`get_time_records.php?page=${page}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    const container = document.getElementById('timeRecordsContainer');
                    if (container) {
                        container.innerHTML = data;
                        attachEventListeners();
                        updateTimeRecordsView();
                    }
                })
                .catch((error) => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Fehler',
                        text: 'Fehler beim Aktualisieren der Zeiteinträge',
                        confirmButtonText: 'OK'
                    });
                });
        }

        function updateLatestTimeRecord() {
            return fetch('get_latest_record.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    const container = document.getElementById('latestTimeRecord');
                    if (container) {
                        container.innerHTML = data;
                    }
                })
                .catch((error) => {
                    console.error('Error:', error);
                    // Hier keine Fehlermeldung anzeigen, da dies ein optionales Feature ist
                });
        }

        function attachEventListeners() {
            // Delete row functionality
            document.querySelectorAll('.deleteRow').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    Swal.fire({
                        title: 'Sind Sie sicher?',
                        text: "Dieser Eintrag wird unwiderruflich gelöscht!",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Ja, löschen!',
                        cancelButtonText: 'Abbrechen'
                    }).then((result) => {
                        if (result.isConfirmed) {
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
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Gelöscht!',
                                        text: 'Der Eintrag wurde erfolgreich gelöscht.',
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                    updateTimeRecordsTable();
                                    updateLatestTimeRecord();
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Fehler',
                                        text: data
                                    });
                                }
                            })
                            .catch((error) => {
                                console.error('Error:', error);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Fehler',
                                    text: 'Fehler beim Löschen des Eintrags'
                                });
                            });
                        }
                    });
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
                                updateLatestTimeRecord();
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

        mobileStartButton.addEventListener('click', function(e) {
            e.preventDefault();
            submitForm('start');
        });

        mobileEndButton.addEventListener('click', function(e) {
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
                            // Änderung hier: ereignistyp zu beschreibung
                            body: `urlaubStart=${start}&urlaubEnde=${end}&beschreibung=${ereignistyp}`,
                        })
                        .then(response => response.text())
                        .then(data => {
                            alert(data);
                            updateTimeRecordsTable();
                            updateLatestTimeRecord();
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

        // Initial setup
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($activeSession): ?>
                startTimer('<?= $activeSession['startzeit'] ?>');
            <?php else: ?>
                updateButtonVisibility(false);
            <?php endif; ?>

            updateTimeRecordsTable();
            updateTimeRecordsView();

            // Run on window resize
            window.addEventListener('resize', updateTimeRecordsView);
        });
    </script>
</body>

</html>