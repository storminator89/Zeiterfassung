<?php
session_start();
include 'config.php';

// Supported languages
$supported_languages = ['de', 'en'];

// Detect browser language if not set
if (!isset($_SESSION['lang'])) {
    $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
    $_SESSION['lang'] = in_array($lang, $supported_languages) ? $lang : 'de';
}

// Allow manual language change
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = in_array($_GET['lang'], $supported_languages) ? $_GET['lang'] : $_SESSION['lang'];
}

$lang = $_SESSION['lang'];
require_once "languages/$lang.php";

$error = '';
$successMessage = '';

// Generate a CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_GET['error'])) {
    if ($_GET['error'] == 'existinguser') {
        $error = ERROR_EXISTING_USER;
    } else {
        $error = htmlspecialchars($_GET['error']);
    }
}

if (isset($_GET['success'])) {
    $successMessage = htmlspecialchars($_GET['success']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = ERROR_INVALID_CSRF;
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        if (!empty($username) && !empty($password)) {
            // LDAP-Verbindungsdetails aus der Datenbank abrufen
            $stmt = $conn->prepare("SELECT * FROM ldap_settings ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $ldapSettings = $stmt->fetch(PDO::FETCH_OBJ);

            if ($ldapSettings) {
                $ldapHost = $ldapSettings->ldap_host;
                $ldapPort = $ldapSettings->ldap_port;
                $ldapUser = $ldapSettings->ldap_user;
                $ldapPass = $ldapSettings->ldap_pass;
                $ldapBaseDN = $ldapSettings->ldap_base_dn;

                // Check LDAP first
                $ldapConn = ldap_connect($ldapHost, $ldapPort);

                if ($ldapConn) {
                    ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
                    $ldapRdn = "uid=$username,$ldapBaseDN";

                    if (@ldap_bind($ldapConn, $ldapRdn, $password)) {
                        // User authenticated via LDAP
                        // Fetch user details from LDAP and proceed
                        $search = ldap_search($ldapConn, $ldapBaseDN, "(uid=$username)");
                        $entries = ldap_get_entries($ldapConn, $search);

                        if ($entries["count"] > 0) {
                            $ldapUser = $entries[0];
                            $email = $ldapUser["mail"][0];

                            // Check if user exists in local database
                            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
                            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                            $stmt->execute();
                            $user = $stmt->fetch(PDO::FETCH_OBJ);

                            if (!$user) {
                                // If user doesn't exist locally, insert the user
                                $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (:username, :password, :email, 'user')");
                                $hashedPassword = password_hash($password, PASSWORD_DEFAULT); // Store a hashed password
                                $stmt->bindParam(':username', $username);
                                $stmt->bindParam(':password', $hashedPassword);
                                $stmt->bindParam(':email', $email);
                                $stmt->execute();

                                // Fetch the newly inserted user
                                $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
                                $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                                $stmt->execute();
                                $user = $stmt->fetch(PDO::FETCH_OBJ);
                            }

                            // Regenerate session ID to prevent session fixation
                            session_regenerate_id(true);

                            $_SESSION['user_id'] = $user->id;
                            $_SESSION['username'] = $user->username;
                            $_SESSION['role'] = $user->role; // Benutzerrolle speichern

                            header("Location: index.php");
                            exit();
                        }
                    }
                    ldap_close($ldapConn);
                }
            }

            // If LDAP authentication fails, check local database
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_OBJ);

            if ($user && password_verify($password, $user->password)) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user->id;
                $_SESSION['username'] = $user->username;
                $_SESSION['role'] = $user->role; // Benutzerrolle speichern

                header("Location: index.php");
                exit();
            } else {
                $error = ERROR_INVALID_CREDENTIALS;
            }
        } else {
            $error = ERROR_ALL_FIELDS_REQUIRED;
        }
    }
}

// Check if user registration is allowed
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
$stmt->execute();
$totalUsers = $stmt->fetch(PDO::FETCH_OBJ)->count;

?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= LOGIN_TITLE ?></title>
    <link rel="icon" href="assets/kolibri_icon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>

<body class="bg-gradient-to-r from-blue-100 to-blue-50 flex justify-center items-center min-h-screen p-4">
    <div class="card w-full max-w-md bg-white shadow-2xl">
        <div class="card-body">
            <div class="flex flex-col items-center mb-6">
                <img src="assets/kolibri_icon.png" alt="Quodara Chrono Logo" class="w-20 h-20 mb-2" />
                <h2 class="card-title text-2xl font-semibold text-gray-800"><?= LOGIN_TITLE ?></h2>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error shadow-lg mb-4">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span><?= $error ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($successMessage): ?>
                <div class="alert alert-success shadow-lg mb-4">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span><?= $successMessage ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-control">
                    <label class="label">
                        <span class="label-text text-gray-700"><?= USERNAME_LABEL ?></span>
                    </label>
                    <input type="text" name="username" placeholder="Enter your username" class="input input-bordered w-full bg-gray-50 focus:bg-white transition-colors duration-300" required />
                </div>
                
                <div class="form-control">
                    <label class="label">
                        <span class="label-text text-gray-700"><?= PASSWORD_LABEL ?></span>
                    </label>
                    <input type="password" name="password" placeholder="Enter your password" class="input input-bordered w-full bg-gray-50 focus:bg-white transition-colors duration-300" required />
                </div>
                
                <div class="form-control mt-6">
                    <button type="submit" class="btn btn-primary w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out transform hover:-translate-y-1 hover:shadow-lg">
                        <?= LOGIN_BUTTON ?>
                    </button>
                </div>
            </form>
            
            <?php if ($totalUsers == 0) : ?>
                <div class="text-center mt-4">
                    <a href="register.php" class="link link-primary text-sm hover:underline"><?= REGISTER_BUTTON ?></a>
                </div>
            <?php endif; ?>
        </div>
        <div class="text-center pb-6">
            <p class="text-xs text-gray-500">© 2024 Quodara Chrono - Zeiterfassung</p>
        </div>
    </div>
</body>

</html>