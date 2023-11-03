<?php
include 'config.php';


// Prüfen, ob es sich um eine Aktualisierung handelt
if(isset($_POST["update"]) && $_POST["update"] == true) {
    $column = $_POST["column"];
    $data = $_POST["data"];
    $id = $_POST["id"];

    if ($column == "dauer" || $column == "id" || $column == "aktion") {
        echo "Ungültige Spalte";
        exit;
    }
    

    $stmt = $conn->prepare("UPDATE zeiterfassung SET $column = :data WHERE id = :id");
    $stmt->bindParam(':data', $data);
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    echo "Erfolgreich aktualisiert";
    exit;
}

$startzeit_raw = $_POST["startzeit"];
$endzeit_raw = $_POST["endzeit"];
$beschreibung = isset($_POST["beschreibung"]) ? $_POST["beschreibung"] : '';
$pause = isset($_POST["pause"]) ? $_POST["pause"] : 0;  // Angenommen, der Standardwert für Pause ist 0.
$standort = isset($_POST["standort"]) ? $_POST["standort"] : '';  // Neuer Code für den Standort

$startzeit_iso = date('Y-m-d\TH:i:s', strtotime($startzeit_raw));
$endzeit_iso = $endzeit_raw ? date('Y-m-d\TH:i:s', strtotime($endzeit_raw)) : NULL;

$stmt = $conn->prepare("INSERT INTO zeiterfassung (startzeit, endzeit, beschreibung, pause, standort) VALUES (:startzeit, :endzeit, :beschreibung, :pause, :standort)");  // SQL-Anweisung aktualisiert
$stmt->bindParam(':startzeit', $startzeit_iso);
$stmt->bindParam(':endzeit', $endzeit_iso);
$stmt->bindParam(':beschreibung', $beschreibung);
$stmt->bindParam(':pause', $pause);
$stmt->bindParam(':standort', $standort);  // Binden Sie den Standort-Wert
$stmt->execute();


header("Location: index.php");

?>
