<?php
$database = __DIR__ . '/assets/db/timetracking.sqlite';

if (file_exists($database)) {    
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($database).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($database));
    readfile($database);
    exit;
}
?>
