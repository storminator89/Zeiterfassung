<?php
include 'config.php';
session_start();

$user_id = $_SESSION['user_id'];
$lang = $_SESSION['lang'] ?? 'de';
require_once "languages/$lang.php";

// Funktion, um die Mindestpausendauer basierend auf der Arbeitszeit abzurufen
function getPauseDuration($totalHours) {
    global $conn;
    $stmt = $conn->prepare("SELECT minimum_pause FROM pause_settings WHERE hours_threshold <= ? ORDER BY hours_threshold DESC LIMIT 1");
    $stmt->execute([$totalHours]);
    return $stmt->fetchColumn();
}

// Update request handler
if (isset($_POST["update"]) && $_POST["update"] == true) {
    $forbiddenColumns = ["dauer", "id", "aktion"];
    $column = $_POST["column"];
    $data = $_POST["data"];
    $id = $_POST["id"];

    if (in_array($column, $forbiddenColumns)) {
        echo "Invalid column";
        exit;
    }

    if ($column === 'endzeit' || $column === 'startzeit') {
        // Fetch the start time and end time from the database
        $stmt = $conn->prepare("SELECT startzeit, endzeit, pause FROM zeiterfassung WHERE id = :id AND user_id = :user_id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $times = $stmt->fetch(PDO::FETCH_ASSOC);

        $startzeit_raw = $times['startzeit'];
        $endzeit_raw = ($column === 'endzeit') ? $data : $times['endzeit'];
        $startzeit_raw = ($column === 'startzeit') ? $data : $times['startzeit'];
        $current_pause = $times['pause'];

        if ($startzeit_raw && $endzeit_raw) {
            $startzeit_iso = new DateTime($startzeit_raw);
            $endzeit_iso = new DateTime($endzeit_raw);

            // Calculate the total work duration
            $totalDuration = $endzeit_iso->diff($startzeit_iso);
            $totalHours = $totalDuration->h + ($totalDuration->i / 60);

            // Determine the pause duration if not set or below the minimum required pause
            if (!$current_pause || $current_pause < getPauseDuration($totalHours)) {
                $pause = getPauseDuration($totalHours);
                $stmt = $conn->prepare("UPDATE zeiterfassung SET $column = :data, pause = :pause WHERE id = :id AND user_id = :user_id");
                $stmt->bindParam(':pause', $pause);
            } else {
                $stmt = $conn->prepare("UPDATE zeiterfassung SET $column = :data WHERE id = :id AND user_id = :user_id");
            }
        } else {
            $stmt = $conn->prepare("UPDATE zeiterfassung SET $column = :data WHERE id = :id AND user_id = :user_id");
        }
    } else {
        $stmt = $conn->prepare("UPDATE zeiterfassung SET $column = :data WHERE id = :id AND user_id = :user_id");
    }

    $stmt->bindParam(':data', $data);
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    echo "Successfully updated";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["startzeit"]) && isset($_POST["standort"])) {
    $startzeit_iso = date('Y-m-d H:i:s', strtotime($_POST["startzeit"]));
    $standort = $_POST["standort"];

    $stmt = $conn->prepare("INSERT INTO zeiterfassung (startzeit, standort, user_id) VALUES (:startzeit, :standort, :user_id)");
    $stmt->bindParam(':startzeit', $startzeit_iso);
    $stmt->bindParam(':standort', $standort);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    echo $conn->lastInsertId();
}

if (isset($_POST["aktion"]) && $_POST["aktion"] === "gehen" && isset($_POST["id"])) {
    $id = $_POST["id"];
    $endzeit_raw = $_POST["endzeit"] ?? null;
    $beschreibung = $_POST["beschreibung"] ?? '';
    $standort = $_POST["standort"] ?? '';

    // Fetch the start time from the database
    $stmt = $conn->prepare("SELECT startzeit, pause FROM zeiterfassung WHERE id = :id AND user_id = :user_id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $startzeit_raw = $result['startzeit'];
    $current_pause = $result['pause'];

    if ($startzeit_raw) {
        $startzeit_iso = new DateTime($startzeit_raw);
        $endzeit_iso = new DateTime($endzeit_raw);

        // Calculate the total work duration
        $totalDuration = $endzeit_iso->diff($startzeit_iso);
        $totalHours = $totalDuration->h + ($totalDuration->i / 60);

        // Determine the pause duration if not set or below the minimum required pause
        if (!$current_pause || $current_pause < getPauseDuration($totalHours)) {
            $pause = getPauseDuration($totalHours);
        } else {
            $pause = $current_pause; // Keep the current pause if it meets the requirements
        }

        $stmt = $conn->prepare("UPDATE zeiterfassung SET endzeit = :endzeit, beschreibung = :beschreibung, pause = :pause, standort = :standort WHERE id = :id AND user_id = :user_id");
        $stmt->bindParam(':endzeit', $endzeit_iso->format('Y-m-d\TH:i:s'));
        $stmt->bindParam(':beschreibung', $beschreibung);
        $stmt->bindParam(':pause', $pause);
        $stmt->bindParam(':standort', $standort);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        header("Location: index.php");
        exit();
    } else {
        echo "Fehler: Startzeit nicht gefunden.";
    }
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

        $stmt = $conn->prepare("INSERT INTO zeiterfassung (startzeit, endzeit, beschreibung, pause, standort, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$startzeit_iso, $endzeit_iso, $beschreibung, $pause, $standort, $user_id]);
    }

    echo "{$ereignistyp} von {$start->format('Y-m-d')} bis {$end->format('Y-m-d')} wurde eingetragen.";
} else {
    // handle other cases
}
?>
