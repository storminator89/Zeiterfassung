<?php

$database = __DIR__ . '/timetracking.sqlite';

try {    
    $conn = new PDO("sqlite:$database");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $createZeiterfassungSql = "
    CREATE TABLE IF NOT EXISTS zeiterfassung (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        startzeit TEXT NOT NULL,
        endzeit TEXT NOT NULL,
        pause INTEGER NOT NULL,
        beschreibung TEXT,
        standort TEXT
    );
";    
    $conn->exec($createZeiterfassungSql);
    
    $createFeiertageSql = "
    CREATE TABLE IF NOT EXISTS Feiertage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        Datum TEXT NOT NULL
    );
    ";

    // Tabelle Feiertage erstellen
    $conn->exec($createFeiertageSql);
} catch (PDOException $e) {
    die("Can't connect to SQLite database: " . $e->getMessage());
}
