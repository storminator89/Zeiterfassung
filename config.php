<?php

// Define the path to the SQLite database file
$database = __DIR__ . '/assets/db/timetracking.sqlite';

try {    
    // Create a new PDO instance for SQLite database connection
    $conn = new PDO("sqlite:$database");
    // Set the error mode to exception for better error handling
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // SQL to create the 'zeiterfassung' table if it doesn't exist
    $createZeiterfassungSql = "
    CREATE TABLE IF NOT EXISTS zeiterfassung (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        startzeit TEXT NOT NULL,
        endzeit TEXT NOT NULL,
        pause INTEGER NOT NULL,
        beschreibung TEXT,
        standort TEXT
    );";    
    // Execute the query to create 'zeiterfassung' table
    $conn->exec($createZeiterfassungSql);
    
    // SQL to create the 'Feiertage' table if it doesn't exist
    $createFeiertageSql = "
    CREATE TABLE IF NOT EXISTS Feiertage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        Datum TEXT NOT NULL
    );";
    // Execute the query to create 'Feiertage' table
    $conn->exec($createFeiertageSql);

} catch (PDOException $e) {
    // Handle any connection errors
    die("Can't connect to SQLite database: " . $e->getMessage());
}
