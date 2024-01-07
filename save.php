<?php
// including the config file
include 'config.php';

// Update request handler
if (isset($_POST["update"]) && $_POST["update"] == true) {
    // Input validation to avoid editing reserved columns
    $forbiddenColumns = ["dauer", "id", "aktion"];
    $column = $_POST["column"];
    $data = $_POST["data"];
    $id = $_POST["id"];

    if (in_array($column, $forbiddenColumns)) {
        echo "Invalid column";
        exit;
    }

    // Prepare and bind params for the update query
    $stmt = $conn->prepare("UPDATE zeiterfassung SET $column = :data WHERE id = :id");
    $stmt->bindParam(':data', $data);
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    echo "Successfully updated";
    exit;
}

// Insert starting timestamp upon receiving a post request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["startzeit"])) {
    $startzeit_iso = date('Y-m-d\TH:i:s', strtotime($_POST["startzeit"]));

    // Prepare and execute insert query
    $stmt = $conn->prepare("INSERT INTO zeiterfassung (startzeit) VALUES (:startzeit)");
    $stmt->bindParam(':startzeit', $startzeit_iso);
    $stmt->execute();

    // Output the newly created entry Id
    echo $conn->lastInsertId();
}

// Insert a vacation day
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["urlaubstag"])) {
    $urlaubstag = $_POST["urlaubstag"];
    $startzeit_iso = $urlaubstag . ' 09:00:00';
    $endzeit_iso = $urlaubstag . ' 17:00:00';
    $beschreibung = 'Urlaub';
    $pause = '0';
    $standort = '';

    // Prepare and execute insert query
    $stmt = $conn->prepare("INSERT INTO zeiterfassung (startzeit, endzeit, beschreibung, pause, standort) VALUES (:startzeit, :endzeit, :beschreibung, :pause, :standort)");
    $stmt->bindParam(':startzeit', $startzeit_iso);
    $stmt->bindParam(':endzeit', $endzeit_iso);
    $stmt->bindParam(':beschreibung', $beschreibung);
    $stmt->bindParam(':pause', $pause);
    $stmt->bindParam(':standort', $standort);
    $stmt->execute();

    // Output the newly created entry Id
    echo $conn->lastInsertId();
}

// Insert a holiday
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["feiertag"])) {
    $feiertag = $_POST["feiertag"];
    $startzeit_iso = $feiertag . ' 09:00:00';
    $endzeit_iso = $feiertag . ' 17:00:00';
    $beschreibung = 'Feiertag';
    $pause = '0';
    $standort = '';

    // Prepare and execute insert query
    $stmt = $conn->prepare("INSERT INTO zeiterfassung (startzeit, endzeit, beschreibung, pause, standort) VALUES (:startzeit, :endzeit, :beschreibung, :pause, :standort)");
    $stmt->bindParam(':startzeit', $startzeit_iso);
    $stmt->bindParam(':endzeit', $endzeit_iso);
    $stmt->bindParam(':beschreibung', $beschreibung);
    $stmt->bindParam(':pause', $pause);
    $stmt->bindParam(':standort', $standort);
    $stmt->execute();

    // Output the newly created entry Id
    echo $conn->lastInsertId();
}


// Handling "go away" action with given id
if (isset($_POST["aktion"]) && $_POST["aktion"] === "gehen" && isset($_POST["id"])) {
    // Store incoming data with reasonable defaults
    $id = $_POST["id"];
    $startzeit_raw = $_POST["startzeit"];
    $endzeit_raw = $_POST["endzeit"] ?? null;
    $beschreibung = $_POST["beschreibung"] ?? '';
    $pause = $_POST["pause"] ?? 0;
    $standort = $_POST["standort"] ?? '';

    // Format received timestamps
    $startzeit_iso = date('Y-m-d\TH:i:s', strtotime($startzeit_raw));
    $endzeit_iso = $endzeit_raw ? date('Y-m-d\TH:i:s', strtotime($endzeit_raw)) : null;

    // Prepare and bind params for update query
    $stmt = $conn->prepare("UPDATE zeiterfassung SET endzeit = :endzeit, beschreibung = :beschreibung, pause = :pause, standort = :standort WHERE id = :id");
    $stmt->bindParam(':endzeit', $endzeit_iso);
    $stmt->bindParam(':beschreibung', $beschreibung);
    $stmt->bindParam(':pause', $pause);
    $stmt->bindParam(':standort', $standort);
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    // Perform redirect to index page
    header("Location: index.php");
}