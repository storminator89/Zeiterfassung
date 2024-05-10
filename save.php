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

// Insert starting timestamp and location upon receiving a post request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["startzeit"]) && isset($_POST["standort"])) {
    $startzeit_iso = date('Y-m-d H:i:s', strtotime($_POST["startzeit"]));
    $standort = $_POST["standort"];

    // Prepare and execute insert query
    $stmt = $conn->prepare("INSERT INTO zeiterfassung (startzeit, standort) VALUES (:startzeit, :standort)");
    $stmt->bindParam(':startzeit', $startzeit_iso);
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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["urlaubStart"]) && isset($_POST["urlaubEnde"]) && isset($_POST["ereignistyp"])) {
    $start = new DateTime($_POST["urlaubStart"]);
    $end = new DateTime($_POST["urlaubEnde"]);
    $end = $end->modify('+1 day');
    $ereignistyp = $_POST["ereignistyp"];

    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($start, $interval, $end);

    foreach ($daterange as $date) {
        $datum = $date->format("Y-m-d");
        $startzeit_iso = $datum . ' 09:00:00';
        $endzeit_iso = $datum . ' 17:00:00';
        $beschreibung = $ereignistyp;
        $pause = 0;
        $standort = '';

        $stmt = $conn->prepare("INSERT INTO zeiterfassung (startzeit, endzeit, beschreibung, pause, standort) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$startzeit_iso, $endzeit_iso, $beschreibung, $pause, $standort]);
    }

    echo "{$ereignistyp} von {$start->format('Y-m-d')} bis {$end->format('Y-m-d')} wurde eingetragen.";
} else {
    
}