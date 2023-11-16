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

    echo json_encode(["success" => true, "message" => "Entry created", "id" => $lastId]);
}

// weitere Funktionen für andere Aktionen hinzufügen
// ...

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

        //  Fälle für weitere Aktionen hinzufügen
        // ...

        default:
            echo json_encode(["success" => false, "message" => "invalid action parameter"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Only POST requests allowed"]);
}
?>
