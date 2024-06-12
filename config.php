<?php

$database = __DIR__ . '/assets/db/timetracking.sqlite';

try {
    $conn = new PDO("sqlite:{$database}", null, null, [
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
        \PDO::ATTR_ERRMODE           => \PDO::ERRMODE_EXCEPTION
    ]);

    // SQL for creating 'zeiterfassung' table
    $createZeiterfassungSql = <<<SQL
CREATE TABLE IF NOT EXISTS zeiterfassung (
    id          INTEGER      PRIMARY KEY AUTOINCREMENT,
    startzeit   TEXT         NOT NULL,
    endzeit     TEXT,
    pause       INTEGER,
    beschreibung TEXT         DEFAULT '' NULL,
    standort    TEXT         DEFAULT '' NULL,
    user_id     INTEGER      NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
SQL;

    // SQL for creating 'Feiertage' table
    $createFeiertageSql = <<<SQL
    CREATE TABLE IF NOT EXISTS Feiertage (
        id  INTEGER  PRIMARY KEY AUTOINCREMENT,
        datum TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL
    );
    SQL;

    // SQL for creating 'users' table
    $createUserSql = <<<SQL
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        role TEXT NOT NULL DEFAULT 'user',
        token TEXT,
        department_id INTEGER,
        supervisor_id INTEGER,
        regelarbeitszeit REAL DEFAULT 8.0,
        FOREIGN KEY (department_id) REFERENCES departments(id),
        FOREIGN KEY (supervisor_id) REFERENCES users(id)
    );
    SQL;

    // SQL for creating 'departments' table
    $createDepartmentsSql = <<<SQL
    CREATE TABLE IF NOT EXISTS departments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE
    );
    SQL;

    $createLdapSettingsSql = <<<SQL
    CREATE TABLE IF NOT EXISTS ldap_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ldap_host TEXT NOT NULL,
    ldap_port INTEGER NOT NULL,
    ldap_user TEXT NOT NULL,
    ldap_pass TEXT NOT NULL,
    ldap_base_dn TEXT NOT NULL
    );
    SQL;

    // Execute the SQL statements
    $conn->exec($createUserSql);
    $conn->exec($createZeiterfassungSql);
    $conn->exec($createFeiertageSql);
    $conn->exec($createDepartmentsSql);
    $conn->exec($createLdapSettingsSql);

    $result = $conn->query("PRAGMA table_info(users)")->fetchAll();
    $columns = array_column($result, 'name');
} catch (\PDOException $e) {
    exit('Could not connect to the SQLite database: ' . $e->getMessage());
}
