<?php
require_once __DIR__ . '/config.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { abort_request(405, 'POST required.'); }
$file = $_FILES['dbFile'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 20 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) {
    abort_request(400, 'Upload a SQLite backup no larger than 20 MiB.');
}
// Never replace the live file with uploaded SQL schema/triggers. Only copy known data.
try {
    $source = new PDO('sqlite:' . $file['tmp_name'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $source->exec('PRAGMA query_only = ON');
    if ($source->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') { throw new RuntimeException('Invalid database'); }
    if ($source->query("SELECT COUNT(*) FROM sqlite_master WHERE type IN ('trigger','view')")->fetchColumn()) {
        throw new RuntimeException('Unsupported schema');
    }
    $tables = ['departments', 'users', 'zeiterfassung', 'Feiertage', 'ldap_settings', 'pause_settings'];
    $conn->beginTransaction();
    foreach (array_reverse($tables) as $table) { $conn->exec('DELETE FROM "' . $table . '"'); }
    foreach ($tables as $table) {
        $columns = array_column($conn->query('PRAGMA table_info("' . $table . '")')->fetchAll(PDO::FETCH_ASSOC), 'name');
        $names = implode(',', array_map(fn($column) => '"' . $column . '"', $columns));
        $rows = $source->query('SELECT ' . $names . ' FROM "' . $table . '"');
        $insert = $conn->prepare('INSERT INTO "' . $table . '" (' . $names . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')');
        while ($row = $rows->fetch(PDO::FETCH_NUM)) { $insert->execute($row); }
    }
    if (!$conn->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn()) {
        throw new RuntimeException('Backup needs an administrator');
    }
    $conn->commit();
    $_SESSION = [];
    session_destroy();
    header('Location: login.php?success=Backup+imported.+Please+sign+in+again.');
} catch (Throwable $error) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    error_log('Rejected database import: ' . $error->getMessage());
    abort_request(400, 'Invalid or incompatible backup. Current data was not changed.');
}
