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
if ($user_role === 'admin' || $user_role === 'supervisor') {
    $stmt = $conn->prepare("SELECT z.*, u.username, u.id as user_id, u.regelarbeitszeit, u.ueberstunden as vorherige_ueberstunden, strftime('%Y-%m-%d', z.startzeit) AS day, strftime('%W', z.startzeit) AS weekNumber 
                            FROM zeiterfassung z
                            JOIN users u ON z.user_id = u.id
                            WHERE u.supervisor_id = ?
                            ORDER BY z.startzeit DESC");
    $stmt->execute([$user_id]);
    $zeiten = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

        if (!isset($workHoursByUser[$userId]['days'][$day])) {
            $workHoursByUser[$userId]['days'][$day] = 0;
            $workHoursByUser[$userId]['total_days']++;
        }

        $start = new DateTime($zeit['startzeit']);
        $end = new DateTime($zeit['endzeit']);
        $interval = $start->diff($end);
        $pauseMinuten = intval($zeit['pause']) ?: 0;

        $gesamtMinuten = ($interval->h * 60 + $interval->i) - $pauseMinuten;
        $workHoursByUser[$userId]['days'][$day] += $gesamtMinuten;
        $workHoursByUser[$userId]['total_hours'] += $gesamtMinuten / 60;
    }

    // Berechnung der Gesamtüberstunden pro Benutzer und Vorbereitung der detaillierten Daten
    foreach ($workHoursByUser as $userId => $data) {
        $totalOverMinutes = 0;
        $regelarbeitszeit = $data['regelarbeitszeit'];
        foreach ($data['days'] as $day => $totalMinutes) {
            $regularWorkingMinutesPerDay = $regelarbeitszeit * 60;

            $overMinutes = $totalMinutes - $regularWorkingMinutesPerDay;
            $totalOverMinutes += $overMinutes;
        }

        $totalOverMinutes += ($data['vorherige_ueberstunden'] * 60);

        $isNegative = $totalOverMinutes < 0;
        $totalOverHours = floor(abs($totalOverMinutes) / 60);
        $totalOverMinutes = abs($totalOverMinutes % 60);

        $ueberstundenListe[$userId] = [
            'username' => $data['username'],
            'ueberstunden' => ($isNegative ? '-' : '') . sprintf("%02d:%02d", $totalOverHours, $totalOverMinutes)
        ];

        $detaillierteDaten[$userId] = [
            'username' => $data['username'],
            'regelarbeitszeit' => $data['regelarbeitszeit'],
            'total_hours' => round($data['total_hours'], 2),
            'total_days' => $data['total_days'],
            'avg_hours_per_day' => round($data['total_hours'] / $data['total_days'], 2),
            'ueberstunden' => ($isNegative ? '-' : '') . sprintf("%02d:%02d", $totalOverHours, $totalOverMinutes)
        ];
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
    <style>
        .modal-box {
            width: 90vw;
            max-width: 70vw;
            height: 70vh;
            max-height: 90vh;          
        }

        th {
            text-align: left !important;
        }
    </style>
</head>

<body class="bg-base-200">
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
            <div class="mb-12">
                <h3 class="text-2xl font-semibold mb-6"><i class="fas fa-hourglass mr-2"></i><?= TOTAL_OVERTIME_TITLE ?></h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php if (is_array($ueberstundenListe) && !empty($ueberstundenListe)) : ?>
                        <?php foreach ($ueberstundenListe as $userId => $data) : ?>
                            <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow duration-300 fade-in">
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
                        <div class="col-span-full text-center text-lg text-gray-500">
                            <?= NO_ENTRIES_FOUND ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-8">
                <h3 class="text-2xl font-semibold mb-6"><i class="fas fa-clock mr-2"></i><?= ACTUAL_WORKED_TIMES ?></h3>
                <div class="overflow-x-auto bg-base-100 rounded-lg shadow">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th><?= TABLE_HEADER_USERNAME ?></th>
                                <th><?= TABLE_HEADER_WEEK ?></th>
                                <th><?= TABLE_HEADER_START_TIME ?></th>
                                <th><?= TABLE_HEADER_END_TIME ?></th>
                                <th><?= TABLE_HEADER_DURATION ?></th>
                                <th><?= TABLE_HEADER_BREAK ?></th>
                                <th><?= TABLE_HEADER_LOCATION ?></th>
                                <th><?= TABLE_HEADER_OVERTIME ?></th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <!-- Tabellenzeilen werden hier dynamisch eingefügt -->
                        </tbody>
                    </table>
                </div>
                <!-- Paginierung -->
                <div class="flex justify-center space-x-1 mt-4" id="pagination">
                    <!-- Paginierungsbuttons werden hier dynamisch eingefügt -->
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal für Details -->
    <dialog id="details_modal" class="modal">
        <div class="modal-box w-11/12 max-w-7xl bg-base-200">
            <h3 class="font-bold text-2xl mb-6" id="modal_title">Detaillierte Übersicht</h3>
            <div id="modal_content" class="space-y-6"></div>
            <div class="modal-action mt-8">
                <form method="dialog">
                    <button class="btn btn-primary">Schließen</button>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>Schließen</button>
        </form>
    </dialog>

    <script>
        const detaillierteDaten = <?= json_encode($detaillierteDaten) ?>;
        const itemsPerPage = 10; // Anzahl der Einträge pro Seite
        let currentPage = 1;
        const zeiten = <?= json_encode($zeiten) ?>; // PHP-Array in JavaScript-Variable umwandeln

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
                    <div class="stat-value">${userData.regelarbeitszeit}h</div>
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

        function formatTime(minutes) {
            const hours = Math.floor(minutes / 60);
            const mins = Math.round(minutes % 60);
            return `${hours}h ${mins}m`;
        }

        function displayTable(page) {
            const tableBody = document.getElementById('tableBody');
            const startIndex = (page - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const pageItems = zeiten.slice(startIndex, endIndex);

            tableBody.innerHTML = '';

            pageItems.forEach(zeit => {
                const start = new Date(zeit.startzeit);
                const end = new Date(zeit.endzeit);
                const interval = (end - start) / (1000 * 60); // Differenz in Minuten
                const pauseMinuten = parseInt(zeit.pause) || 0;

                const gesamtMinuten = interval - pauseMinuten;
                const dauer = formatTime(gesamtMinuten);

                const regelarbeitszeit = zeit.regelarbeitszeit || 8.0;
                const regularWorkingMinutesPerDay = regelarbeitszeit * 60;
                const ueberstunden = gesamtMinuten - regularWorkingMinutesPerDay;
                const ueberstundenFormat = formatTime(Math.abs(ueberstunden));

                const row = `
                <tr class="hover:bg-base-200 transition-colors duration-200">
                    <td>${zeit.username}</td>
                    <td>${zeit.weekNumber}</td>
                    <td>${start.toLocaleString()}</td>
                    <td>${end.toLocaleString()}</td>
                    <td>${dauer}</td>
                    <td>${zeit.pause} min</td>
                    <td>${zeit.standort}</td>
                    <td class="${ueberstunden >= 0 ? 'text-success' : 'text-error'} font-semibold">${ueberstunden >= 0 ? '+' : '-'}${ueberstundenFormat}</td>
                </tr>
            `;
                tableBody.innerHTML += row;
            });

            updatePagination();
        }

        function updatePagination() {
            const paginationContainer = document.getElementById('pagination');
            const totalPages = Math.ceil(zeiten.length / itemsPerPage);

            let paginationHTML = `
            <button class="btn btn-sm" onclick="changePage(1)" ${currentPage === 1 ? 'disabled' : ''}>«</button>
            <button class="btn btn-sm" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>‹</button>
        `;

            const maxVisiblePages = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

            if (endPage - startPage + 1 < maxVisiblePages) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }

            for (let i = startPage; i <= endPage; i++) {
                paginationHTML += `
                <button class="btn btn-sm ${i === currentPage ? 'btn-active' : ''}" onclick="changePage(${i})">${i}</button>
            `;
            }

            paginationHTML += `
            <button class="btn btn-sm" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>›</button>
            <button class="btn btn-sm" onclick="changePage(${totalPages})" ${currentPage === totalPages ? 'disabled' : ''}>»</button>
        `;

            paginationContainer.innerHTML = paginationHTML;
        }

        function changePage(page) {
            currentPage = page;
            displayTable(currentPage);
        }

        // Initial display
        displayTable(currentPage);
    </script>
</body>

</html>