<?php
// Test database only, never point APP_DATA_DIR at production.
if (PHP_SAPI !== 'cli' || getenv('APP_TEST_MODE') !== '1') { exit(1); }
require __DIR__ . '/../config.php';
if ($conn->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) { exit('Refusing to seed a populated database.'); }
$insert = $conn->prepare('INSERT INTO users (username,password,email,role,supervisor_id) VALUES (?,?,?,?,?)');
foreach ([['auditadmin','admin',null],['audituser','user',1],['auditother','user',null]] as [$name,$role,$supervisor]) {
    $insert->execute([$name,password_hash('LocalAudit!Password2026',PASSWORD_DEFAULT),$name.'@example.invalid',$role,$supervisor]);
}
$conn->prepare('INSERT OR IGNORE INTO Feiertage(datum,name) VALUES (?,?)')->execute([date('Y').'-01-01','Test fixture']);
$conn->prepare('INSERT INTO zeiterfassung(startzeit,endzeit,pause,beschreibung,standort,user_id) VALUES (?,?,?,?,?,?)')->execute([date('Y').'-10-01 09:00:00',date('Y').'-10-01 17:00:00',30,'Test fixture','office',2]);
echo "Seeded three local test accounts.\n";
