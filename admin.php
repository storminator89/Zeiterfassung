<?php
require_once __DIR__ . '/security.php';
include 'config.php';

// Sprachdateien laden
$lang = $_SESSION['lang'] ?? 'de';
$langFile = "languages/$lang.php";

if (file_exists($langFile)) {
    require_once $langFile;
} else {
    die("Sprachdatei nicht gefunden!");
}

// Überprüfen, ob der Benutzer eingeloggt und ein Admin ist
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fixed independent Native holiday command, before any existing Admin mutation.
require_once __DIR__.'/lib/native_holiday_refill.php';
if ($_SERVER['REQUEST_METHOD']==='POST' && array_key_exists('refill_holidays',$_POST)) {
    unset($_SESSION['native_holiday_refill_receipt']); // Never show an older success after a new refused attempt.
    try {
        $receipt=native_holiday_refill($conn,(int)$_SESSION['user_id'],$_SESSION['role'],$_SESSION['auth_version']??null,$_POST);
    } catch (InvalidArgumentException $error) { abort_request(400,$error->getMessage()); }
    catch (DomainException $error) { abort_request(409,$error->getMessage()); }
    catch (NativeHolidayRefillError $error) { abort_request($error->status,$error->getMessage()); }
    catch (Throwable) { abort_request(503,'Feiertagsergänzung nicht bestätigt. Neu laden und gespeichertes Jahr prüfen; nicht automatisch wiederholen.'); }
    // A minimal own receipt is retained only after a confirmed COMMIT; never the JSON.
    $_SESSION['native_holiday_refill_receipt']=['actor'=>(int)$_SESSION['user_id'],'result'=>array_intersect_key($receipt,array_flip(['year','inserted','unchanged','conditional_omitted']))];
    header('Cache-Control: no-store');header('Location: admin.php#holiday-refill',true,303);exit;
}
$nativeHolidayActor=att_one($conn,'SELECT auth_source FROM users WHERE id=?',[(int)$_SESSION['user_id']]);
$nativeHolidayRefillEnabled=native_holiday_refill_available($nativeHolidayActor['auth_source']??null,$_SESSION['role'],$_SESSION['auth_version']??null);
$holidayRefillReceipt=$_SERVER['REQUEST_METHOD']==='GET'&&$nativeHolidayRefillEnabled?native_holiday_refill_receipt($_SESSION['native_holiday_refill_receipt']??null,(int)$_SESSION['user_id']):null;
unset($_SESSION['native_holiday_refill_receipt']);

$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$theme_mode = $_SESSION['theme_mode'] ?? 'system';

$error = '';
$successMessage = '';
$createUserDraft = null;
require_once __DIR__.'/lib/admin_create_user_draft.php';

function encryptData($data, $key, $method)
{
    $iv = random_bytes(12);
    $encrypted = openssl_encrypt($data, 'aes-256-gcm', hex2bin($key), OPENSSL_RAW_DATA, $iv, $tag);
    if ($encrypted === false) { throw new RuntimeException('Encryption failed'); }
    return 'v2:' . base64_encode($iv . $tag . $encrypted);
}
function decryptData($data, $key, $method)
{
    // Old credentials used a public key. Re-enter them rather than trusting them.
    if (!str_starts_with($data, 'v2:')) { return ''; }
    $raw = base64_decode(substr($data, 3), true);
    if ($raw === false || strlen($raw) < 28) { return ''; }
    return openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hex2bin($key), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16)) ?: '';
}

// LDAP-Einstellungen aus der Datenbank abrufen
$stmt = $conn->prepare("SELECT * FROM ldap_settings ORDER BY id DESC LIMIT 1");
$stmt->execute();
$ldapSettings = $stmt->fetch(PDO::FETCH_OBJ);

$ldapHost = $ldapSettings->ldap_host ?? '';
$ldapPort = $ldapSettings->ldap_port ?? '';
$ldapUser = $ldapSettings->ldap_user ?? '';
$ldapPass = isset($ldapSettings->ldap_pass) ? decryptData($ldapSettings->ldap_pass, ENCRYPTION_KEY, ENCRYPTION_METHOD) : '';
$ldapBaseDN = $ldapSettings->ldap_base_dn ?? '';

// Pauseneinstellungen aus der Datenbank abrufen
$stmt = $conn->prepare("SELECT * FROM pause_settings ORDER BY hours_threshold ASC");
$stmt->execute();
$pauseSettings = $stmt->fetchAll(PDO::FETCH_OBJ);

// Pauseneinstellungen aktualisieren
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_pause_settings'])) {
    require_once __DIR__ . '/lib/pause_settings.php';
    $pauseTransactionStarted = false;

    try {
        $rules = validatePauseSettings($_POST['hours_threshold'] ?? null, $_POST['minimum_pause'] ?? null);
        $conn->beginTransaction();
        $pauseTransactionStarted = true;
        replacePauseSettingsLocked($conn, $rules);
        $conn->commit();

        $successMessage = "Pauseneinstellungen erfolgreich aktualisiert.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        if ($pauseTransactionStarted && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = "Fehler beim Aktualisieren der Pauseneinstellungen: " . $e->getMessage();
    }
}

// Funktion zum Base64 URL Enkodieren
function base64UrlEncode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Funktion zum Erzeugen des JWT
function generateJWT($header, $payload, $secret)
{
    $headerEncoded = base64UrlEncode(json_encode($header));
    $payloadEncoded = base64UrlEncode(json_encode($payload));

    $signature = hash_hmac('SHA256', "$headerEncoded.$payloadEncoded", $secret, true);
    $signatureEncoded = base64UrlEncode($signature);

    return "$headerEncoded.$payloadEncoded.$signatureEncoded";
}

// Token erzeugen und anzeigen
$token = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_token'])) {
    $header = [
        'alg' => 'HS256',
        'typ' => 'JWT'
    ];

    $payload = [
        'iss' => "localhost",
        'aud' => "localhost",
        'iat' => time(),
        'exp' => time() + 3600, // 1 Jahr Ablaufzeit
        'user_id' => (int)$_SESSION['user_id'],
        'ver' => $_SESSION['auth_version']
    ];

    $secret = JWT_SECRET;
    $token = generateJWT($header, $payload, $secret);

    try {
        $stmt = $conn->prepare("UPDATE users SET token = ? WHERE id = ?");
        $stmt->execute([$token, $_SESSION['user_id']]);
        $successMessage = TOKEN_GENERATED_SUCCESS;
    } catch (PDOException $e) {
        $error = TOKEN_GENERATED_ERROR . $e->getMessage();
    }
}

// LDAP-Synchronisation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['sync_ldap'])) {
    $ldapHost = is_string($_POST['ldap_host'] ?? null) ? $_POST['ldap_host'] : '';
    $ldapPort = is_string($_POST['ldap_port'] ?? null) ? $_POST['ldap_port'] : '';
    $ldapUser = is_string($_POST['ldap_user'] ?? null) ? $_POST['ldap_user'] : '';
    $ldapPass = is_string($_POST['ldap_pass'] ?? null) ? $_POST['ldap_pass'] : '';
    $ldapBaseDN = is_string($_POST['ldap_base_dn'] ?? null) ? $_POST['ldap_base_dn'] : '';
    $ldapConn = null;
    $result = false;
    $ldapFailureMessage = LDAP_SETTINGS_INVALID;
    $ldapImport = null;
    $ldapTransactionStarted = false;

    try {
        // Leere Bind-Daten dürfen nicht versehentlich als anonymer Test gelten.
        if (trim($ldapHost) === '' || trim($ldapUser) === '' || $ldapPass === '' || trim($ldapBaseDN) === ''
            || !ctype_digit($ldapPort) || (int)$ldapPort < 1 || (int)$ldapPort > 65535) {
            throw new RuntimeException();
        }
        $ldapFailureMessage = LDAP_EXTENSION_REQUIRED;
        if (!function_exists('ldap_connect')) { throw new RuntimeException(); }

        // Erst vollständig prüfen; bis einschließlich Suchergebnis keine DB-Schreibzugriffe.
        $ldapFailureMessage = LDAP_CONNECTION_FAILED;
        $ldapConn = @ldap_connect($ldapHost, (int)$ldapPort);
        if ($ldapConn) {
            if (!@ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3)
                || !@ldap_set_option($ldapConn, LDAP_OPT_NETWORK_TIMEOUT, 5)) {
                throw new RuntimeException();
            }
            $ldapFailureMessage = LDAP_TLS_FAILED;
            if (!str_starts_with(strtolower($ldapHost), 'ldaps://') && !@ldap_start_tls($ldapConn)) {
                throw new RuntimeException();
            }
            $ldapFailureMessage = LDAP_BIND_FAILED;
            if (@ldap_bind($ldapConn, $ldapUser, $ldapPass)) {
                $searchFilter = "(objectClass=inetOrgPerson)";
                $attributes = ["uid", "cn", "sn", "mail", "ou", "manager"];
                $ldapFailureMessage = LDAP_SEARCH_FAILED;
                $result = @ldap_search($ldapConn, $ldapBaseDN, $searchFilter, $attributes);
                if ($result) {
                    // Auch ein partielles Ergebnis mit LDAP-Fehlercode ist kein bestandener Test.
                    $ldapResultCode = -1;
                    if (!@ldap_parse_result($ldapConn, $result, $ldapResultCode) || $ldapResultCode !== 0) {
                        throw new RuntimeException();
                    }
                    $entries = @ldap_get_entries($ldapConn, $result);
                    if (!is_array($entries) || !isset($entries['count']) || !is_int($entries['count']) || $entries['count'] < 0
                        || count($entries) !== $entries['count'] + 1) {
                        throw new RuntimeException();
                    }
                    for ($entryIndex = 0; $entryIndex < $entries['count']; $entryIndex++) {
                        if (!isset($entries[$entryIndex]) || !is_array($entries[$entryIndex])) { throw new RuntimeException(); }
                    }

                    // Nur den erfolgreich geprüften Stand verschlüsselt übernehmen.
                    $ldapFailureMessage = LDAP_SYNC_FAILED;
                    $encryptedLdapPass = encryptData($ldapPass, ENCRYPTION_KEY, ENCRYPTION_METHOD);
                    // Fixed materialized stage only: no live result/callback or raw bind password.
                    $ldapImport = ['entries' => $entries, 'host' => $ldapHost, 'port' => $ldapPort,
                        'bind_user' => $ldapUser, 'ciphertext' => $encryptedLdapPass, 'base_dn' => $ldapBaseDN];
                } else {
                    $error = LDAP_SEARCH_FAILED;
                }
            } else {
                $error = LDAP_BIND_FAILED;
            }
        } else {
            $error = LDAP_CONNECTION_FAILED;
        }
    } catch (Throwable $e) {
        // Keine Serverdiagnosen oder eingereichten Zugangsdaten in die UI übernehmen.
        $error = $ldapFailureMessage;
    } finally {
        // Finish transport BEFORE opening the database transaction. Always try
        // unbind even if free fails; no cleanup error may follow a DB commit.
        $ldapCleanupFailed = false;
        try { if ($result && !@ldap_free_result($result)) { $ldapCleanupFailed = true; } }
        catch (Throwable $e) { $ldapCleanupFailed = true; }
        try { if ($ldapConn && !@ldap_unbind($ldapConn)) { $ldapCleanupFailed = true; } }
        catch (Throwable $e) { $ldapCleanupFailed = true; }
        $result = false;
        $ldapConn = null;
        if ($ldapCleanupFailed) {
            $ldapImport = null;
            $error = $error ?: LDAP_SYNC_FAILED;
            $successMessage = '';
        }
    }

    // A valid empty directory is still a materialized stage, not a falsey skip.
    if ($ldapImport !== null) {
        try {
            if ($conn->inTransaction()) {
                abort_request(503, LDAP_SYNC_FAILED); // Never join or roll back another owner.
            }
            require_once __DIR__ . '/lib/ldap_import.php';
            if (!$conn->beginTransaction()) { throw new RuntimeException(); }
            $ldapTransactionStarted = true;
            $managerPlan = chrono_ldap_import_locked($conn, $ldapImport['entries'], $ldapImport['host'],
                $ldapImport['port'], $ldapImport['bind_user'], $ldapImport['ciphertext'], $ldapImport['base_dn']);
            $importError = '';
            $importSuccess = '';
            if ($managerPlan['conflicts']) {
                $reasons = [
                    'ambiguous_identity' => 'mehrdeutige LDAP-Identität',
                    'invalid_identity_dn' => 'ungültiger Identitäts-DN oder DN-Parser nicht verfügbar',
                    'invalid_manager_dn' => 'ungültiger Manager-DN oder DN-Parser nicht verfügbar',
                    'unknown_manager' => 'Manager nicht eindeutig im aktuellen Import vorhanden',
                    'ambiguous_manager' => 'mehrdeutiger Manager',
                    'self_manager' => 'Selbstzuordnung',
                    'cyclic_manager' => 'zyklische Managerbeziehung',
                ];
                $details = [];
                foreach ($managerPlan['conflicts'] as $ldapUserId => $reason) {
                    $details[] = 'Konto #' . $ldapUserId . ': ' . $reasons[$reason];
                }
                $importError = 'LDAP-Identitäten synchronisiert. Managerkonflikte: Die bisherigen Beziehungen dieser Konten bleiben unverändert. ' . implode('; ', $details);
            } else {
                $importSuccess = LDAP_SYNC_SUCCESS;
            }
            // Prepare presentation before COMMIT; publish it only after success.
            if (!$conn->commit()) { throw new RuntimeException(); }
            $ldapTransactionStarted = false;
            $error = $importError;
            $successMessage = $importSuccess;
        } catch (Throwable $e) {
            $successMessage = '';
            if ($ldapTransactionStarted) {
                try {
                    if (!$conn->inTransaction() || !$conn->rollBack() || $conn->inTransaction()) {
                        abort_request(503, LDAP_SYNC_FAILED);
                    }
                } catch (Throwable $rollbackError) {
                    // Unknown commit/rollback outcome: stop, never claim rollback
                    // or continue other handlers/automatically retry this import.
                    abort_request(503, LDAP_SYNC_FAILED);
                }
            } elseif ($conn->inTransaction()) {
                abort_request(503, LDAP_SYNC_FAILED);
            }
            $error = LDAP_SYNC_FAILED;
        }
    }
}

// Aktuellen Benutzer aus der Datenbank abrufen
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_OBJ);

// Abteilungen und Vorgesetzte aus der Datenbank abrufen
$stmt = $conn->prepare("SELECT id, name FROM departments");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_OBJ);

// Abteilung löschen
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_department'])) {
    try {
        require_once __DIR__ . '/lib/departments.php';
        department_delete_if_unused($conn, $_POST['department_id'] ?? null);

        $successMessage = DEPARTMENT_DELETED_SUCCESS;

        // Seite nach erfolgreichem Löschen neu laden
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (InvalidArgumentException | DomainException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = DEPARTMENT_DELETED_ERROR . $e->getMessage();
    }
}

// Abteilung hinzufügen
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_department'])) {
    require_once __DIR__ . '/lib/departments.php';
    $department_name = $_POST['department_name'];

    // Eingaben validieren
    if (department_create_name_missing($department_name)) {
        $error = ERROR_ALL_FIELDS_REQUIRED;
    } else {
        try {
            // Statement vorbereiten und ausführen, um die neue Abteilung einzufügen
            department_create_core($conn, $department_name);

            $successMessage = DEPARTMENT_CREATED_SUCCESS;

            // Seite nach erfolgreichem Hinzufügen neu laden
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $error = DEPARTMENT_CREATED_ERROR . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/lib/admin_users.php';

// Benutzer hinzufügen
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $createInputRejected = false;
    $username = $_POST['username'] ?? null;
    $password = $_POST['password'] ?? null;
    $email = $_POST['email'] ?? null;
    $role = $_POST['role'] ?? null;
    $department_id = $_POST['department'] ?? null;
    $supervisor = $_POST['supervisor'] ?? null;
    $regelarbeitszeit = $_POST['regelarbeitszeit'] ?? null;
    $ueberstunden = $_POST['ueberstunden'] ?? null;

    // Eingaben validieren
    if (empty($username) || empty($password) || empty($email) || empty($role) || empty($department_id) || empty($regelarbeitszeit)) {
        $error = ERROR_ALL_FIELDS_REQUIRED;
        if (!$conn->inTransaction()) { $createUserDraft = admin_create_user_rejected_draft($_POST); }
    } else {
        try {
            admin_create_user($conn, function () use ($conn, $username, $password, $email, $role, $department_id, $supervisor, $regelarbeitszeit, $ueberstunden, &$createInputRejected): void {
                try {
                    $assignment = validateUserAssignment($conn, $role, $supervisor, $department_id);
                    admin_validate_user_fields($username, $email, $regelarbeitszeit, $ueberstunden);
                    if (!is_string($password)) { throw new InvalidArgumentException('Ungültige Benutzereingabe.'); }
                } catch (InvalidArgumentException $e) { $createInputRejected = true; throw $e; }
                $department_id = $assignment['department_id'];
                $supervisor = $assignment['supervisor_id'];
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                // Statement vorbereiten und ausführen, um den neuen Benutzer einzufügen
                $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, department_id, supervisor_id, regelarbeitszeit, ueberstunden) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashedPassword, $email, $role, $department_id, $supervisor, $regelarbeitszeit, $ueberstunden]);
            });

            $successMessage = USER_CREATED_SUCCESS;
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            if ($createInputRejected && !$conn->inTransaction()) {
                $createUserDraft = admin_create_user_rejected_draft($_POST);
            }
        } catch (PDOException $e) {
            $error = USER_CREATED_ERROR . $e->getMessage();
        }
    }
}

// Benutzer löschen
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'] ?? null;

    try {
        admin_mutate_existing_user($conn, $user_id, null, function (int $targetId) use ($conn): void {
            if (att_one($conn, 'SELECT id FROM audit_log WHERE user_id=?', [$targetId])) { throw new AttendanceError('Konten mit Änderungshistorie bleiben erhalten. Eine zentrale Kontodeaktivierung ist noch nicht verbunden; ein lokaler Passwortwechsel sperrt LDAP-Anmeldungen und Terminal-Zugänge nicht.', 409); }
            // Statement vorbereiten und ausführen, um den Benutzer zu löschen
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$targetId]);
        });

        $successMessage = USER_DELETED_SUCCESS;
    } catch (AttendanceError $e) {
        abort_request($e->status, $e->getMessage());
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = USER_DELETED_ERROR . $e->getMessage();
    }
}

// Benutzer bearbeiten
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'] ?? null;
    $username = $_POST['username'] ?? null;
    $email = $_POST['email'] ?? null;
    $role = $_POST['role'] ?? null;
    $password = $_POST['password'] ?? '';
    $department_id = $_POST['department'] ?? null;
    $supervisor = $_POST['supervisor'] ?? null;
    $regelarbeitszeit = $_POST['regelarbeitszeit'] ?? null;
    $ueberstunden = $_POST['ueberstunden'] ?? null;

    try {
        admin_assignment_role($role); // Keep invalid roles out of the existing typed last-admin boundary.
        admin_mutate_existing_user($conn, $user_id, $role, function (int $targetId) use ($conn, $username, $email, $role, $password, $department_id, $supervisor, $regelarbeitszeit, $ueberstunden): void {
            $assignment = validateUserAssignment($conn, $role, $supervisor, $department_id, $targetId);
            admin_validate_user_fields($username, $email, $regelarbeitszeit, $ueberstunden);
            if (!is_string($password)) { throw new InvalidArgumentException('Ungültige Benutzereingabe.'); }
            $department_id = $assignment['department_id'];
            $supervisor = $assignment['supervisor_id'];
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ?, department_id = ?, supervisor_id = ?, regelarbeitszeit = ?, ueberstunden = ? WHERE id = ?");
                $stmt->execute([$username, $email, $role, $hashedPassword, $department_id, $supervisor, $regelarbeitszeit, $ueberstunden, $targetId]);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, department_id = ?, supervisor_id = ?, regelarbeitszeit = ?, ueberstunden = ? WHERE id = ?");
                $stmt->execute([$username, $email, $role, $department_id, $supervisor, $regelarbeitszeit, $ueberstunden, $targetId]);
            }
        });

        $successMessage = USER_UPDATED_SUCCESS;
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = USER_UPDATED_ERROR . $e->getMessage();
    }
}

// Abteilung bearbeiten
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_department'])) {
    require_once __DIR__ . '/lib/departments.php';
    $department_id = $_POST['department_id'];
    $department_name = $_POST['department_name'];

    try {
        department_rename_core($conn, $department_id, $department_name);

        $successMessage = DEPARTMENT_UPDATED_SUCCESS;

        // Seite nach erfolgreichem Bearbeiten neu laden
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        $error = DEPARTMENT_UPDATED_ERROR . $e->getMessage();
    }
}

// Vorgesetzte unabhängig von Suche und Benutzerseite laden, nach allen Änderungen.
$stmt = $conn->prepare("SELECT id, username FROM users WHERE role IN ('supervisor', 'admin') ORDER BY username");
$stmt->execute();
$supervisors = $stmt->fetchAll(PDO::FETCH_OBJ);

// Suchparameter und Paginierung
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10; // Anzahl der Benutzer pro Seite
require_once __DIR__.'/lib/admin_reference_status.php';

// Benutzer aus der Datenbank abrufen (mit Suche und Paginierung)
$stmt = $conn->prepare("
    SELECT users.*, departments.name as department_name, supervisors.username as supervisor_name,
           departments.id as resolved_department_id, supervisors.id as resolved_supervisor_id,
           EXISTS (SELECT 1 FROM audit_log WHERE audit_log.user_id = users.id) AS has_audit_history
    FROM users
    LEFT JOIN departments ON users.department_id = departments.id
    LEFT JOIN users as supervisors ON users.supervisor_id = supervisors.id
    WHERE users.username LIKE :search OR users.email LIKE :search
    ORDER BY users.username
    LIMIT :offset, :perPage
");

$searchParam = "%$search%";
$offset = ($page - 1) * $perPage;

$stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':perPage', $perPage, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_OBJ);

// Gesamtanzahl der Benutzer für die Paginierung
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM users 
    WHERE username LIKE :search OR email LIKE :search
");
$stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
$stmt->execute();
$totalUsers = $stmt->fetchColumn();

$totalPages = ceil($totalUsers / $perPage);

// Header einbinden
include 'header.php';
?>

<div class="drawer lg:drawer-open">
    <input id="my-drawer" type="checkbox" class="drawer-toggle" />
    <div class="drawer-content flex flex-col bg-base-100 text-base-content">
        <!-- Hauptinhalt -->
        <div class="p-4">
            <?php if ($error): ?>
                <div class="alert alert-error shadow-lg mb-4">
                    <div>
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= h($error) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($successMessage): ?>
                <div class="alert alert-success shadow-lg mb-4">
                    <div>
                        <i class="fas fa-check-circle"></i>
                        <span><?= h($successMessage) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php require __DIR__.'/lib/native_holiday_refill_view.php'; ?>

            <!-- Benutzerverwaltung -->
            <div id="user-management" class="card bg-base-100 shadow-xl mb-8">
                <div class="card-body">
                    <h2 class="card-title text-2xl mb-4"><?= USER_MANAGEMENT_TITLE ?></h2>
                    <p id="native-account-access-limits" class="text-sm opacity-70 mb-4" role="note">Diese native Benutzerverwaltung bietet keine zentrale Kontosperre. Lokale Passwortänderungen sperren weder erneute LDAP-Anmeldungen noch Terminal-Zugänge.</p>
                    <button class="btn btn-primary mb-4" id="toggleUserForm">
                        <i class="fas fa-plus mr-2"></i><?= BUTTON_CREATE_USER ?>
                    </button>
                    <div id="userForm" class="<?= $createUserDraft === null ? 'hidden ' : '' ?>mb-6">
                        <form method="post" class="space-y-4">
                            <?php if ($createUserDraft !== null): ?><p class="text-sm opacity-70" role="status">Eingaben zur Überarbeitung erhalten. Bitte alle Angaben prüfen, Rolle und Zuordnungen ausdrücklich neu wählen und das Passwort erneut eingeben. Keine automatische Speicherung oder Wiederholung.</p><?php endif; ?>
<?= csrf_field() ?>
                            <input type="hidden" name="add_user" value="1">
                            <div class="form-control">
                                <label class="label" for="username">
                                    <span class="label-text"><?= FORM_USERNAME ?></span>
                                </label>
                                <input type="text" id="username" name="username"<?= $createUserDraft !== null ? ' value="'.h($createUserDraft['username'] ?? '').'"' : '' ?> class="input input-bordered" required>
                            </div>
                            <div class="form-control">
                                <label class="label" for="password">
                                    <span class="label-text"><?= FORM_PASSWORD ?></span>
                                </label>
                                <input type="password" id="password" name="password" class="input input-bordered" required>
                            </div>
                            <div class="form-control">
                                <label class="label" for="email">
                                    <span class="label-text"><?= FORM_EMAIL ?></span>
                                </label>
                                <input type="email" id="email" name="email"<?= $createUserDraft !== null ? ' value="'.h($createUserDraft['email'] ?? '').'"' : '' ?> class="input input-bordered" required>
                            </div>
                            <div class="form-control">
                                <label class="label" for="role">
                                    <span class="label-text"><?= FORM_ROLE ?></span>
                                </label>
                                <select id="role" name="role" class="select select-bordered" required>
                                    <?php if ($createUserDraft !== null): ?><option value="" disabled selected>Rolle erneut wählen</option><?php endif; ?>
                                    <option value="user"><?= FORM_ROLE_USER ?></option>
                                    <option value="admin"><?= FORM_ROLE_ADMIN ?></option>
                                    <option value="supervisor"><?= FORM_ROLE_SUPERVISOR ?></option>
                                </select>
                            </div>
                            <div class="form-control">
                                <label class="label" for="department">
                                    <span class="label-text"><?= FORM_DEPARTMENT ?></span>
                                </label>
                                <select id="department" name="department" class="select select-bordered" required>
                                    <?php if ($createUserDraft !== null): ?><option value="" disabled selected>Abteilung erneut wählen</option><?php endif; ?>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?= $department->id ?>"><?= htmlspecialchars($department->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-control">
                                <label class="label" for="supervisor">
                                    <span class="label-text"><?= FORM_SUPERVISOR ?></span>
                                </label>
                                <select id="supervisor" name="supervisor" class="select select-bordered">
                                    <?php if ($createUserDraft !== null): ?><option value="" disabled selected>Vorgesetzten oder ausdrücklich keinen erneut wählen</option><?php endif; ?>
                                    <option value=""><?= FORM_NO_SUPERVISOR ?></option>
                                    <?php foreach ($supervisors as $supervisorOption): ?>
                                        <option value="<?= $supervisorOption->id ?>"><?= htmlspecialchars($supervisorOption->username) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-control">
                                <label class="label" for="regelarbeitszeit">
                                    <span class="label-text"><?= FORM_REGULAR_WORKING_HOURS ?></span>
                                </label>
                                <input type="number" id="regelarbeitszeit" name="regelarbeitszeit"<?= $createUserDraft !== null ? ' value="'.h($createUserDraft['regelarbeitszeit'] ?? '').'"' : '' ?> class="input input-bordered" step="0.01" required>
                            </div>
                            <div class="form-control">
                                <label class="label" for="ueberstunden">
                                    <span class="label-text"><?= FORM_OVERTIME ?></span>
                                </label>
                                <input type="number" id="ueberstunden" name="ueberstunden" class="input input-bordered" step="0.01" value="<?= h($createUserDraft === null ? '0' : ($createUserDraft['ueberstunden'] ?? '')) ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus mr-2"></i><?= BUTTON_CREATE_USER ?>
                            </button>
                        </form>
                    </div>
                    
                    <!-- Suchfeld für Benutzer -->
                    <form action="" method="GET" class="mb-4">
                        <div class="form-control">
                            <div class="input-group">
                                <input type="text" name="search" id="userSearch" class="input input-bordered" placeholder="<?= SEARCH_USERS ?>" value="<?= htmlspecialchars($search) ?>">
                                <button class="btn btn-square" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Benutzertabelle -->
                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th class="text-left"><?= TABLE_HEADER_USERNAME ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_EMAIL ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_ROLE ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_DEPARTMENT ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_SUPERVISOR ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_REGULAR_WORKING_HOURS ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_OVERTIME ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_ACTIONS ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): $departmentReference=admin_reference_status($user->department_id,$user->resolved_department_id);$supervisorReference=admin_reference_status($user->supervisor_id,$user->resolved_supervisor_id); ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user->username) ?></td>
                                        <td><?= htmlspecialchars($user->email) ?></td>
                                        <td><?= htmlspecialchars($user->role) ?></td>
                                        <td><?= htmlspecialchars($user->department_name ?? '') ?><?php if($departmentReference==='missing'): ?><small class="block text-warning mt-1">Abteilungs-ID #<?= (int)$user->department_id ?> fehlt. Zuordnung über „Bearbeiten“ klären.</small><?php elseif($departmentReference==='invalid'||$departmentReference==='unknown'): ?><small class="block text-warning mt-1">Abteilungszuordnung nicht eindeutig prüfbar. Bestand über „Bearbeiten“ klären.</small><?php endif; ?></td>
                                        <td><?= htmlspecialchars($user->supervisor_name ?? '') ?><?php if($supervisorReference==='missing'): ?><small class="block text-warning mt-1">Vorgesetzten-ID #<?= (int)$user->supervisor_id ?> fehlt. Zuordnung über „Bearbeiten“ klären.</small><?php elseif($supervisorReference==='invalid'||$supervisorReference==='unknown'): ?><small class="block text-warning mt-1">Vorgesetztenzuordnung nicht eindeutig prüfbar. Bestand über „Bearbeiten“ klären.</small><?php endif; ?></td>
                                        <td><?= h($user->regelarbeitszeit) ?></td>
                                        <td><?= h($user->ueberstunden) ?></td>
                                        <td>
                                            <button class="btn btn-ghost btn-sm" onclick="editUser(<?= h(json_encode((string)$user->id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)" title="<?= BUTTON_EDIT ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ((int)$user->has_audit_history === 1): ?>
                                                <span class="inline-block text-sm"><?= h(ADMIN_USER_DELETE_HISTORY_LOCKED) ?></span>
                                            <?php else: ?>
                                            <form method="post" class="inline" onsubmit="return confirm('<?= CONFIRM_DELETE_USER ?>')">
<?= csrf_field() ?>
                                                <input type="hidden" name="delete_user" value="1">
                                                <input type="hidden" name="user_id" value="<?= $user->id ?>">
                                                <button type="submit" class="btn btn-ghost btn-sm" title="<?= BUTTON_DELETE ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginierung -->
                    <div class="btn-group mt-4">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="btn <?= $i === $page ? 'btn-active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

             <!-- Abteilungsverwaltung -->
             <div id="department-management" class="card bg-base-100 shadow-xl mb-8">
                <div class="card-body">
                    <h2 class="card-title text-2xl mb-4"><?= DEPARTMENT_MANAGEMENT_TITLE ?></h2>
                    <form method="post" class="flex space-x-2 mb-4">
<?= csrf_field() ?>
                        <input type="hidden" name="add_department" value="1">
                        <input type="text" name="department_name" class="input input-bordered flex-grow" placeholder="<?= FORM_NEW_DEPARTMENT ?>" required>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus mr-2"></i><?= BUTTON_ADD_DEPARTMENT ?>
                        </button>
                    </form>
                    <!-- Suchfeld für Abteilungen -->
                    <div class="form-control mb-4">
                        <input type="text" id="departmentSearch" class="input input-bordered" placeholder="<?= SEARCH_DEPARTMENTS ?>">
                    </div>
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th class="text-left"><?= TABLE_HEADER_DEPARTMENT_ID ?></th>
                                <th class="text-left"><?= TABLE_HEADER_DEPARTMENT_NAME ?></th>
                                <th class="text-left"><?= TABLE_HEADER_ACTIONS ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $department): ?>
                                <tr>
                                    <td><?= $department->id ?></td>
                                    <td><?= htmlspecialchars($department->name) ?></td>
                                    <td>
                                        <button class="btn btn-ghost btn-sm" onclick="editDepartment(<?= (int)$department->id ?>, <?= h(json_encode($department->name, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)" title="<?= BUTTON_EDIT ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="post" class="inline" onsubmit="return confirm('<?= CONFIRM_DELETE_DEPARTMENT ?>')">
<?= csrf_field() ?>
                                            <input type="hidden" name="delete_department" value="1">
                                            <input type="hidden" name="department_id" value="<?= $department->id ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm" title="<?= BUTTON_DELETE ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- LDAP-Synchronisation -->
            <div id="ldap-sync" class="card bg-base-100 shadow-xl mb-8">
                <div class="card-body">
                    <h2 class="card-title text-2xl mb-4"><?= LDAP_SYNC_TITLE ?></h2>
                    <form method="post" class="space-y-4">
<?= csrf_field() ?>
                        <input type="hidden" name="sync_ldap" value="1">
                        <div class="form-control">
                            <label class="label" for="ldap_host">
                                <span class="label-text"><?= FORM_LDAP_HOST ?></span>
                            </label>
                            <input type="text" id="ldap_host" name="ldap_host" class="input input-bordered" value="<?= htmlspecialchars($ldapHost) ?>" required>
                        </div>
                        <div class="form-control">
                            <label class="label" for="ldap_port">
                                <span class="label-text"><?= FORM_LDAP_PORT ?></span>
                            </label>
                            <input type="number" id="ldap_port" name="ldap_port" class="input input-bordered" value="<?= htmlspecialchars($ldapPort) ?>" required>
                        </div>
                        <div class="form-control">
                            <label class="label" for="ldap_user">
                                <span class="label-text"><?= FORM_LDAP_USER ?></span>
                            </label>
                            <input type="text" id="ldap_user" name="ldap_user" class="input input-bordered" value="<?= htmlspecialchars($ldapUser) ?>" required>
                        </div>
                        <div class="form-control">
                            <label class="label" for="ldap_pass">
                                <span class="label-text"><?= FORM_LDAP_PASS ?></span>
                            </label>
                            <input type="password" id="ldap_pass" name="ldap_pass" class="input input-bordered" required>
                        </div>
                        <div class="form-control">
                            <label class="label" for="ldap_base_dn">
                                <span class="label-text"><?= FORM_LDAP_BASE_DN ?></span>
                            </label>
                            <input type="text" id="ldap_base_dn" name="ldap_base_dn" class="input input-bordered" value="<?= htmlspecialchars($ldapBaseDN) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync mr-2"></i><?= BUTTON_SYNC_LDAP ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Pauseneinstellungen -->
            <div id="pause-settings" class="card bg-base-100 shadow-xl mb-8">
                <div class="card-body">
                    <h2 class="card-title text-2xl mb-4"><?= PAUSE_SETTINGS_TITLE ?></h2>
                    <form method="post" class="space-y-4">
<?= csrf_field() ?>
                        <input type="hidden" name="update_pause_settings" value="1">
                        <table class="table w-full" id="pauseSettingsTable">
                            <thead>
                                <tr>
                                    <th class="text-left"><?= TABLE_HEADER_HOURS_THRESHOLD ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_MINIMUM_PAUSE ?></th>
                                    <th class="text-left"><?= TABLE_HEADER_ACTIONS ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pauseSettings as $setting): ?>
                                    <tr>
                                        <td>
                                            <input type="number" name="hours_threshold[]" class="input input-bordered w-full" value="<?= htmlspecialchars($setting->hours_threshold) ?>" required>
                                        </td>
                                        <td>
                                            <input type="number" name="minimum_pause[]" class="input input-bordered w-full" value="<?= htmlspecialchars($setting->minimum_pause) ?>" required>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-ghost btn-sm remove-row" title="<?= BUTTON_DELETE ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" id="addRow" class="btn btn-secondary">
                            <i class="fas fa-plus mr-2"></i><?= BUTTON_ADD_ROW ?>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-2"></i><?= BUTTON_SAVE_CHANGES ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- API-Zugang -->
            <div id="api-access" class="card bg-base-100 shadow-xl mb-8">
                <div class="card-body">
                    <h2 class="card-title text-2xl mb-4"><?= API_ACCESS_TITLE ?></h2>
                    <form method="post" class="flex space-x-2 mb-4">
<?= csrf_field() ?>
                        <input type="password" id="tokenField" class="input input-bordered flex-grow" value="<?= htmlspecialchars($currentUser->token ?? '') ?>" readonly>
                        <button type="button" id="toggleToken" class="btn btn-secondary">
                            <i class="fas fa-eye"></i>
                        </button>
                        <input type="hidden" name="generate_token" value="1">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key mr-2"></i><?= BUTTON_GENERATE_TOKEN ?>
                        </button>
                    </form>
                    <a href="apidoc.html" target="_blank" class="btn btn-info">
                        <i class="fas fa-book mr-2"></i><?= BUTTON_API_DOC ?>
                    </a>
                </div>
            </div>
        </div>
    </div> 
    <div class="drawer-side">
        <label for="my-drawer" class="drawer-overlay"></label>
        <div class="bg-base-300 h-full pt-16"> <!-- pt-16 für den Abstand unter der Navbar -->
            <ul class="menu p-4 w-80 text-base-content">
                <!-- Sidebar content here -->
                <li><a href="#user-management"><i class="fas fa-users mr-2"></i><?= USER_MANAGEMENT_TITLE ?></a></li>
                <li><a href="#department-management"><i class="fas fa-building mr-2"></i><?= DEPARTMENT_MANAGEMENT_TITLE ?></a></li>
                <li><a href="#ldap-sync"><i class="fas fa-sync mr-2"></i><?= LDAP_SYNC_TITLE ?></a></li>
                <li><a href="#pause-settings"><i class="fas fa-coffee mr-2"></i><?= PAUSE_SETTINGS_TITLE ?></a></li>
                <li><a href="#api-access"><i class="fas fa-key mr-2"></i><?= API_ACCESS_TITLE ?></a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Modal für Benutzer bearbeiten -->
<dialog id="editUserModal" class="modal">
    <form method="post" class="modal-box">
<?= csrf_field() ?>
        <h3 class="font-bold text-lg mb-4"><?= MODAL_TITLE_EDIT_USER ?></h3>
        <p id="edit_user_detail_error" role="alert"></p>
        <input type="hidden" id="edit_user_id" name="user_id">
        <input type="hidden" name="edit_user" value="1">
        <div class="form-control mb-4">
            <label class="label" for="edit_username">
                <span class="label-text"><?= FORM_USERNAME ?></span>
            </label>
            <input type="text" id="edit_username" name="username" class="input input-bordered" required>
        </div>
        <div class="form-control mb-4">
            <label class="label" for="edit_email">
                <span class="label-text"><?= FORM_EMAIL ?></span>
            </label>
            <input type="email" id="edit_email" name="email" class="input input-bordered" required>
        </div>
        <div class="form-control mb-4">
            <label class="label" for="edit_role">
                <span class="label-text"><?= FORM_ROLE ?></span>
            </label>
            <select id="edit_role" name="role" class="select select-bordered" required>
                <option value="user"><?= FORM_ROLE_USER ?></option>
                <option value="admin"><?= FORM_ROLE_ADMIN ?></option>
                <option value="supervisor"><?= FORM_ROLE_SUPERVISOR ?></option>
            </select>
        </div>
        <div class="form-control mb-4">
            <label class="label" for="edit_department">
                <span class="label-text"><?= FORM_DEPARTMENT ?></span>
            </label>
            <select id="edit_department" name="department" class="select select-bordered" required>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= $department->id ?>"><?= htmlspecialchars($department->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-control mb-4">
            <label class="label" for="edit_supervisor">
                <span class="label-text"><?= FORM_SUPERVISOR ?></span>
            </label>
            <select id="edit_supervisor" name="supervisor" class="select select-bordered">
                <option value=""><?= FORM_NO_SUPERVISOR ?></option>
                <?php foreach ($supervisors as $supervisorOption): ?>
                    <option value="<?= $supervisorOption->id ?>"><?= htmlspecialchars($supervisorOption->username) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-control mb-4">
            <label class="label" for="edit_regelarbeitszeit">
                <span class="label-text"><?= FORM_REGULAR_WORKING_HOURS ?></span>
            </label>
            <p>Änderungen am Soll bitte unter <a href="work_models.php">Arbeitszeitmodelle</a> mit Gültigkeitsdatum speichern.</p><input readonly type="number" id="edit_regelarbeitszeit" name="regelarbeitszeit" class="input input-bordered" step="0.01" required>
        </div>
        <div class="form-control mb-4">
            <label class="label" for="edit_ueberstunden">
                <span class="label-text"><?= FORM_OVERTIME ?></span>
            </label>
            <input type="number" id="edit_ueberstunden" name="ueberstunden" class="input input-bordered" step="0.01" required>
        </div>
        <div class="form-control mb-4">
            <label class="label" for="edit_password">
                <span class="label-text"><?= FORM_NEW_PASSWORD ?></span>
            </label>
            <input type="password" id="edit_password" name="password" class="input input-bordered">
        </div>
        <div class="modal-action">
            <button type="submit" class="btn btn-primary"><?= BUTTON_SAVE_CHANGES ?></button>
            <button type="button" class="btn" onclick="closeEditUserModal()"><?= BUTTON_CANCEL ?></button>
        </div>
    </form>
</dialog>

<!-- Modal für Abteilung bearbeiten -->
<dialog id="editDepartmentModal" class="modal">
    <form method="post" class="modal-box">
<?= csrf_field() ?>
        <h3 class="font-bold text-lg mb-4"><?= MODAL_TITLE_EDIT_DEPARTMENT ?></h3>
        <input type="hidden" id="edit_department_id" name="department_id">
        <input type="hidden" name="edit_department" value="1">
        <div class="form-control mb-4">
            <label class="label" for="edit_department_name">
                <span class="label-text"><?= FORM_DEPARTMENT_NAME ?></span>
            </label>
            <input type="text" id="edit_department_name" name="department_name" class="input input-bordered" required>
        </div>
        <div class="modal-action">
            <button type="submit" class="btn btn-primary"><?= BUTTON_SAVE_CHANGES ?></button>
            <button type="button" class="btn" onclick="closeEditDepartmentModal()"><?= BUTTON_CANCEL ?></button>
        </div>
    </form>
</dialog>

<script>
    // JavaScript-Funktionen für Toggles und Modals
    document.addEventListener('DOMContentLoaded', (event) => {
        const toggleUserForm = document.getElementById('toggleUserForm');
        const userForm = document.getElementById('userForm');
        const toggleToken = document.getElementById('toggleToken');
        const tokenField = document.getElementById('tokenField');
        const addRowButton = document.getElementById('addRow');
        const pauseSettingsTable = document.getElementById('pauseSettingsTable');
        const userSearch = document.getElementById('userSearch');
        const departmentSearch = document.getElementById('departmentSearch');

        toggleUserForm.addEventListener('click', () => {
            userForm.classList.toggle('hidden');
        });

        toggleToken.addEventListener('click', () => {
            if (tokenField.type === 'password') {
                tokenField.type = 'text';
                toggleToken.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                tokenField.type = 'password';
                toggleToken.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });

        addRowButton.addEventListener('click', () => {
            const newRow = pauseSettingsTable.insertRow(-1);
            newRow.innerHTML = `
                <td><input type="number" name="hours_threshold[]" class="input input-bordered w-full" required></td>
                <td><input type="number" name="minimum_pause[]" class="input input-bordered w-full" required></td>
                <td><button type="button" class="btn btn-ghost btn-sm remove-row"><i class="fas fa-trash-alt"></i></button></td>
            `;
            newRow.querySelector('.remove-row').title = <?= json_encode(BUTTON_DELETE, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        });

        pauseSettingsTable.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-row') || e.target.parentElement.classList.contains('remove-row')) {
                const row = e.target.closest('tr');
                row.parentNode.removeChild(row);
            }
        });

        // Suchfunktion für Abteilungen
        departmentSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#department-management table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if(text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Smooth scrolling für die Sidebar-Links
        document.querySelectorAll('.drawer-side a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();

                const targetId = this.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);

                if (targetElement) {
                    const headerOffset = 80; // Anpassen Sie diesen Wert, um den gewünschten Abstand zum oberen Rand zu erhalten
                    const elementPosition = targetElement.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });
    });

    // Exact text carries large IDs; only provably safe legacy numbers may be stringified.
    function adminIntegerText(value, signed = false) {
        if (typeof value !== 'string' || value === '') return null;
        const negative = signed && value[0] === '-';
        const digits = negative ? value.slice(1) : value;
        if (digits === '' || /[^0-9]/.test(digits) || (digits.length > 1 && digits[0] === '0')
            || ((!signed || negative) && digits === '0')) return null;
        const maximum = negative ? '9223372036854775808' : '9223372036854775807';
        return digits.length > maximum.length || (digits.length === maximum.length && digits > maximum) ? null : value;
    }
    function adminRequestedId(value) {
        return typeof value === 'number'
            ? (Number.isSafeInteger(value) && value > 0 ? String(value) : null)
            : adminIntegerText(value);
    }
    function adminDetailId(data, requested) {
        if (!data || typeof data !== 'object' || !Object.prototype.hasOwnProperty.call(data, 'id')) throw new Error('Invalid user detail.');
        const hasText = Object.prototype.hasOwnProperty.call(data, 'id_string');
        const exact = hasText ? adminIntegerText(data.id_string) : adminRequestedId(data.id);
        if (exact === null || exact !== requested || (hasText && !(
            (typeof data.id === 'number' && Number.isInteger(data.id) && Number(exact) === data.id)
            || (typeof data.id === 'string' && data.id === exact)))) throw new Error('Invalid user detail.');
        return exact;
    }
    function adminDetailAssignment(data, key) {
        if (!Object.prototype.hasOwnProperty.call(data, key)) throw new Error('Invalid assignment detail.');
        const raw = data[key], hasText = Object.prototype.hasOwnProperty.call(data, key + '_string');
        if (!hasText) {
            if (raw === null || typeof raw === 'string') return raw;
            if (typeof raw === 'number' && Number.isFinite(raw) && Math.abs(raw) <= Number.MAX_SAFE_INTEGER) return String(raw);
            throw new Error('Unconfirmed assignment detail.');
        }
        const exact = data[key + '_string'];
        if (raw === null && exact === null) return null;
        if (typeof exact !== 'string' || raw === null) throw new Error('Invalid assignment detail.');
        if (typeof raw === 'string') {
            if (raw !== exact) throw new Error('Inconsistent assignment detail.');
        } else if (typeof raw === 'number' && Number.isFinite(raw)) {
            if (Number.isSafeInteger(raw) || !Number.isInteger(raw)) {
                if (exact !== String(raw)) throw new Error('Inconsistent assignment detail.');
            } else if (adminIntegerText(exact, true) === null || Number(exact) !== raw) {
                throw new Error('Unconfirmed assignment detail.');
            }
        } else {
            throw new Error('Invalid assignment detail.');
        }
        // Preserve exact stored legacy 0/negative/string references for explicit correction.
        return exact;
    }
    let userDetailRequest = 0;
    let confirmedUserDetail = null;
    const userDetailModal = document.getElementById('editUserModal');
    const userDetailForm = userDetailModal.querySelector('form');
    const userDetailSave = userDetailForm.querySelector('button[type="submit"]');
    const userDetailError = document.getElementById('edit_user_detail_error');
    const userDetailFields = ['edit_username', 'edit_email', 'edit_role', 'edit_department',
        'edit_supervisor', 'edit_regelarbeitszeit', 'edit_ueberstunden', 'edit_password'];
    function clearUserDetails() {
        confirmedUserDetail = null;
        userDetailSave.disabled = true;
        userDetailFields.forEach(id => { document.getElementById(id).disabled = true; });
        ['edit_user_id', 'edit_username', 'edit_email', 'edit_regelarbeitszeit',
            'edit_ueberstunden', 'edit_password'].forEach(id => { document.getElementById(id).value = ''; });
        ['edit_role', 'edit_department', 'edit_supervisor'].forEach(id => {
            const select = document.getElementById(id);
            select.querySelectorAll('[data-detail-option]').forEach(option => option.remove());
            select.selectedIndex = -1;
        });
    }
    function selectUserAssignment(id, value) {
        const select = document.getElementById(id);
        const exact = value === null ? '' : value;
        if (!Array.from(select.options).some(option => option.value === exact)) {
            const option = document.createElement('option');
            option.value = exact;
            option.textContent = exact;
            option.dataset.detailOption = '1';
            select.appendChild(option);
        }
        select.value = exact;
    }
    async function editUser(userId) {
        const request = ++userDetailRequest;
        clearUserDetails();
        userDetailError.textContent = '';
        if (!userDetailModal.open) userDetailModal.showModal();
        try {
            const requestedUserId = adminRequestedId(userId);
            if (requestedUserId === null) throw new Error('Invalid user detail target.');
            const response = await fetch('get_user.php?id=' + encodeURIComponent(requestedUserId));
            if (!response.ok) throw new Error('User detail unavailable.');
            const data = await response.json();
            if (request !== userDetailRequest || !userDetailModal.open) return;
            const exactUserId = adminDetailId(data, requestedUserId);
            const exactDepartmentId = adminDetailAssignment(data, 'department_id');
            const exactSupervisorId = adminDetailAssignment(data, 'supervisor_id');
            document.getElementById('edit_user_id').value = exactUserId;
            document.getElementById('edit_username').value = data.username;
            document.getElementById('edit_email').value = data.email;
            document.getElementById('edit_role').value = data.role;
            selectUserAssignment('edit_department', exactDepartmentId);
            selectUserAssignment('edit_supervisor', exactSupervisorId);
            document.getElementById('edit_regelarbeitszeit').value = data.regelarbeitszeit;
            document.getElementById('edit_ueberstunden').value = data.ueberstunden;
            userDetailFields.forEach(id => { document.getElementById(id).disabled = false; });
            confirmedUserDetail = exactUserId;
            userDetailSave.disabled = false;
        } catch (error) {
            if (request !== userDetailRequest || !userDetailModal.open) return;
            clearUserDetails();
            userDetailError.textContent = 'Benutzerdetails nicht verfügbar. Bitte neu laden und erneut versuchen.';
        }
    }
    function closeEditUserModal() {
        ++userDetailRequest;
        clearUserDetails();
        userDetailModal.close();
    }
    userDetailModal.addEventListener('cancel', () => {
        ++userDetailRequest;
        clearUserDetails();
    });
    userDetailModal.addEventListener('close', () => {
        // An old queued close event must not clear a newly opened request.
        if (userDetailModal.open) return;
        ++userDetailRequest;
        clearUserDetails();
    });
    userDetailForm.addEventListener('submit', event => {
        if (!userDetailModal.open || confirmedUserDetail === null
            || document.getElementById('edit_user_id').value !== confirmedUserDetail) {
            event.preventDefault();
        }
    });
    clearUserDetails();

    function editDepartment(departmentId, departmentName) {
        const modal = document.getElementById('editDepartmentModal');
        document.getElementById('edit_department_id').value = departmentId;
        document.getElementById('edit_department_name').value = departmentName;
        modal.showModal();
    }

    function closeEditDepartmentModal() {
        const modal = document.getElementById('editDepartmentModal');
        modal.close();
    }
</script>




