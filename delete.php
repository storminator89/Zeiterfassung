<?php
include 'config.php';

// Überprüfen, ob ID gesendet wurde
if (isset($_POST['id'])) {
   $id = $_POST['id'];

   // Datensatz löschen
   $stmt = $conn->prepare('DELETE FROM zeiterfassung WHERE id = :id');
   $stmt->bindParam(':id', $id);
   
   if ($stmt->execute()) {
       $affectedRows = $stmt->rowCount();
       if ($affectedRows == 0) {
           die("Kein Datensatz mit der ID $id gefunden.");
       }
   } else {
       die("Fehler beim Löschen des Datensatzes: " . $stmt->errorInfo()[2]);
   }

   // Zur Hauptseite umleiten
   header("Location: index.php");
   exit;
} else {
   die("ID nicht angegeben.");
}
?>