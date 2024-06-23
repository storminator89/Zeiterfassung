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
<html lang="<?= $lang ?>">

<head>
    <!-- Meta tags and title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= LOGIN_TITLE ?></title>

    <!-- Favicon and external stylesheets -->
    <link rel="icon" href="assets/kolibri_icon.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" type="text/css" href="assets/css/main.css">

    <style>
        :root {
            --primary-color: #0366d6;
            --background-color: #ededed;
            --card-background: #ffffff;
            --text-color: #333333;
        }

        body {
            font-family: 'Source Sans Pro', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .login-container {
            background-color: var(--card-background);
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            transition: all 0.3s ease;
            margin: 1rem;
            box-sizing: border-box;
        }

        .login-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .login-header img {
            width: 80px;
            height: auto;
            margin-bottom: 1rem;
        }

        h1 {
            color: var(--primary-color);
            margin: 0;
            font-size: 1.75rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group i {
            position: absolute;
            left: 10px;
            top: 58%;
            color: var(--text-color);
            font-size: 1.2rem;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem 0.75rem 0.75rem 2.5rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
            margin-top: 0.5rem;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s ease;
            box-sizing: border-box;
            margin-top: 0.5rem;
        }

        button:hover {
            background-color: #0056b3;
        }

        .register-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s ease;
            box-sizing: border-box;
            margin-top: 1rem;
            text-align: center;
            display: block;
            text-decoration: none;
        }

        .register-button:hover {
            background-color: #0056b3;
        }

        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 0.9rem;
        }

        .social-login-buttons .btn {
            width: 100%;
            margin-bottom: 0.5rem;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-header">
            <img src="assets/kolibri_icon.png" alt="Quodara Chrono Logo">
            <h1><?= LOGIN_TITLE ?></h1>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group">
                <i class="fas fa-user"></i>
                <label for="username"><?= USERNAME_LABEL ?></label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <i class="fas fa-lock"></i>
                <label for="password"><?= PASSWORD_LABEL ?></label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit"><?= LOGIN_BUTTON ?></button>
        </form>
        
        <div class="social-login-buttons">
            <button class="btn btn-danger">
                <i class="fab fa-google"></i> Login via Google
            </button>
            <button class="btn btn-dark">
                <i class="fab fa-github"></i> Login via GitHub
            </button>
            <button class="btn btn-secondary">
                <i class="fab fa-apple"></i> Login via Apple
            </button>
        </div>

        <?php if ($totalUsers == 0) : ?>
            <a href="register.php" class="register-button"><?= REGISTER_BUTTON ?></a>
        <?php endif; ?>
        <div class="footer">
            © 2024 Quodara Chrono - Zeiterfassung
        </div>
    </div>

    <!-- Show modals if there are messages and redirect after close -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            <?php if ($successMessage) : ?>
                alert('<?= $successMessage ?>');
            <?php endif; ?>

            <?php if ($error) : ?>
                alert('<?= $error ?>');
            <?php endif; ?>
        });
    </script>
</body>

</html>