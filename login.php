<?php
session_start();
include 'config.php';

$error = '';
$successMessage = '';

// Generate a CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_GET['error']) && $_GET['error'] == 'existinguser') {
    $error = "Registrierung ist nicht möglich, da bereits ein Nutzer existiert.";
}

if (isset($_GET['success'])) {
    $successMessage = "Registrierung erfolgreich! Bitte loggen Sie sich ein.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Ungültiges CSRF-Token.";
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        if (!empty($username) && !empty($password)) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_OBJ);

            if ($user && password_verify($password, $user->password)) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user->id;
                $_SESSION['username'] = $user->username;

                header("Location: index.php");
                exit();
            } else {
                $error = "Benutzername oder Passwort falsch!";
            }
        } else {
            $error = "Bitte alle Felder ausfüllen!";
        }
    }
}

// Function to check if the connection is secure
function isSecure() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
}

if (!isSecure()) {
    $error = "Verwenden Sie eine sichere HTTPS-Verbindung.";
}
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <!-- Meta tags and title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Quodara Chrono</title>

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
        <a class="navbar-brand" href="#">
            <img class="pl-3" src="assets/kolibri_icon_weiß.png" alt="Time Tracking" height="50">
        </a>
    </nav>

    <!-- Main content -->
    <div class="container mt-5 p-5">
        <h2>Login</h2>
        <form method="post" class="mt-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="mb-3">
                <label for="username" class="form-label">Benutzername</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Passwort</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
            <?php if ($successMessage) {
                echo "<div class='alert alert-success mt-3'>$successMessage</div>";
            } ?>
            <a href="register.php" class="btn btn-secondary">Registrieren</a>
            <?php if (!empty($error)) {
                echo "<div class='alert alert-danger mt-3'>$error</div>";
            } ?>
        </form>
    </div>

    <!-- Footer -->
    <footer class="footer mt-auto py-3">
        <div class="container">
            <span class="text-muted">© 2023 Quodara Chrono - Zeiterfassung</span>
        </div>
    </footer>
</body>

</html>
