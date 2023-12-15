<?php
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
        // Display message if invalid SQLite format was not provided.
        echo "no valid SQLite database";
        // Immediately terminate the script.
        exit;
    }

    // Move the temporary file to its final destination.
    if (move_uploaded_file($tempFile, $databasePath)) {
        // Display success message for imported database.
        echo "Database successfully imported.";
    } else {
        // Display error message for failed import.
        echo "Error in import.";
    }
} else { 
    // Display message if no file is provided during submission.
    echo "No file in upload.";
}
?>