<?php
session_start();
include 'config.php';

// Überprüfen, ob der Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? null;
$error = '';
$successMessage = '';
$showSuccessModal = false;

// Sprachdateien laden
$lang = $_SESSION['lang'] ?? 'de';
$langFile = "languages/$lang.php";
if (file_exists($langFile)) {
    require_once $langFile;
} else {
    die("Sprachdatei nicht gefunden!");
}

// Benutzerinformationen laden
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['lang'])) {
        $lang = $_POST['lang'];
        $_SESSION['lang'] = $lang;
        // Lade die neue Sprachdatei sofort
        $langFile = "languages/$lang.php";
        if (file_exists($langFile)) {
            require_once $langFile;
        }
    }
    if (isset($_POST['regelarbeitszeit'])) {
        $regelarbeitszeit = floatval($_POST['regelarbeitszeit']);
        $updateSql = "UPDATE users SET regelarbeitszeit = :regelarbeitszeit WHERE id = :id";
        $stmt = $conn->prepare($updateSql);
        $stmt->execute([':regelarbeitszeit' => $regelarbeitszeit, ':id' => $user_id]);
        $userInfo['regelarbeitszeit'] = $regelarbeitszeit;
    }
    if (isset($_POST['ueberstunden']) && $user_role === 'admin') {
        $ueberstunden = floatval($_POST['ueberstunden']);
        $updateSql = "UPDATE users SET ueberstunden = :ueberstunden WHERE id = :id";
        $stmt = $conn->prepare($updateSql);
        $stmt->execute([':ueberstunden' => $ueberstunden, ':id' => $user_id]);
        $userInfo['ueberstunden'] = $ueberstunden;
    }
    if (isset($_POST['theme_mode'])) {
        $theme_mode = $_POST['theme_mode'];
        $_SESSION['theme_mode'] = $theme_mode;
    }
    $successMessage = 'Einstellungen erfolgreich aktualisiert!';
    $showSuccessModal = true;
}

$theme_mode = $_SESSION['theme_mode'] ?? 'light';

// Check for import messages
if (isset($_SESSION['import_success'])) {
    $successMessage = $_SESSION['import_success'];
    unset($_SESSION['import_success']);
    $showSuccessModal = true;
}

if (isset($_SESSION['import_error'])) {
    $error = $_SESSION['import_error'];
    unset($_SESSION['import_error']);
}

include 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title text-3xl mb-6 flex items-center">
                <img src="<?= $kolibri_icon ?>" alt="Quodara Chrono Logo" class="w-12 h-12 mr-4">
                <?= SETTINGS_TITLE ?>
            </h2>

            <?php if ($error) : ?>
                <div class="alert alert-error shadow-lg mb-4">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span><?= $error ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-control">
                        <label class="label" for="lang">
                            <span class="label-text"><i class="fas fa-language mr-2"></i><?= SETTINGS_LANGUAGE ?></span>
                        </label>
                        <select name="lang" id="lang" class="select select-bordered w-full">
                            <option value="de" <?= $lang == 'de' ? 'selected' : '' ?>>Deutsch</option>
                            <option value="en" <?= $lang == 'en' ? 'selected' : '' ?>>English</option>
                            <option value="zh" <?= $lang == 'zh' ? 'selected' : '' ?>>中文</option>
                        </select>
                    </div>

                    <div class="form-control">
                        <label class="label" for="regelarbeitszeit">
                            <span class="label-text"><i class="fas fa-clock mr-2"></i><?= SETTINGS_REGULAR_WORKING_HOURS ?></span>
                        </label>
                        <input type="number" step="0.1" id="regelarbeitszeit" name="regelarbeitszeit" value="<?= $userInfo['regelarbeitszeit'] ?>" min="0" max="24" class="input input-bordered w-full">
                    </div>

                    <?php if ($user_role === 'admin') : ?>
                        <div class="form-control">
                            <label class="label" for="ueberstunden">
                                <span class="label-text"><i class="fas fa-hourglass mr-2"></i><?= SETTINGS_OVERTIME ?></span>
                            </label>
                            <input type="number" step="0.1" id="ueberstunden" name="ueberstunden" value="<?= $userInfo['ueberstunden'] ?>" min="0" class="input input-bordered w-full">
                        </div>
                    <?php endif; ?>

                    <div class="form-control">
                        <label class="label" for="theme_mode">
                            <span class="label-text"><i class="fas fa-adjust mr-2"></i><?= NAV_DARK_MODE ?></span>
                        </label>
                        <select name="theme_mode" id="theme_mode" class="select select-bordered w-full">
                            <option value="light" <?= $theme_mode == 'light' ? 'selected' : '' ?>>Hell</option>
                            <option value="dark" <?= $theme_mode == 'dark' ? 'selected' : '' ?>>Dunkel</option>
                            <option value="system" <?= $theme_mode == 'system' ? 'selected' : '' ?>>System</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" class="btn btn-primary w-full md:w-auto"><i class="fas fa-save mr-2"></i><?= BUTTON_SAVE_CHANGES ?></button>
                </div>
            </form>

            <?php if ($user_role === 'admin') : ?>
                <div class="divider my-8">Datenbankoperationen</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="card bg-base-200 shadow-md">
                        <div class="card-body">
                            <h3 class="card-title"><i class="fas fa-upload mr-2"></i><?= SETTINGS_IMPORT_DATABASE ?></h3>
                            <form method="post" enctype="multipart/form-data" action="import.php" class="space-y-4">
                                <div class="form-control">
                                    <label class="label" for="dbFile">
                                        <span class="label-text"><?= SETTINGS_IMPORT_DATABASE ?></span>
                                    </label>
                                    <input type="file" id="dbFile" name="dbFile" class="file-input file-input-bordered w-full" accept=".sqlite">
                                </div>
                                <button type="submit" class="btn btn-primary w-full"><i class="fas fa-upload mr-2"></i><?= BUTTON_IMPORT ?></button>
                            </form>
                        </div>
                    </div>
                    <div class="card bg-base-200 shadow-md">
                        <div class="card-body">
                            <h3 class="card-title"><i class="fas fa-download mr-2"></i><?= SETTINGS_DOWNLOAD_DATABASE ?></h3>
                            <p class="mb-4">Laden Sie eine Kopie der aktuellen Datenbank herunter.</p>
                            <a href="download.php" class="btn btn-primary w-full"><i class="fas fa-download mr-2"></i><?= DOWNLOAD_DATABASE ?></a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($successMessage && $showSuccessModal) : ?>
    <div id="successModal" class="modal modal-open">
        <div class="modal-box">
            <h3 class="font-bold text-lg">Erfolg!</h3>
            <p class="py-4"><?= $successMessage ?></p>
            <div class="modal-action">
                <button onclick="closeModal()" class="btn btn-primary">Schließen</button>
            </div>
        </div>
    </div>

    <script>
        function closeModal() {
            document.getElementById('successModal').classList.remove('modal-open');
        }
    </script>
<?php endif; ?>

<script>
    // Funktion zum Aktualisieren des Themes
    function updateTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
    }

    // Theme beim Laden der Seite setzen
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        updateTheme(savedTheme);
    });

    // Theme-Wechsel überwachen
    const themeSelect = document.getElementById('theme_mode');
    if (themeSelect) {
        themeSelect.addEventListener('change', function() {
            updateTheme(this.value);
        });
    }
</script>


