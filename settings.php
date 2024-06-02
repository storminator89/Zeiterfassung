<?php
session_start();
include 'config.php';

// Sprachdateien laden
$lang = $_SESSION['lang'] ?? 'de';
$langFile = "languages/$lang.php";

if (file_exists($langFile)) {
    require_once $langFile;
} else {
    die("Sprachdatei nicht gefunden!");
}

// Überprüfen, ob der Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'] ?? null;
$error = '';
$successMessage = '';
$showSuccessModal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['lang'])) {
        $_SESSION['lang'] = $_POST['lang'];
        header("Location: settings.php");
        exit();
    }

    if ($user_role === 'admin' && isset($_FILES['dbFile']) && $_FILES['dbFile']['error'] == 0) {
        $databasePath = __DIR__ . '/assets/db/timetracking.sqlite';
        if (move_uploaded_file($_FILES["dbFile"]["tmp_name"], $databasePath)) {
            $successMessage = 'Datenbank erfolgreich importiert!';
            $showSuccessModal = true;
        } else {
            $error = 'Fehler beim Verschieben der hochgeladenen Datei!';
        }
    } else if (isset($_FILES['dbFile'])) {
        $error = 'Keine Datei hochgeladen oder Fehler beim Hochladen!';
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <!-- Meta tags and title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SETTINGS_TITLE ?></title>
    <!-- Favicon and external stylesheets -->
    <link rel="icon" href="assets/kolibri_icon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="./assets/css/main.css">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom pl-3">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <img src="assets/kolibri_icon_weiß.png" alt="Time Tracking" height="50">
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
                                <li><a class="dropdown-item" href="admin.php"><i class="fas fa-user-shield mr-1"></i> <?= NAV_ADMIN ?></a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#aboutModal"><i class="fas fa-info-circle mr-1"></i> <?= NAV_ABOUT ?></a></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt mr-1"></i> <?= NAV_LOGOUT ?></a></li>
                            <li><button class="dropdown-item" onclick="toggleDarkMode()"><i class="fas fa-moon mr-1"></i> <?= NAV_DARK_MODE ?></button></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <div class="container mt-5 p-5">
        <h2 class="fancy-title">
            <img src="assets/kolibri_icon.png" alt="Quodara Chrono Logo" style="width: 80px; height: 80px; margin-right: 10px;">
            <?= SETTINGS_TITLE ?>
        </h2>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($successMessage && $showSuccessModal): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    successModal.show();
                });
            </script>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-12">
                <!-- Form to change language -->
                <form method="post" class="mb-4">
                    <div class="mb-3 input-group">
                        <span class="input-group-text"><i class="fas fa-language"></i></span>
                        <select name="lang" class="form-control" id="lang">
                            <option value="de" <?= $lang == 'de' ? 'selected' : '' ?>>Deutsch</option>
                            <option value="en" <?= $lang == 'en' ? 'selected' : '' ?>>English</option>
                            <option value="zh" <?= $lang == 'zh' ? 'selected' : '' ?>>中文</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> <?= BUTTON_SUBMIT_DATA ?></button>
                </form>

                <!-- Form to import database, visible only to admins -->
                <?php if ($user_role === 'admin'): ?>
                <form method="post" enctype="multipart/form-data" action="settings.php" class="mb-4">
                    <div class="mb-3 input-group">
                        <span class="input-group-text"><i class="fas fa-database"></i></span>
                        <input type="file" class="form-control" id="dbFile" name="dbFile">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload mr-1"></i> <?= BUTTON_IMPORT ?></button>
                </form>
                <?php endif; ?>

                <!-- Download database backup button -->
                <a href="download.php" class="btn btn-secondary"><i class="fas fa-download mr-1"></i> <?= DOWNLOAD_DATABASE ?></a>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="successModalLabel">Erfolg</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= $successMessage ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer mt-auto py-3">
        <div class="container">
            <span class="text-muted"><?= FOOTER_TEXT ?></span>
        </div>
    </footer>

    <!-- Bootstrap Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
