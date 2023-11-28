<?php
$databasePath = __DIR__ . '/assets/db/timetracking.sqlite';

if (!empty($_FILES['dbFile']['name'])) {
    $tempFile = $_FILES['dbFile']['tmp_name'];
    $uploadFileName = $_FILES['dbFile']['name'];

    $fileExtension = pathinfo($uploadFileName, PATHINFO_EXTENSION);
    if ($fileExtension !== 'sqlite') {
        echo "no valid SQLite database";
        exit;
    }
    
    if (move_uploaded_file($tempFile, $databasePath)) {
        echo "Datenbank wurde erfolgreich importiert.";
    } else {
        echo "error in import.";
    }
} else { 
    echo "no file in upload";
}
?>
