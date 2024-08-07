<?php
session_start();

// Check if the user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// This PHP script handles the uploading of a SQLite database file.
// The target database path is defined.
$databasePath = __DIR__ . '/assets/db/timetracking.sqlite';

// Check if a file has been submitted via the upload form.
if (!empty($_FILES['dbFile']['name'])) {
    // Save temporary file information.
    $tempFile = $_FILES['dbFile']['tmp_name'];
    $uploadFileName = $_FILES['dbFile']['name'];

    // Retrieve the file extension of the uploaded file.
    $fileExtension = pathinfo($uploadFileName, PATHINFO_EXTENSION);

    // Verify that the file extension is sqlite.
    if ($fileExtension !== 'sqlite') {
        // Set error message and redirect
        $_SESSION['import_error'] = "No valid SQLite database provided.";
        header("Location: settings.php");
        exit;
    }

    // Move the temporary file to its final destination.
    if (move_uploaded_file($tempFile, $databasePath)) {
        // Set success message and redirect
        $_SESSION['import_success'] = "Database successfully imported.";
    } else {
        // Set error message and redirect
        $_SESSION['import_error'] = "Error in import.";
    }
} else { 
    // Set error message and redirect
    $_SESSION['import_error'] = "No file in upload.";
}

// Redirect back to settings.php
header("Location: settings.php");
exit;
?>