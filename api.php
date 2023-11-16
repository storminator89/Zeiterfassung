<?php
include 'config.php'; 

// PDO-Verbindung
$conn = new PDO("sqlite:$database");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Funktion zum Erstellen eines neuen Arbeitseintrags
function createNewWorkEntry($conn) {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    $startzeit_raw = $input['startzeit'];
    $endzeit_raw = $input['endzeit'];
    $pause = $input['pause'];
    $beschreibung = $input['beschreibung'];
    $standort = $input['standort'];

    $startzeit_iso = date('Y-m-d\TH:i:s', strtotime($startzeit_raw));
    $endzeit_iso = $endzeit_raw ? date('Y-m-d\TH:i:s', strtotime($endzeit_raw)) : NULL;

    $stmt = $conn->prepare("INSERT INTO zeiterfassung (startzeit, endzeit, pause, beschreibung, standort) VALUES (:startzeit, :endzeit, :pause, :beschreibung, :standort)");

    $stmt->bindParam(':startzeit', $startzeit_iso);
    $stmt->bindParam(':endzeit', $endzeit_iso);
    $stmt->bindParam(':pause', $pause);
    $stmt->bindParam(':beschreibung', $beschreibung);
    $stmt->bindParam(':standort', $standort);

    $stmt->execute();
    $lastId = $conn->lastInsertId();
    
    $stmt = $conn->prepare("SELECT * FROM zeiterfassung WHERE id = :id");
    $stmt->bindParam(':id', $lastId, PDO::PARAM_INT);
    $stmt->execute();
    $newEntry = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($newEntry) {
        echo json_encode(["success" => true, "message" => "Eintrag erstellt", "data" => $newEntry]);
    } else {
        echo json_encode(["success" => false, "message" => "Eintrag erstellt, aber Daten konnten nicht abgerufen werden"]);
    }
}

function setEndzeit($conn, $input) {
    $id = $input['id'];
    $endzeit = date('Y-m-d\TH:i:s'); 

    $stmt = $conn->prepare("UPDATE zeiterfassung SET endzeit = :endzeit WHERE id = :id");
    $stmt->bindParam(':endzeit', $endzeit, PDO::PARAM_STR);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Endzeit aktualisiert", "id" => $id]);
    } else {
        echo json_encode(["success" => false, "message" => "Fehler beim Aktualisieren der Endzeit"]);
    }
}

// Router-Logik
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Der 'action'-Parameter bestimmt die auszuführende Funktion
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'createNewWorkEntry':
            createNewWorkEntry($conn);
            break;
        case 'setEndzeit': // Neue Aktion für das Gehen-Event
            setEndzeit($conn, $input);
            break;
        // Weitere Fälle ...
        case 'getEntryById':
            $id = $input['id'] ?? 0;
            getEntryById($conn, $id);
            break;
        // Weitere Fälle ...
        default:
            echo json_encode(["success" => false, "message" => "Ungültige Aktion"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Only POST requests allowed"]);
}
?>
