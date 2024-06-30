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

// Fetch theme mode from session
$theme_mode = $_SESSION['theme_mode'] ?? 'system'; // Default to system preference
$dark_mode_class = '';
$kolibri_icon = 'assets/kolibri_icon.png'; // Standard icon

if ($theme_mode === 'dark') {
    $dark_mode_class = 'dark-mode';
    $kolibri_icon = 'assets/kolibri_icon_weiß.png';
} elseif ($theme_mode === 'light') {
    $dark_mode_class = 'light-mode';
    $kolibri_icon = 'assets/kolibri_icon.png';
} elseif ($theme_mode === 'system') {
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        if (preg_match('/(prefers-color-scheme: dark)/i', $_SERVER['HTTP_USER_AGENT'])) {
            $dark_mode_class = 'dark-mode';
            $kolibri_icon = 'assets/kolibri_icon_weiß.png';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" class="<?= $dark_mode_class ?>">

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
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600&display=swap" rel="stylesheet">
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
        var BUTTON_PAUSE_START = "<?= BUTTON_PAUSE_START ?>";
        var BUTTON_PAUSE_RESUME = "<?= BUTTON_PAUSE_RESUME ?>";
        var BUTTON_PAUSE_END = "<?= BUTTON_PAUSE_END ?>";
        var currentLang = "<?= $lang ?>";
    </script>
    <script src="./assets/js/main.js"></script>
</head>

<body class="<?= $dark_mode_class ?>">
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom pl-3">
        <a class="navbar-brand" href="index.php">
            <img class="pl-3" src="<?= $kolibri_icon ?>" alt="Time Tracking" height="50">
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
</body>

</html>