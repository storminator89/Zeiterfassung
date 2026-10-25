<?php
require_once __DIR__ . '/config.php';
require_admin();
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="timetracking.sqlite"');
header('Cache-Control: no-store');
// Consistent snapshot even when other requests write concurrently.
$snapshot = tempnam(APP_DATA_DIR, 'backup-');
try {
    $conn->exec('VACUUM INTO ' . $conn->quote($snapshot));
    header('Content-Length: ' . filesize($snapshot));
    readfile($snapshot);
} finally {
    unlink($snapshot);
}
