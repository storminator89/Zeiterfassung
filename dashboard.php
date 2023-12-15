<?php
include 'functions.php';
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <!-- Metadata -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quodara Chrono - Dashboard</title>
    <link rel="icon" href="assets\kolibri_icon.png" type="image/png">

    <!-- Toast UI Components -->
    <script src="https://uicdn.toast.com/tui.code-snippet/latest/tui-code-snippet.js"></script>
    <script src="https://uicdn.toast.com/tui.dom/v3.0.0/tui-dom.js"></script>
    <script src="https://uicdn.toast.com/tui.time-picker/latest/tui-time-picker.min.js"></script>
    <script src="https://uicdn.toast.com/tui.date-picker/latest/tui-date-picker.min.js"></script>
    <script src="https://uicdn.toast.com/tui-calendar/latest/tui-calendar.js"></script>
    <link rel="stylesheet" href="https://uicdn.toast.com/tui-calendar/latest/tui-calendar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- Bootstrap CSS & JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-annotation/3.0.1/chartjs-plugin-annotation.min.js"></script>

    <!-- DataTable Buttons -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.3.0/css/buttons.dataTables.min.css">
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.3.0/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.3.0/js/buttons.html5.min.js"></script>

    <!-- Custom JavaScript & CSS -->
    <script src="./assets/js/main.js"></script>
    <link rel="stylesheet" type="text/css" href="./assets/css/main.css">

    <!-- Page Specific Variables -->
    <script>
        var totalHoursThisMonthFromRecords = <?= $totalHoursThisMonthFromRecords ?>;
        var workingDaysThisMonth = "<?= $workingDaysThisMonth ?>";
        var workingHoursThisMonth = <?= $workingHoursThisMonth ?>;
        var totalHoursThisWeek = <?= $totalHoursThisWeek ?>;
        var days = <?= json_encode($days) ?>;
        var hours = <?= json_encode($hours) ?>;
        var allEvents = <?= json_encode($events) ?>;
    </script>
</head>

<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <!-- Brand -->
        <a class="navbar-brand" href="index.php">
            <img src="assets\kolibri_icon_weiß.png" alt="Arbeitszeiterfassung" height="50">
        </a>

        <!-- Toggle Button -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Collapsible Content -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Menu Items -->
            <ul class="navbar-nav ml-auto">
                <li class="nav-item active">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container mt-5">
        <!-- Title -->
        <h2 class="fancy-title">
            <img src="assets/kolibri_icon.png" alt="Logo" style="width: 70px; height: 70px; margin-right: 10px;">
            Quodara Chrono - Zeiterfassung
        </h2>

        <!-- Calendar Section -->
        <div class="row">
            <div class="calendar-navigation">
                <!-- Previous Month Button -->
                <button id="prevMonthBtn" class="btn btn-light"><i class="fas fa-arrow-left"></i></button>

                <!-- Today Button -->
                <button id="todayBtn" class="btn btn-light"><i class="fas fa-calendar-day"></i> Heute</button>

                <!-- Next Month Button -->
                <button id="nextMonthBtn" class="btn btn-light"><i class="fas fa-arrow-right"></i></button>
            </div>

            <!-- Calendar View -->
            <div class="col-md-12" id="calendar"></div>
        </div>

        <!-- Charts Section -->
        <div class="row mt-3">
            <div class="col-md-6">
                <!-- Weekly Hours Chart -->
                <canvas id="weeklyHoursChart" width="200" height="200"></canvas>
            </div>
            <div class="col-md-6">
                <!-- Daily Hours Chart -->
                <canvas id="dailyHoursChart" width="200" height="200"></canvas>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-6">
                <!-- Monthly Hours Chart -->
                <canvas id="monthlyHoursChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1" role="dialog" aria-labelledby="scheduleModalLabel" aria-hidden="true">
        <!-- Modal Dialog -->
        <div class="modal-dialog" role="document">
            <!-- Modal Content -->
            <div class="modal-content">
                <!-- Header -->
                <div class="modal-header">
                    <h5 class="modal-title" id="scheduleModalLabel">Arbeitszeiten</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <!-- Body -->
                <div class="modal-body">
                    <!-- Start Time -->
                    <strong>Start:</strong> <span id="startTime"></span>


                    <!-- End Time -->
                    <strong>Ende:</strong> <span id="endTime"></span>
                </div>

                <!-- Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>