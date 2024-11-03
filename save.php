<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("Nicht angemeldet");
}

$user_id = $_SESSION['user_id'];

// Funktion, um die Mindestpausendauer basierend auf der Arbeitszeit abzurufen
function getPauseDuration($totalHours)
{
    global $conn;
    $stmt = $conn->prepare("SELECT minimum_pause FROM pause_settings WHERE hours_threshold <= ? ORDER BY hours_threshold DESC LIMIT 1");
    $stmt->execute([$totalHours]);
    return $stmt->fetchColumn();
}

// Löschen eines Eintrags
if (isset($_POST['delete']) && $_POST['delete'] == 'true' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM zeiterfassung WHERE id = :id AND user_id = :user_id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo "Successfully deleted";
    } else {
        echo "Error deleting record";
    }
    exit;
}

// Aktualisieren eines Eintrags
if (isset($_POST['update']) && $_POST['update'] == 'true') {
    header('Content-Type: application/json');
    $id = $_POST['id'];
    $column = $_POST['column'];
    $value = $_POST['data'];

    $allowedColumns = ['startzeit', 'endzeit', 'pause', 'standort', 'beschreibung'];

    if (in_array($column, $allowedColumns)) {
        // Fetch existing record
        $stmt = $conn->prepare("SELECT startzeit, endzeit FROM zeiterfassung WHERE id = :id AND user_id = :user_id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            http_response_code(404);
            die("Datensatz nicht gefunden");
        }

        $new_startzeit = ($column === 'startzeit') ? $value : $record['startzeit'];
        $new_endzeit = ($column === 'endzeit') ? $value : $record['endzeit'];

        // Validate that endzeit is not before startzeit
        if ($new_startzeit && $new_endzeit) {
            $start = new DateTime($new_startzeit);
            $end = new DateTime($new_endzeit);
            if ($end < $start) {
                http_response_code(400);
                die("Endzeit darf nicht vor der Startzeit liegen.");
            }
        }

        if ($column === 'pause') {
            // Gesamtarbeitsstunden ermitteln
            $stmt_total = $conn->prepare("SELECT (julianday(endzeit) - julianday(startzeit)) * 24 AS total_hours FROM zeiterfassung WHERE id = :id AND user_id = :user_id");
            $stmt_total->execute([':id' => $id, ':user_id' => $user_id]);
            $totalHours = $stmt_total->fetchColumn();

            // Mindestpause bestimmen
            $minimumPause = getPauseDuration($totalHours);

            if ((int)$value < $minimumPause) {
                http_response_code(400);
                die("Die Pause muss mindestens $minimumPause Minuten betragen.");
            }
        }

        // Now proceed with the update
        $stmt = $conn->prepare("UPDATE zeiterfassung SET $column = :value WHERE id = :id AND user_id = :user_id");
        $stmt->bindParam(':value', $value);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $user_id);

        if ($stmt->execute()) {
            http_response_code(204);
            exit;
        } else {
            http_response_code(400);
            die("Fehler beim Aktualisieren der Daten");
        }
    } else {
        http_response_code(400);
        echo "Ungültige Spalte";
        exit;
    }
}

// Hinzufügen eines neuen Eintrags oder Beenden eines Eintrags
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"])) {
    $action = $_POST["action"];

    if ($action === 'start') {
        $startzeit_iso = date('Y-m-d H:i:s', strtotime($_POST["startzeit"]));
        $standort = $_POST["standort"];

        $stmt = $conn->prepare("INSERT INTO zeiterfassung (startzeit, standort, user_id) VALUES (:startzeit, :standort, :user_id)");
        $stmt->bindParam(':startzeit', $startzeit_iso);
        $stmt->bindParam(':standort', $standort);
        $stmt->bindParam(':user_id', $user_id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Arbeitszeit erfolgreich gestartet!";
        } else {
            $_SESSION['success_message'] = "Fehler beim Starten der Arbeitszeit.";
        }
    } elseif ($action === 'end') {
        $endzeit_iso = date('Y-m-d H:i:s', strtotime($_POST["endzeit"]));

        $stmt = $conn->prepare("SELECT id, startzeit FROM zeiterfassung WHERE user_id = :user_id AND endzeit IS NULL ORDER BY startzeit DESC LIMIT 1");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record) {
            $startzeit = new DateTime($record['startzeit']);
            $endzeit = new DateTime($endzeit_iso);
            $duration = $startzeit->diff($endzeit);
            $totalHours = $duration->h + ($duration->i / 60);
            $pause = getPauseDuration($totalHours);

            // Validierung: Endzeit darf nicht vor Startzeit liegen
            if ($endzeit < $startzeit) {
                $_SESSION['error_message'] = "Endzeit darf nicht vor der Startzeit liegen.";
                http_response_code(400);
                die("Endzeit darf nicht vor der Startzeit liegen.");
            }

            $stmt = $conn->prepare("UPDATE zeiterfassung SET endzeit = :endzeit, pause = :pause WHERE id = :id");
            $stmt->bindParam(':endzeit', $endzeit_iso);
            $stmt->bindParam(':pause', $pause);
            $stmt->bindParam(':id', $record['id']);

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Arbeitszeit erfolgreich beendet!";
            } else {
                $_SESSION['success_message'] = "Fehler beim Beenden der Arbeitszeit.";
            }
        } else {
            $_SESSION['success_message'] = "Kein offener Arbeitszeitentrageintrag gefunden.";
        }
    }

    header("Location: index.php");
    exit();
}


// Hinzufügen von Sondertagen (Urlaub, Feiertag, Krankheit)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["urlaubStart"]) && isset($_POST["urlaubEnde"]) && isset($_POST["ereignistyp"])) {
    $start = new DateTime($_POST["urlaubStart"]);
    $end = new DateTime($_POST["urlaubEnde"]);

    // Validierung: UrlaubEnde darf nicht vor UrlaubStart liegen
    if ($end < $start) {
        http_response_code(400);
        die("Enddatum des Urlaubs darf nicht vor dem Startdatum liegen.");
    }

    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($start, $interval, $end->modify('+1 day'));

    $eingetragene_tage = 0;

    foreach ($daterange as $date) {
        if ($date->format('N') >= 6) {
            continue;  // Skip Saturday (6) and Sunday (7)
        }
        $datum = $date->format("Y-m-d");
        $startzeit_iso = $datum . ' 09:00:00';
        $endzeit_iso = $datum . ' 17:00:00';
        $beschreibung = $ereignistyp;
        $pause = 0;
        $standort = '';

        $stmt = $conn->prepare("INSERT INTO zeiterfassung (startzeit, endzeit, beschreibung, pause, standort, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$startzeit_iso, $endzeit_iso, $beschreibung, $pause, $standort, $user_id]);
        $eingetragene_tage++;
    }

    echo "{$ereignistyp} von {$start->format('d.m.Y')} bis {$end->modify('-1 day')->format('d.m.Y')} wurde eingetragen. {$eingetragene_tage} Tage wurden erfasst.";
}
