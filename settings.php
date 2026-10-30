<?php
require_once __DIR__ . '/security.php';
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

// Resolve the existing explicit choices before defining any locale constants.
// The unchanged security/CSRF bootstrap and signed-in guard have already run.
$passwordFormRequested = $_SERVER['REQUEST_METHOD'] === 'POST'
    && array_intersect(array_keys($_POST), ['password_action', 'current_password', 'password', 'password_confirmation']) !== [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$passwordFormRequested && isset($_POST['lang'])) {
    $_SESSION['lang'] = valid_language($_POST['lang']);
}
// Resolve header.php's existing GET priority before defining any dictionary,
// including a password form that may redisplay after a known refusal.
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = valid_language($_GET['lang']);
}

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
$stmt->closeCursor();

require_once __DIR__ . '/lib/native_own_password.php';
$passwordError = '';
if ($passwordFormRequested) {
    header('Cache-Control: no-store');
    $passwordChanged = false;
    $passwordLogoutRequired = false;
    $passwordStatus = 503;
    $passwordFailure = SETTINGS_OWN_PASSWORD_UNCONFIRMED;
    try {
        native_own_password_change($conn, (int)$user_id, (string)$user_role,
            (string)($_SESSION['auth_version'] ?? ''), $_POST, (string)($_SERVER['REMOTE_ADDR'] ?? 'local'));
        $passwordChanged = true;
    } catch (NativeOwnPasswordError $passwordException) {
        $passwordStatus = $passwordException->status;
        $passwordError = match ($passwordException->publicCode) {
            'invalid_form' => SETTINGS_OWN_PASSWORD_INVALID_FORM,
            'invalid_current' => SETTINGS_OWN_PASSWORD_INVALID_CURRENT,
            'rate_limited' => SETTINGS_OWN_PASSWORD_RATE_LIMITED,
            'not_allowed' => SETTINGS_OWN_PASSWORD_UNAVAILABLE,
            'unsupported_profile' => SETTINGS_OWN_PASSWORD_PROFILE_UNAVAILABLE,
            default => SETTINGS_OWN_PASSWORD_STALE,
        };
        $passwordLogoutRequired = $passwordStatus === 401 || $passwordStatus === 503;
        $passwordFailure = $passwordError;
        http_response_code($passwordStatus);
    } catch (Throwable $passwordException) {
        // Outcome may be unknown: no automatic retry, success modal or old identity continuation.
        $passwordLogoutRequired = true;
    } finally {
        unset($_POST['current_password'], $_POST['password'], $_POST['password_confirmation']);
    }
    if ($passwordChanged || $passwordLogoutRequired) {
        $passwordLoggedOut = false;
        try {
            $_SESSION = [];
            $params = session_get_cookie_params();
            $cookieCleared = setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'], 'domain' => $params['domain'],
                'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
            $sessionDestroyed = session_status() === PHP_SESSION_ACTIVE && session_destroy();
            $passwordLoggedOut = $cookieCleared && $sessionDestroyed;
        } catch (Throwable) {}
        if ($passwordChanged) {
            if (!$passwordLoggedOut) { abort_request(503, SETTINGS_OWN_PASSWORD_LOGOUT_UNCONFIRMED); }
            header('Location: login.php', true, 303);
            exit;
        }
        abort_request($passwordStatus, $passwordFailure);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$passwordFormRequested) {
    if (isset($_POST['ueberstunden']) && $user_role === 'admin') {
        require_once __DIR__ . '/lib/settings_carry_in.php';
        $ueberstunden = floatval($_POST['ueberstunden']);
        $stmt = settings_carry_in_prepare($conn);
        $stmt->execute([':ueberstunden' => $ueberstunden, ':id' => $user_id]);
        $userInfo['ueberstunden'] = $ueberstunden;
    }
    if (isset($_POST['theme_mode'])) {
        $theme_mode = in_array($_POST['theme_mode'], ['light', 'dark', 'system'], true) ? $_POST['theme_mode'] : 'light';
        $_SESSION['theme_mode'] = $theme_mode;
    }
    $successMessage = SETTINGS_SAVE_SUCCESS_MESSAGE;
    $showSuccessModal = true;
}

$theme_mode = $_SESSION['theme_mode'] ?? 'light';

// Check for import messages
if (!$passwordFormRequested && isset($_SESSION['import_success'])) {
    $successMessage = $_SESSION['import_success'];
    unset($_SESSION['import_success']);
    $showSuccessModal = true;
}

if (!$passwordFormRequested && isset($_SESSION['import_error'])) {
    $error = $_SESSION['import_error'];
    unset($_SESSION['import_error']);
}

// Own display only, after all existing form handling. Reuse the definitions-only
// shared snapshot and DTO/labels without a Laravel bootstrap or a new selector.
require_once __DIR__ . '/lib/own_settings_profile.php';
require_once __DIR__ . '/framework/app/View/ExistingUi/OwnSettingsProfile.php';
$ownSettingsProfileTransaction = false;
try {
    if ($conn->inTransaction()) {
        throw new LogicException('Own settings profile requires an idle connection.');
    }
    if (!$conn->beginTransaction()) {
        throw new RuntimeException('Own settings profile unavailable.');
    }
    $ownSettingsProfileTransaction = true;
    $ownSettingsProfile = new \App\View\ExistingUi\OwnSettingsProfile(
        own_settings_profile_snapshot($conn, (int)$user_id), $lang);
    if (!$conn->commit()) {
        throw new RuntimeException('Own settings profile unavailable.');
    }
    $ownSettingsProfileTransaction = false;
} catch (Throwable) {
    if ($ownSettingsProfileTransaction && $conn->inTransaction()) {
        try { $conn->rollBack(); } catch (Throwable) {}
    }
    $ownSettingsProfile = new \App\View\ExistingUi\OwnSettingsProfile(null, $lang);
}

require_once __DIR__ . '/lib/native_own_password_display.php';
$ownPasswordDisplay = native_own_password_display($conn, $userInfo);

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
                        <span><?= h($error) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-6">
<?= csrf_field() ?>
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
                        <p>Das aktuelle Soll mit Gültigkeitsdatum steht unter <a href="work_models.php">Arbeitszeitmodell</a>.</p><input type="number" step="0.1" id="regelarbeitszeit" disabled name="regelarbeitszeit" value="<?= $userInfo['regelarbeitszeit'] ?>" min="0" max="24" class="input input-bordered w-full">
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
                            <option value="light" <?= $theme_mode == 'light' ? 'selected' : '' ?>><?= h(SETTINGS_THEME_LIGHT) ?></option>
                            <option value="dark" <?= $theme_mode == 'dark' ? 'selected' : '' ?>><?= h(SETTINGS_THEME_DARK) ?></option>
                            <option value="system" <?= $theme_mode == 'system' ? 'selected' : '' ?>><?= h(SETTINGS_THEME_SYSTEM) ?></option>
                        </select>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" class="btn btn-primary w-full md:w-auto"><i class="fas fa-save mr-2"></i><?= BUTTON_SAVE_CHANGES ?></button>
                </div>
            </form>

            <?php require __DIR__ . '/lib/native_own_settings_profile_view.php'; ?>

            <div class="divider my-8"><?= h(SETTINGS_OWN_PASSWORD_TITLE) ?></div>
            <?php if ($passwordError !== '') : ?>
                <div class="alert alert-error mb-4" role="alert"><?= h($passwordError) ?></div>
            <?php endif; ?>
            <?php if ($ownPasswordDisplay === 'available') : ?>
                <p class="text-sm opacity-70 mb-4"><?= h(SETTINGS_OWN_PASSWORD_HELP) ?></p>
                <form method="post" class="space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="password_action" value="change">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-control"><label class="label" for="current_password"><?= h(SETTINGS_OWN_PASSWORD_CURRENT) ?></label><input class="input input-bordered w-full" type="password" id="current_password" name="current_password" autocomplete="current-password" maxlength="4096" required></div>
                        <div class="form-control"><label class="label" for="new_password"><?= h(SETTINGS_OWN_PASSWORD_NEW) ?></label><input class="input input-bordered w-full" type="password" id="new_password" name="password" autocomplete="new-password" maxlength="72" required></div>
                        <div class="form-control"><label class="label" for="password_confirmation"><?= h(SETTINGS_OWN_PASSWORD_CONFIRM) ?></label><input class="input input-bordered w-full" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" maxlength="72" required></div>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= h(SETTINGS_OWN_PASSWORD_SUBMIT) ?></button>
                </form>
            <?php elseif ($ownPasswordDisplay === 'profile_unavailable') : ?>
                <p class="text-sm opacity-70"><?= h(SETTINGS_OWN_PASSWORD_PROFILE_UNAVAILABLE) ?></p>
            <?php else : ?>
                <p class="text-sm opacity-70"><?= h(SETTINGS_OWN_PASSWORD_UNAVAILABLE) ?></p>
            <?php endif; ?>

            <?php if ($user_role === 'admin') : ?>
                <div class="divider my-8"><?= SETTINGS_BACKUP_OPERATIONS_TITLE ?></div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="card bg-base-200 shadow-md">
                        <div class="card-body">
                            <h3 class="card-title"><i class="fas fa-history mr-2"></i><?= SETTINGS_BACKUP_RESTORE_TITLE ?></h3>
                            <p><?= SETTINGS_BACKUP_IMPORT_DISABLED ?></p>
                            <p><?= SETTINGS_BACKUP_RESTORE_INSTRUCTIONS ?></p>
                            <p><?= SETTINGS_BACKUP_RESTORE_GUIDE ?> <code>docs/TERMINAL.md</code></p>
                        </div>
                    </div>
                    <div class="card bg-base-200 shadow-md">
                        <div class="card-body">
                            <h3 class="card-title"><i class="fas fa-download mr-2"></i><?= SETTINGS_BACKUP_SNAPSHOT_TITLE ?></h3>
                            <p class="mb-4"><?= SETTINGS_BACKUP_SNAPSHOT_DESCRIPTION ?></p>
                            <a href="download.php" class="btn btn-primary w-full"><i class="fas fa-download mr-2"></i><?= SETTINGS_BACKUP_SNAPSHOT_DOWNLOAD ?></a>
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
            <h3 class="font-bold text-lg"><?= h(SETTINGS_SAVE_SUCCESS_TITLE) ?></h3>
            <p class="py-4"><?= h($successMessage) ?></p>
            <div class="modal-action">
                <button onclick="closeModal()" class="btn btn-primary"><?= h(BUTTON_CLOSE) ?></button>
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
        // Reuse the standard chrome's single raw-choice/System resolver.
        setTheme(theme);
    }

    // Theme beim Laden der Seite setzen
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = chronoReadPreference('theme') || 'light';
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


