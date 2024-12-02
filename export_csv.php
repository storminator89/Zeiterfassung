<?php
require_once 'config.php';
require_once 'functions.php';

// Ensure user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    die('Not authorized');
}

try {
    // Set headers for CSV download with UTF-8 encoding
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Arbeitszeiterfassung_' . date('Y-m-d') . '.csv"');

    // Create output stream with UTF-8 encoding
    $output = fopen('php://temp', 'w+');
    fprintf($output, "\xEF\xBB\xBF"); // UTF-8 BOM

    // Add headers
    fputcsv($output, [
        'Datum',
        'Start',
        'Ende',
        'Pause (Min)',
        'Arbeitszeit',
        'Standort',
        'Beschreibung'
    ], ';');

    // Fetch records
    $stmt = $conn->prepare('
        SELECT 
            startzeit, 
            endzeit, 
            pause, 
            standort, 
            beschreibung 
        FROM zeiterfassung 
        WHERE user_id = :user_id 
        ORDER BY startzeit DESC
    ');
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    
    while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $startzeit = new DateTime($record['startzeit']);
        $endzeit = $record['endzeit'] ? new DateTime($record['endzeit']) : null;
        
        fputcsv($output, [
            $startzeit->format('d.m.Y'),
            $startzeit->format('H:i'),
            $endzeit ? $endzeit->format('H:i') : '',
            $record['pause'] ?: '',
            calculateDuration($record['startzeit'], $record['endzeit'], $record['pause']),
            $record['standort'],
            $record['beschreibung']
        ], ';');
    }

    // Output the file with UTF-8 encoding
    rewind($output);
    echo mb_convert_encoding(stream_get_contents($output), 'UTF-8', 'UTF-8');
    fclose($output);
    exit;

} catch (Exception $e) {
    die('Error exporting data: ' . $e->getMessage());
}