<?php

// Synthetic source fixture only: no config.php/security.php, file DB, credentials
// or copied business rules. The real legacy migration functions own v1/v2 semantics.
return static function (PDO $pdo, array $mapping, bool $withRows): void {
    App\LegacyDatabase\MemoryOnly::connection($pdo);

    if ((int) $pdo->query("SELECT count(*) FROM sqlite_schema WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchColumn() !== 0) {
        throw new RuntimeException('Source fixture requires an empty memory database.');
    }

    $tables = array_column($mapping['tables'], null, 'name');
    foreach (['users', 'zeiterfassung', 'Feiertage', 'departments', 'ldap_settings', 'pause_settings'] as $name) {
        $pdo->exec($tables[$name]['create_sql']);
    }
    $auth = $tables['users']['later_columns'][0];
    $pdo->exec('ALTER TABLE users ADD COLUMN '.$auth['name'].' '.$auth['declaration']);
    $pdo->exec('INSERT INTO pause_settings(hours_threshold,minimum_pause) VALUES(6,30),(9,45)');

    if ($withRows) {
        $pdo->exec("INSERT INTO users(id,username,password,email,role,regelarbeitszeit,ueberstunden) VALUES
            (1,'Synthetic member','not-a-usable-password-hash','member@example.invalid','user',4.5,7),
            (2,'Synthetic reviewer','not-a-usable-password-hash','reviewer@example.invalid','admin',8,0)");
        $pdo->exec("INSERT INTO zeiterfassung(id,startzeit,endzeit,pause,beschreibung,standort,user_id) VALUES
            (42,'2026-10-23 08:00:00','2026-10-23 13:00:00',30,'Synthetic old record','office',1),
            (43,'2026-10-22T08:00:00+02:00','2026-10-22T12:00:00+02:00',0,'Synthetic cancellation','office',1)");
    }

    require_once dirname(__DIR__, 3).'/lib/attendance.php';
    require_once dirname(__DIR__, 3).'/lib/practice.php';

    $previousActor = $GLOBALS['audit_actor'] ?? null;
    $previousReason = $GLOBALS['audit_reason'] ?? null;
    $GLOBALS['audit_actor'] = 'fixture:source-migration';
    $GLOBALS['audit_reason'] = 'Synthetic v2 source fixture';

    try {
        attendance_migrate($pdo);
        practice_migrate($pdo);
    } finally {
        if ($previousActor === null) {
            unset($GLOBALS['audit_actor']);
        } else {
            $GLOBALS['audit_actor'] = $previousActor;
        }
        if ($previousReason === null) {
            unset($GLOBALS['audit_reason']);
        } else {
            $GLOBALS['audit_reason'] = $previousReason;
        }
    }
};
