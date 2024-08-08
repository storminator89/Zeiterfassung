<?php
session_start();
include 'config.php';

// Überprüfen, ob der Benutzer eingeloggt und ein Admin ist
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Nicht autorisiert');
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    exit('Benutzer-ID nicht angegeben');
}

$userId = $_GET['id'];

try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        exit('Benutzer nicht gefunden');
    }

    // Passwort aus den Daten entfernen
    unset($user['password']);

    header('Content-Type: application/json');
    echo json_encode($user);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Datenbankfehler: ' . $e->getMessage());
}