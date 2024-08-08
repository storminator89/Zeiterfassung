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

// Überprüfen, ob der Benutzer eingeloggt und ein Admin ist
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$theme_mode = $_SESSION['theme_mode'] ?? 'system';

$error = '';
$successMessage = '';

function encryptData($data, $key, $method)
{
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
    $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function decryptData($data, $key, $method)
{
    list($encryptedData, $iv) = explode('::', base64_decode($data), 2);
    return openssl_decrypt($encryptedData, $method, $key, 0, $iv);
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
        // Speichern der LDAP-Einstellungen in der Datenbank, bevor der LDAP-Bind ausgeführt wird
        $encryptedLdapPass = encryptData($ldapPass, ENCRYPTION_KEY, ENCRYPTION_METHOD);

        // Überprüfen, ob bereits ein Eintrag existiert
        $stmt = $conn->prepare("SELECT COUNT(*) FROM ldap_settings");
        $stmt->execute();
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            // Eintrag aktualisieren
            $stmt = $conn->prepare("UPDATE ldap_settings SET ldap_host = ?, ldap_port = ?, ldap_user = ?, ldap_pass = ?, ldap_base_dn = ? WHERE id = (SELECT id FROM ldap_settings ORDER BY id DESC LIMIT 1)");
            $stmt->execute([$ldapHost, $ldapPort, $ldapUser, $encryptedLdapPass, $ldapBaseDN]);
        } else {
            // Neuer Eintrag erstellen
            $stmt = $conn->prepare("INSERT INTO ldap_settings (ldap_host, ldap_port, ldap_user, ldap_pass, ldap_base_dn) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$ldapHost, $ldapPort, $ldapUser, $encryptedLdapPass, $ldapBaseDN]);
        }

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
    $regelarbeitszeit = $_POST['regelarbeitszeit'];
    $ueberstunden = $_POST['ueberstunden'];

    // Eingaben validieren
    if (empty($username) || empty($password) || empty($email) || empty($role) || empty($department_id) || empty($regelarbeitszeit)) {
        $error = ERROR_ALL_FIELDS_REQUIRED;
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Statement vorbereiten und ausführen, um den neuen Benutzer einzufügen
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, department_id, supervisor_id, regelarbeitszeit, ueberstunden) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hashedPassword, $email, $role, $department_id, $supervisor, $regelarbeitszeit, $ueberstunden]);

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
    $regelarbeitszeit = $_POST['regelarbeitszeit'];
    $ueberstunden = $_POST['ueberstunden'];

    try {
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ?, department_id = ?, supervisor_id = ?, regelarbeitszeit = ?, ueberstunden = ? WHERE id = ?");
            $stmt->execute([$username, $email, $role, $hashedPassword, $department_id, $supervisor, $regelarbeitszeit, $ueberstunden, $user_id]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, department_id = ?, supervisor_id = ?, regelarbeitszeit = ?, ueberstunden = ? WHERE id = ?");
            $stmt->execute([$username, $email, $role, $department_id, $supervisor, $regelarbeitszeit, $ueberstunden, $user_id]);
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

// Suchparameter und Paginierung
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10; // Anzahl der Benutzer pro Seite

// Benutzer aus der Datenbank abrufen (mit Suche und Paginierung)
$stmt = $conn->prepare("
    SELECT users.*, departments.name as department_name, supervisors.username as supervisor_name
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
                        <span><?= $error ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($successMessage): ?>
                <div class="alert alert-success shadow-lg mb-4">
                    <div>
                        <i class="fas fa-check-circle"></i>
                        <span><?= $successMessage ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Benutzerverwaltung -->
            <div id="user-management" class="card bg-base-100 shadow-xl mb-8">
                <div class="card-body">
                    <h2 class="card-title text-2xl mb-4"><?= USER_MANAGEMENT_TITLE ?></h2>
                    <button class="btn btn-primary mb-4" id="toggleUserForm">
                        <i class="fas fa-plus mr-2"></i><?= BUTTON_CREATE_USER ?>
                    </button>
                    <div id="userForm" class="hidden mb-6">
                        <form method="post" class="space-y-4">
                            <input type="hidden" name="add_user" value="1">
                            <div class="form-control">
                                <label class="label" for="username">
                                    <span class="label-text"><?= FORM_USERNAME ?></span>
                                </label>
                                <input type="text" id="username" name="username" class="input input-bordered" required>
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
                                <input type="email" id="email" name="email" class="input input-bordered" required>
                            </div>
                            <div class="form-control">
                                <label class="label" for="role">
                                    <span class="label-text"><?= FORM_ROLE ?></span>
                                </label>
                                <select id="role" name="role" class="select select-bordered" required>
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
                                    <option value=""><?= FORM_NO_SUPERVISOR ?></option>
                                    <?php foreach ($users as $user): ?>
                                        <?php if ($user->role === 'supervisor' || $user->role === 'admin'): ?>
                                            <option value="<?= $user->id ?>"><?= htmlspecialchars($user->username) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-control">
                                <label class="label" for="regelarbeitszeit">
                                    <span class="label-text"><?= FORM_REGULAR_WORKING_HOURS ?></span>
                                </label>
                                <input type="number" id="regelarbeitszeit" name="regelarbeitszeit" class="input input-bordered" step="0.01" required>
                            </div>
                            <div class="form-control">
                                <label class="label" for="ueberstunden">
                                    <span class="label-text"><?= FORM_OVERTIME ?></span>
                                </label>
                                <input type="number" id="ueberstunden" name="ueberstunden" class="input input-bordered" step="0.01" value="0">
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
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user->username) ?></td>
                                        <td><?= htmlspecialchars($user->email) ?></td>
                                        <td><?= htmlspecialchars($user->role) ?></td>
                                        <td><?= htmlspecialchars($user->department_name) ?></td>
                                        <td><?= htmlspecialchars($user->supervisor_name) ?></td>
                                        <td><?= htmlspecialchars($user->regelarbeitszeit) ?></td>
                                        <td><?= htmlspecialchars($user->ueberstunden) ?></td>
                                        <td>
                                            <button class="btn btn-ghost btn-sm" onclick="editUser(<?= $user->id ?>)" title="<?= BUTTON_EDIT ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="post" class="inline" onsubmit="return confirm('<?= CONFIRM_DELETE_USER ?>')">
                                                <input type="hidden" name="delete_user" value="1">
                                                <input type="hidden" name="user_id" value="<?= $user->id ?>">
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
                                        <button class="btn btn-ghost btn-sm" onclick="editDepartment(<?= $department->id ?>, '<?= htmlspecialchars($department->name) ?>')" title="<?= BUTTON_EDIT ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="post" class="inline" onsubmit="return confirm('<?= CONFIRM_DELETE_DEPARTMENT ?>')">
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
        <h3 class="font-bold text-lg mb-4"><?= MODAL_TITLE_EDIT_USER ?></h3>
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
                <?php foreach ($users as $user): ?>
                    <?php if ($user->role === 'supervisor' || $user->role === 'admin'): ?>
                        <option value="<?= $user->id ?>"><?= htmlspecialchars($user->username) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-control mb-4">
            <label class="label" for="edit_regelarbeitszeit">
                <span class="label-text"><?= FORM_REGULAR_WORKING_HOURS ?></span>
            </label>
            <input type="number" id="edit_regelarbeitszeit" name="regelarbeitszeit" class="input input-bordered" step="0.01" required>
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
                <td><button type="button" class="btn btn-ghost btn-sm remove-row" title="${BUTTON_DELETE}"><i class="fas fa-trash-alt"></i></button></td>
            `;
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

    function editUser(userId) {
        const modal = document.getElementById('editUserModal');
        
        // AJAX-Aufruf, um die Benutzerdaten zu holen
        fetch(`get_user.php?id=${userId}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('edit_user_id').value = data.id;
                document.getElementById('edit_username').value = data.username;
                document.getElementById('edit_email').value = data.email;
                document.getElementById('edit_role').value = data.role;
                document.getElementById('edit_department').value = data.department_id;
                document.getElementById('edit_supervisor').value = data.supervisor_id || '';
                document.getElementById('edit_regelarbeitszeit').value = data.regelarbeitszeit;
                document.getElementById('edit_ueberstunden').value = data.ueberstunden;
                
                modal.showModal();
            })
            .catch(error => console.error('Error:', error));
    }

    function closeEditUserModal() {
        const modal = document.getElementById('editUserModal');
        modal.close();
    }

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




