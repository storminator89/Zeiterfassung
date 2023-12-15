<?php

// Path to the SQLite database file
$database = __DIR__ . '/assets/db/timetracking.sqlite';

// Connect to the SQLite database
try {
    // New PDO instance for SQLite database connection
    $conn = new PDO("sqlite:{$database}", null, null, [
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
        \PDO::ATTR_ERRMODE           => \PDO::ERRMODE_EXCEPTION
    ]);

    // Table creation queries
    $createZeiterfassungSql = <<<SQL
CREATE TABLE IF NOT EXISTS zeiterfassung (
    id          INTEGER      PRIMARY KEY AUTOINCREMENT,
    startzeit   TEXT         NOT NULL,
    endzeit     TEXT,
    pause       INTEGER,
    beschreibung TEXT         DEFAULT '' NULL,
    standort    TEXT         DEFAULT '' NULL
);
SQL;

    $createFeiertageSql = <<<SQL
CREATE TABLE IF NOT EXISTS Feiertage (
    id  INTEGER  PRIMARY KEY AUTOINCREMENT,
    datum TEXT UNIQUE NOT NULL
);
SQL;

    // Execution of table creation queries
    $conn->exec($createZeiterfassungSql);
    $conn->exec($createFeiertageSql);
} catch (\PDOException $e) {
    // Connection errors handling
    exit('Could not connect to the SQLite database: ' . $e->getMessage());
}
