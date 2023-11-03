<?php
include 'config.php';

// Überprüfen, ob ID gesendet wurde
if (isset($_POST['id'])) {
    $id = $_POST['id'];

    // Datensatz löschen
    $stmt = $conn->prepare('DELETE FROM zeiterfassung WHERE id = :id');
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    // Zur Hauptseite umleiten
    header("Location: index.php");
} else {
    die("ID nicht angegeben.");
}
?>
