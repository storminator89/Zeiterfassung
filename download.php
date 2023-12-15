<?php
/**
 * Download timetracking SQLite database when accessed directly from web browser
 */

// Define the absolute path of the local SQLite database
$database = __DIR__ . '/assets/db/timetracking.sqlite';

// If the SQLite database exists...
if (file_exists($database)) {
    // Set appropriate headers for downloading a file
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header(
        'Content-Disposition: attachment; filename="' . basename($database) . '"'
    );
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($database));

    // Read and output the contents of the file to start the download process
    @readfile($database);

    // Stop executing after sending the response
    exit();
}
?>