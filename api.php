<?php
// Including configuration settings
include 'config.php';

// Creating a new instance of the PDO object to connect to SQLite database
$conn = new PDO("sqlite:$database");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Function to insert a new work entry into the time tracking table
function createNewWorkEntry($conn) {
    // Reading JSON input
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    // Parsing parameters
    $startzeit_raw = $input['startzeit'];
    $endzeit_raw = isset($input['endzeit']) ? $input['endzeit'] : null;
    $pause = $input['pause'];
    $beschreibung = $input['beschreibung'];
    $standort = $input['standort'];

    // Converting startzeit and endzeit to ISO datetime format
    $startzeit_iso = date('Y-m-d\TH:i:s', strtotime($startzeit_raw));
    $endzeit_iso = !is_null($endzeit_raw) ? date('Y-m-d\TH:i:s', strtotime($endzeit_raw)) : null;

    try {
        // Preparing statement for inserting records
        $stmt = $conn->prepare("INSERT INTO zeiterfassung (startzeit, endzeit, pause, beschreibung, standort) VALUES (:startzeit, :endzeit, :pause, :beschreibung, :standort)");
        
        // Binding variables to prevent SQL injection attacks
        $stmt->bindParam(':startzeit', $startzeit_iso);
        $stmt->bindParam(':endzeit', $endzeit_iso);
        $stmt->bindParam(':pause', $pause);
        $stmt->bindParam(':beschreibung', $beschreibung);
        $stmt->bindParam(':standort', $standort);

        // Executing query
        $stmt->execute();
        $lastId = $conn->lastInsertId();

        // Selecting inserted record
        $stmt = $conn->prepare("SELECT * FROM zeiterfassung WHERE id = :id");
        $stmt->bindParam(':id', $lastId, PDO::PARAM_INT);
        $stmt->execute();
        $newEntry = $stmt->fetch(PDO::FETCH_ASSOC);

        // Return result as JSON
        echo json_encode(['success' => true, 'message' => 'Eintrag erstellt', 'data' => $newEntry]);

    } catch (\Exception $exception) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
    }
}

// Function to update endzeit of specific row
function setEndzeit($conn, $input) {
    $id = $input['id'];
    $endzeit = date('Y-m-d\TH:i:s'); 

    try {
        // Preparing update statement
        $stmt = $conn->prepare("UPDATE zeiterfassung SET endzeit = :endzeit WHERE id = :id");
        $stmt->bindParam(':endzeit', $endzeit, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        // Executing query
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Endzeit aktualisiert', 'id' => $id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating endzeit']);
        }
    } catch (\Exception $exception) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
    }
}

// Switch case router logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    $action = isset($input['action']) ? $input['action'] : '';

    switch ($action) {
        case 'createNewWorkEntry':
            createNewWorkEntry($conn);
            break;
        case 'setEndzeit':
            setEndzeit($conn, $input);
            break;
        // More cases here
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
}