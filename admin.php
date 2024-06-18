<?php
session_start();
include 'config.php';

// Sprachdateien laden
$lang = $_SESSION['lang'] ?? 'de';
require_once "languages/$lang.php";

// Überprüfen, ob der Benutzer eingeloggt und ein Admin ist
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

$error = '';
$successMessage = '';

// LDAP-Einstellungen aus der Datenbank abrufen
$stmt = $conn->prepare("SELECT * FROM ldap_settings ORDER BY id DESC LIMIT 1");
$stmt->execute();
$ldapSettings = $stmt->fetch(PDO::FETCH_OBJ);

$ldapHost = $ldapSettings->ldap_host ?? '';
$ldapPort = $ldapSettings->ldap_port ?? '';
$ldapUser = $ldapSettings->ldap_user ?? '';
$ldapPass = $ldapSettings->ldap_pass ?? '';
$ldapBaseDN = $ldapSettings->ldap_base_dn ?? '';

// Pauseneinstellungen aus der Datenbank abrufen
$stmt = $conn->prepare("SELECT * FROM pause_settings ORDER BY hours_threshold ASC");
$stmt->execute();
$pauseSettings = $stmt->fetchAll(PDO::FETCH_OBJ);

// Pauseneinstellungen aktualisieren
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_pause_settings'])) {
    $hoursThresholds = $_POST['hours_threshold'];
    $minimumPauses = $_POST['minimum_pause'];

    try {
        $stmt = $conn->prepare("DELETE FROM pause_settings");
        $stmt->execute();

        foreach ($hoursThresholds as $index => $hoursThreshold) {
            $stmt = $conn->prepare("INSERT INTO pause_settings (hours_threshold, minimum_pause) VALUES (?, ?)");
            $stmt->execute([$hoursThreshold, $minimumPauses[$index]]);
        }

        $successMessage = "Pauseneinstellungen erfolgreich aktualisiert.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
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
        'exp' => time() + (365 * 24 * 60 * 60), // 1 Jahr Ablaufzeit
        'user_id' => $_SESSION['user_id']
    ];

    $secret = 'your_secret_key';
    $token = generateJWT($header, $payload, $secret);

    try {
        $stmt = $conn->prepare("UPDATE users SET token = ? WHERE id = ?");
        $stmt->execute([$token, $_SESSION['user_id']]);
        $successMessage = TOKEN_GENERATED_SUCCESS;
    } catch (PDOException $e) {
        $error = TOKEN_GENERATED_ERROR . $e->getMessage();
    }
}

function getUidFromDn($dn)
{
    $parts = ldap_explode_dn($dn, 1);
    foreach ($parts as $part) {
        if (strpos($part, 'uid=') === 0) {
            return substr($part, 4); // 'uid=' entfernen
        }
    }
    return null;
}

// LDAP-Synchronisation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['sync_ldap'])) {
    $ldapHost = $_POST['ldap_host'];
    $ldapPort = $_POST['ldap_port'];
    $ldapUser = $_POST['ldap_user'];
    $ldapPass = $_POST['ldap_pass'];
    $ldapBaseDN = $_POST['ldap_base_dn'];

    try {
        // LDAP-Verbindung herstellen
        $ldapConn = ldap_connect($ldapHost, $ldapPort);
        if ($ldapConn) {
            ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
            if (ldap_bind($ldapConn, $ldapUser, $ldapPass)) {
                $searchFilter = "(objectClass=inetOrgPerson)";
                $attributes = ["uid", "cn", "sn", "mail", "ou", "manager"];
                $result = ldap_search($ldapConn, $ldapBaseDN, $searchFilter, $attributes);
                if ($result) {
                    $entries = ldap_get_entries($ldapConn, $result);
                    foreach ($entries as $entry) {
                        if (isset($entry["uid"][0]) && isset($entry["mail"][0])) {
                            $username = $entry["uid"][0];
                            $email = $entry["mail"][0];
                            $cn = isset($entry["cn"][0]) ? $entry["cn"][0] : '';
                            $sn = isset($entry["sn"][0]) ? $entry["sn"][0] : '';
                            $department = isset($entry["ou"][0]) ? $entry["ou"][0] : '';
                            $managerDn = isset($entry["manager"][0]) ? $entry["manager"][0] : '';

                            // Prüfen, ob der Benutzer bereits in der SQLite-Datenbank existiert
                            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
                            $stmt->bindParam(':username', $username);
                            $stmt->execute();
                            $user = $stmt->fetch();

                            // Vorgesetzten-ID aus der Datenbank abrufen
                            $supervisorId = null;
                            if ($managerDn) {
                                $managerUsername = getUidFromDn($managerDn); // Extrahiere den Benutzernamen aus dem DN
                                $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
                                $stmt->bindParam(':username', $managerUsername);
                                $stmt->execute();
                                $supervisor = $stmt->fetch(PDO::FETCH_OBJ);
                                $supervisorId = $supervisor ? $supervisor->id : null;
                            }

                            // Abteilungs-ID aus der Datenbank abrufen oder erstellen
                            $stmt = $conn->prepare("SELECT id FROM departments WHERE name = :name");
                            $stmt->bindParam(':name', $department);
                            $stmt->execute();
                            $dept = $stmt->fetch(PDO::FETCH_OBJ);
                            $departmentId = $dept ? $dept->id : null;

                            if (!$departmentId && $department) {
                                // Abteilung einfügen, falls nicht vorhanden
                                $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (:name)");
                                $stmt->bindParam(':name', $department);
                                $stmt->execute();
                                $departmentId = $conn->lastInsertId();
                            }

                            if ($user) {
                                // Benutzerinformationen aktualisieren
                                $updateSql = "UPDATE users SET email = :email, department_id = :department_id, supervisor_id = :supervisor_id WHERE username = :username";
                                $stmt = $conn->prepare($updateSql);
                                $stmt->bindParam(':email', $email);
                                $stmt->bindParam(':department_id', $departmentId);
                                $stmt->bindParam(':supervisor_id', $supervisorId);
                                $stmt->bindParam(':username', $username);
                                $stmt->execute();
                            } else {
                                // Neuen Benutzer einfügen
                                $insertSql = "INSERT INTO users (username, password, email, role, department_id, supervisor_id) VALUES (:username, :password, :email, 'user', :department_id, :supervisor_id)";
                                $stmt = $conn->prepare($insertSql);
                                $passwordHash = password_hash("defaultpassword", PASSWORD_BCRYPT);
                                $stmt->bindParam(':username', $username);
                                $stmt->bindParam(':password', $passwordHash);
                                $stmt->bindParam(':email', $email);
                                $stmt->bindParam(':department_id', $departmentId);
                                $stmt->bindParam(':supervisor_id', $supervisorId);
                                $stmt->execute();
                            }
                        }
                    }

                    // Speichern der LDAP-Einstellungen in der Datenbank
                    $stmt = $conn->prepare("INSERT INTO ldap_settings (ldap_host, ldap_port, ldap_user, ldap_pass, ldap_base_dn) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$ldapHost, $ldapPort, $ldapUser, $ldapPass, $ldapBaseDN]);

                    $successMessage = LDAP_SYNC_SUCCESS;
                }
                ldap_unbind($ldapConn);
            } else {
                $error = LDAP_BIND_FAILED;
            }
        } else {
            $error = LDAP_CONNECTION_FAILED;
        }
    } catch (\PDOException $e) {
        $error = DATABASE_CONNECTION_FAILED . $e->getMessage();
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

$stmt = $conn->prepare("SELECT id, username FROM users");
$stmt->execute();
$allUsers = $stmt->fetchAll(PDO::FETCH_OBJ);

// Abteilung löschen
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_department'])) {
    $department_id = $_POST['department_id'];

    try {
        // Statement vorbereiten und ausführen, um die Abteilung zu löschen
        $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->execute([$department_id]);

        $successMessage = DEPARTMENT_DELETED_SUCCESS;

        // Seite nach erfolgreichem Löschen neu laden
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        $error = DEPARTMENT_DELETED_ERROR . $e->getMessage();
    }
}

// Abteilung hinzufügen
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_department'])) {
    $department_name = $_POST['department_name'];

    // Eingaben validieren
    if (empty($department_name)) {
        $error = ERROR_ALL_FIELDS_REQUIRED;
    } else {
        try {
            // Statement vorbereiten und ausführen, um die neue Abteilung einzufügen
            $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
            $stmt->execute([$department_name]);

            $successMessage = DEPARTMENT_CREATED_SUCCESS;

            // Seite nach erfolgreichem Hinzufügen neu laden
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $error = DEPARTMENT_CREATED_ERROR . $e->getMessage();
        }
    }
}

// Benutzer hinzufügen
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $department_id = $_POST['department'];
    $supervisor = $_POST['supervisor'];

    // Eingaben validieren
    if (empty($username) || empty($password) || empty($email) || empty($role) || empty($department_id)) {
        $error = ERROR_ALL_FIELDS_REQUIRED;
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Statement vorbereiten und ausführen, um den neuen Benutzer einzufügen
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, department_id, supervisor_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hashedPassword, $email, $role, $department_id, $supervisor]);

            $successMessage = USER_CREATED_SUCCESS;
        } catch (PDOException $e) {
            $error = USER_CREATED_ERROR . $e->getMessage();
        }
    }
}

// Benutzer löschen
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];

    try {
        // Statement vorbereiten und ausführen, um den Benutzer zu löschen
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);

        $successMessage = USER_DELETED_SUCCESS;
    } catch (PDOException $e) {
        $error = USER_DELETED_ERROR . $e->getMessage();
    }
}

// Benutzer bearbeiten
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = $_POST['password'];
    $department_id = $_POST['department'];
    $supervisor = $_POST['supervisor'];

    try {
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ?, department_id = ?, supervisor_id = ? WHERE id = ?");
            $stmt->execute([$username, $email, $role, $hashedPassword, $department_id, $supervisor, $user_id]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, department_id = ?, supervisor_id = ? WHERE id = ?");
            $stmt->execute([$username, $email, $role, $department_id, $supervisor, $user_id]);
        }

        $successMessage = USER_UPDATED_SUCCESS;
    } catch (PDOException $e) {
        $error = USER_UPDATED_ERROR . $e->getMessage();
    }
}

// Abteilung bearbeiten
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_department'])) {
    $department_id = $_POST['department_id'];
    $department_name = $_POST['department_name'];

    try {
        $stmt = $conn->prepare("UPDATE departments SET name = ? WHERE id = ?");
        $stmt->execute([$department_name, $department_id]);

        $successMessage = DEPARTMENT_UPDATED_SUCCESS;

        // Seite nach erfolgreichem Bearbeiten neu laden
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        $error = DEPARTMENT_UPDATED_ERROR . $e->getMessage();
    }
}

// Alle Benutzer aus der Datenbank abrufen
$stmt = $conn->prepare("SELECT users.*, departments.name as department_name, supervisors.username as supervisor_name
                        FROM users
                        LEFT JOIN departments ON users.department_id = departments.id
                        LEFT JOIN users as supervisors ON users.supervisor_id = supervisors.id");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_OBJ);

// Alle Abteilungen aus der Datenbank abrufen
$stmt = $conn->prepare("SELECT * FROM departments");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_OBJ);

?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <!-- Meta tags and title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ADMIN_PAGE_TITLE ?></title>
    <!-- Favicon and external stylesheets -->
    <link rel="icon" href="assets/kolibri_icon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="./assets/css/main.css">
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
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
        <h2><?= USER_MANAGEMENT_TITLE ?></h2>
        <form method="post" class="mt-4">
            <input type="hidden" name="add_user" value="1">
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fas fa-user"></i></span>
                <input type="text" class="form-control" id="username" name="username" placeholder="<?= FORM_USERNAME ?>" required>
            </div>
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password" placeholder="<?= FORM_PASSWORD ?>" required>
            </div>
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <input type="email" class="form-control" id="email" name="email" placeholder="<?= FORM_EMAIL ?>" required>
            </div>
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                <select class="form-control" id="role" name="role" required>
                    <option value="user"><?= FORM_ROLE_USER ?></option>
                    <option value="admin"><?= FORM_ROLE_ADMIN ?></option>
                    <option value="supervisor"><?= FORM_ROLE_SUPERVISOR ?></option>
                </select>
            </div>
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fas fa-building"></i></span>
                <select class="form-control" id="department" name="department" required>
                    <option value=""><?= FORM_SELECT_DEPARTMENT ?></option>
                    <?php foreach ($departments as $department) : ?>
                        <option value="<?= htmlspecialchars($department->id) ?>"><?= htmlspecialchars($department->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                <select class="form-control" id="supervisor" name="supervisor">
                    <option value=""><?= FORM_SELECT_SUPERVISOR ?></option>
                    <?php foreach ($allUsers as $user) : ?>
                        <option value="<?= $user->id ?>"><?= htmlspecialchars($user->username) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus mr-1"></i> <?= BUTTON_CREATE_USER ?></button>
        </form>

        <!-- LDAP Synchronization Form -->
        <h2 class="container mt-4"><?= LDAP_SYNC_TITLE ?></h2>
        <form method="post" class="mt-4">
            <input type="hidden" name="sync_ldap" value="1">
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fas fa-server"></i></span>
                <input type="text" class="form-control" id="ldap_host" name="ldap_host" placeholder="<?= LDAP_HOST ?> e.g. ldap://ldap.forumsys.com" value="<?= htmlspecialchars($ldapHost) ?>" required>
            </div>
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fas fa-network-wired"></i></span>
                <input type="number" class="form-control" id="ldap_port" name="ldap_port" placeholder="<?= LDAP_PORT ?> 389" value="<?= htmlspecialchars($ldapPort) ?>" required>
            </div>
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fas fa-user"></i></span>
                <input type="text" class="form-control" id="ldap_user" name="ldap_user" placeholder="<?= LDAP_USER ?> e.g. cn=read-only-admin,dc=example,dc=com" value="<?= htmlspecialchars($ldapUser) ?>" required>
            </div>
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" class="form-control" id="ldap_pass" name="ldap_pass" placeholder="<?= LDAP_PASS ?>" value="<?= htmlspecialchars($ldapPass) ?>" required>
            </div>
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fas fa-sitemap"></i></span>
                <input type="text" class="form-control" id="ldap_base_dn" name="ldap_base_dn" placeholder="<?= LDAP_BASE_DN ?> dc=example,dc=com" value="<?= htmlspecialchars($ldapBaseDN) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt mr-1"></i> <?= BUTTON_SYNC_LDAP ?></button>
        </form>

        <!-- Pauseneinstellungen Form -->
        <h2 class="container mt-4"><?= PAUSE_SETTINGS_TITLE ?></h2>
        <form method="post" class="mt-4">
            <input type="hidden" name="update_pause_settings" value="1">
            <div class="mb-3">
                <table class="table table-bordered" id="pauseSettingsTable">
                    <thead>
                        <tr>
                            <th><?= HOURS_THRESHOLD ?></th>
                            <th><?= MINIMUM_PAUSE ?></th>
                            <th><?= ACTIONS ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pauseSettings as $setting) : ?>
                            <tr>
                                <td>
                                    <input type="number" name="hours_threshold[]" class="form-control" value="<?= htmlspecialchars($setting->hours_threshold) ?>" required>
                                </td>
                                <td>
                                    <input type="number" name="minimum_pause[]" class="form-control" value="<?= htmlspecialchars($setting->minimum_pause) ?>" required>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-trash-alt"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="button" class="btn btn-secondary btn-sm" id="addRow"><i class="fas fa-plus"></i> <?= ADD_ROW ?></button>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= SAVE_SETTINGS ?></button>
        </form>


        <!-- Search Bar -->
        <div class="mt-5 mb-3">
            <input type="text" id="searchInput" class="form-control" placeholder="<?= FORM_SEARCH_USER ?>">
        </div>

        <!-- Users table -->
        <h2 class="mt-3"><?= EXISTING_USERS_TITLE ?></h2>
        <table class="table table-striped mt-3" id="usersTable">
            <thead>
                <tr>
                    <th><?= TABLE_HEADER_ID ?></th>
                    <th><?= TABLE_HEADER_USERNAME ?></th>
                    <th><?= TABLE_HEADER_EMAIL ?></th>
                    <th><?= TABLE_HEADER_ROLE ?></th>
                    <th><?= TABLE_HEADER_DEPARTMENT ?></th>
                    <th><?= TABLE_HEADER_SUPERVISOR ?></th>
                    <th><?= TABLE_HEADER_ACTIONS ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user) : ?>
                    <tr>
                        <td><?= $user->id ?></td>
                        <td><?= htmlspecialchars($user->username) ?></td>
                        <td><?= htmlspecialchars($user->email) ?></td>
                        <td><?= htmlspecialchars($user->role) ?></td>
                        <td><?= htmlspecialchars($user->department_name) ?></td>
                        <td><?= htmlspecialchars($user->supervisor_name) ?></td>
                        <td>
                            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editUserModal" data-userid="<?= $user->id ?>" data-username="<?= htmlspecialchars($user->username) ?>" data-email="<?= htmlspecialchars($user->email) ?>" data-role="<?= htmlspecialchars($user->role) ?>" data-department="<?= htmlspecialchars($user->department_id) ?>" data-supervisor="<?= htmlspecialchars($user->supervisor_id) ?>"><i class="fas fa-edit mr-1"></i> <?= BUTTON_EDIT ?></button>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="delete_user" value="1">
                                <input type="hidden" name="user_id" value="<?= $user->id ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('<?= CONFIRM_DELETE_USER ?>')"><i class="fas fa-trash-alt mr-1"></i> <?= BUTTON_DELETE ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Departments table -->
        <h2 class="mt-3"><?= DEPARTMENT_MANAGEMENT_TITLE ?></h2>
        <form method="post" class="mt-4 mb-4">
            <input type="hidden" name="add_department" value="1">
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fas fa-building"></i></span>
                <input type="text" class="form-control" id="department_name" name="department_name" placeholder="<?= FORM_NEW_DEPARTMENT ?>" required>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> <?= BUTTON_ADD_DEPARTMENT ?></button>
            </div>
        </form>
        <table class="table table-striped mt-3" id="departmentsTable">
            <thead>
                <tr>
                    <th><?= TABLE_HEADER_DEPARTMENT_ID ?></th>
                    <th><?= TABLE_HEADER_DEPARTMENT_NAME ?></th>
                    <th><?= TABLE_HEADER_ACTIONS ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($departments as $department) : ?>
                    <tr>
                        <td><?= $department->id ?></td>
                        <td><?= htmlspecialchars($department->name) ?></td>
                        <td>
                            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editDepartmentModal" data-departmentid="<?= $department->id ?>" data-departmentname="<?= htmlspecialchars($department->name) ?>"><i class="fas fa-edit mr-1"></i> <?= BUTTON_EDIT ?></button>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="delete_department" value="1">
                                <input type="hidden" name="department_id" value="<?= $department->id ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('<?= CONFIRM_DELETE_DEPARTMENT ?>')"><i class="fas fa-trash-alt mr-1"></i> <?= BUTTON_DELETE ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2><?= API_ACCESS_TITLE ?></h2>

        <!-- Generate Token Form and Display -->
        <div class="mt-4">
            <form method="post" class="input-group mb-3">
                <input type="password" class="form-control" id="tokenField" value="<?= htmlspecialchars($currentUser->token ?? '') ?>" readonly>
                <button class="btn btn-outline-secondary" type="button" id="toggleToken"><i class="fas fa-eye"></i></button>
                <input type="hidden" name="generate_token" value="1">
                <button type="submit" class="btn btn-success"><i class="fas fa-key mr-1"></i> <?= BUTTON_GENERATE_TOKEN ?></button>
            </form>
            <div class="mt-3">
                <a href="apidoc.html" target="_blank" class="btn btn-primary"><i class="fas fa-book"></i> <?= BUTTON_API_DOC ?></a>
            </div>
        </div>
    </div>

    <!-- Modal for Success -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="successModalLabel"><?= MODAL_TITLE_SUCCESS ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= $successMessage ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= MODAL_BUTTON_CLOSE ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Error -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="errorModalLabel"><?= MODAL_TITLE_ERROR ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= $error ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= MODAL_BUTTON_CLOSE ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Editing User -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalLabel"><?= MODAL_TITLE_EDIT_USER ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="edit_user" value="1">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3 input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="mb-3 input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3 input-group">
                            <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                            <select class="form-control" id="edit_role" name="role" required>
                                <option value="user"><?= FORM_ROLE_USER ?></option>
                                <option value="admin"><?= FORM_ROLE_ADMIN ?></option>
                                <option value="supervisor"><?= FORM_ROLE_SUPERVISOR ?></option>
                            </select>
                        </div>
                        <div class="mb-3 input-group">
                            <span class="input-group-text"><i class="fas fa-building"></i></span>
                            <select class="form-control" id="edit_department" name="department" required>
                                <option value=""><?= FORM_SELECT_DEPARTMENT ?></option>
                                <?php foreach ($departments as $department) : ?>
                                    <option value="<?= htmlspecialchars($department->id) ?>"><?= htmlspecialchars($department->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 input-group">
                            <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                            <select class="form-control" id="edit_supervisor" name="supervisor">
                                <option value=""><?= FORM_SELECT_SUPERVISOR ?></option>
                                <?php foreach ($allUsers as $user) : ?>
                                    <option value="<?= $user->id ?>"><?= htmlspecialchars($user->username) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="edit_password" name="password" placeholder="<?= FORM_NEW_PASSWORD ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= MODAL_BUTTON_CLOSE ?></button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> <?= BUTTON_SAVE_CHANGES ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for Editing Department -->
    <div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editDepartmentModalLabel"><?= MODAL_TITLE_EDIT_DEPARTMENT ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="edit_department" value="1">
                        <input type="hidden" name="department_id" id="edit_department_id">
                        <div class="mb-3 input-group">
                            <span class="input-group-text"><i class="fas fa-building"></i></span>
                            <input type="text" class="form-control" id="edit_department_name" name="department_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= MODAL_BUTTON_CLOSE ?></button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> <?= BUTTON_SAVE_CHANGES ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer mt-auto py-3">
        <div class="container">
            <span class="text-muted"><?= FOOTER_TEXT ?></span>
        </div>
    </footer>

    <!-- Show modals if there are messages -->
    <script>
        $(document).ready(function() {
            <?php if ($successMessage) : ?>
                var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
            <?php endif; ?>

            <?php if ($error) : ?>
                var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                errorModal.show();
            <?php endif; ?>

            $('#editUserModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var userId = button.data('userid');
                var username = button.data('username');
                var email = button.data('email');
                var role = button.data('role');
                var department = button.data('department');
                var supervisor = button.data('supervisor');

                var modal = $(this);
                modal.find('#edit_user_id').val(userId);
                modal.find('#edit_username').val(username);
                modal.find('#edit_email').val(email);
                modal.find('#edit_role').val(role);
                modal.find('#edit_department').val(department);
                modal.find('#edit_supervisor').val(supervisor);
            });

            $('#editDepartmentModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var departmentId = button.data('departmentid');
                var departmentName = button.data('departmentname');

                var modal = $(this);
                modal.find('#edit_department_id').val(departmentId);
                modal.find('#edit_department_name').val(departmentName);
            });

            // Suchfunktion
            $("#searchInput").on("keyup", function() {
                var value = $(this).val().toLowerCase();
                $("#usersTable tbody tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });

            // Zeige/hide neue Abteilungseingabe basierend auf der Auswahl
            $('#department').change(function() {
                if ($(this).val() === 'new_department') {
                    $('#new_department_group').show();
                } else {
                    $('#new_department_group').hide();
                }
            });

            $('#edit_department').change(function() {
                if ($(this).val() === 'new_department') {
                    $('#edit_new_department_group').show();
                } else {
                    $('#edit_new_department_group').hide();
                }
            });

            // Pauseneinstellungen Zeile hinzufügen
            $('#addRow').click(function() {
                var newRow = `<tr>
                    <td><input type="number" name="hours_threshold[]" class="form-control" required></td>
                    <td><input type="number" name="minimum_pause[]" class="form-control" required></td>
                    <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-trash-alt"></i></button></td>
                </tr>`;
                $('#pauseSettingsTable tbody').append(newRow);
            });

            // Pauseneinstellungen Zeile entfernen
            $('#pauseSettingsTable').on('click', '.remove-row', function() {
                $(this).closest('tr').remove();
            });
        });

        document.addEventListener('DOMContentLoaded', (event) => {
            const toggleButton = document.getElementById('toggleToken');
            const tokenField = document.getElementById('tokenField');

            toggleButton.addEventListener('click', () => {
                if (tokenField.type === 'password') {
                    tokenField.type = 'text';
                    toggleButton.innerHTML = '<i class="fas fa-eye-slash"></i>';
                } else {
                    tokenField.type = 'password';
                    toggleButton.innerHTML = '<i class="fas fa-eye"></i>';
                }
            });
        });
    </script>
</body>

</html>