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

// Aktuellen Benutzer aus der Datenbank abrufen
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_OBJ);

// Benutzer hinzufügen
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $email = $_POST['email'];
    $role = $_POST['role'];

    // Eingaben validieren
    if (empty($username) || empty($password) || empty($email) || empty($role)) {
        $error = ERROR_ALL_FIELDS_REQUIRED;
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Statement vorbereiten und ausführen, um den neuen Benutzer einzufügen
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $hashedPassword, $email, $role]);

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

    try {
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?");
            $stmt->execute([$username, $email, $role, $hashedPassword, $user_id]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
            $stmt->execute([$username, $email, $role, $user_id]);
        }

        $successMessage = USER_UPDATED_SUCCESS;
    } catch (PDOException $e) {
        $error = USER_UPDATED_ERROR . $e->getMessage();
    }
}

// Alle Benutzer aus der Datenbank abrufen
$stmt = $conn->prepare("SELECT * FROM users");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_OBJ);
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
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus mr-1"></i> <?= BUTTON_CREATE_USER ?></button>
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
                        <td>
                            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editUserModal" data-userid="<?= $user->id ?>" data-username="<?= htmlspecialchars($user->username) ?>" data-email="<?= htmlspecialchars($user->email) ?>" data-role="<?= htmlspecialchars($user->role) ?>"><i class="fas fa-edit mr-1"></i> <?= BUTTON_EDIT ?></button>
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

                var modal = $(this);
                modal.find('#edit_user_id').val(userId);
                modal.find('#edit_username').val(username);
                modal.find('#edit_email').val(email);
                modal.find('#edit_role').val(role);
            });

            // Suchfunktion
            $("#searchInput").on("keyup", function() {
                var value = $(this).val().toLowerCase();
                $("#usersTable tbody tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
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
