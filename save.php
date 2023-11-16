<?php
include 'config.php';

// Check if it's an update request
if(isset($_POST["update"]) && $_POST["update"] == true) {
    $column = $_POST["column"];
    $data = $_POST["data"];
    $id = $_POST["id"];

    // Validate if the column is editable
    if (in_array($column, ["dauer", "id", "aktion"])) {
        echo "Invalid column";
        exit;
    }

    // Prepare and execute the update statement
    $stmt = $conn->prepare("UPDATE zeiterfassung SET $column = :data WHERE id = :id");
    $stmt->bindParam(':data', $data);
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    echo "Successfully updated";
    exit;
}

// Collect input data with default values
$startzeit_raw = $_POST["startzeit"];
$endzeit_raw = $_POST["endzeit"];
$beschreibung = $_POST["beschreibung"] ?? '';
$pause = $_POST["pause"] ?? 0;  // Assume default value for pause is 0
$standort = $_POST["standort"] ?? '';  // New code for location

// Convert times to ISO format
$startzeit_iso = date('Y-m-d\TH:i:s', strtotime($startzeit_raw));
$endzeit_iso = $endzeit_raw ? date('Y-m-d\TH:i:s', strtotime($endzeit_raw)) : NULL;

// Prepare and execute the insert statement
$stmt = $conn->prepare("INSERT INTO zeiterfassung (startzeit, endzeit, beschreibung, pause, standort) VALUES (:startzeit, :endzeit, :beschreibung, :pause, :standort)");
$stmt->bindParam(':startzeit', $startzeit_iso);
$stmt->bindParam(':endzeit', $endzeit_iso);
$stmt->bindParam(':beschreibung', $beschreibung);
$stmt->bindParam(':pause', $pause);
$stmt->bindParam(':standort', $standort);
$stmt->execute();

// Redirect to the index page
header("Location: index.php");
?>
