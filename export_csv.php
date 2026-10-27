<?php
require_once 'config.php';
require_once 'functions.php';
require_once __DIR__ . '/lib/report_ordering.php';

// Ensure user is logged in
require_once __DIR__ . '/security.php';
if (!isset($_SESSION['user_id'])) {
    die('Not authorized');
}

try {
    // Materialize one complete own-user statement snapshot before headers. The
    // conflict guard and CSV must use the SAME effective rows, not functions.php
    // context followed by a later query that could see a newly completed shift.
    report_register_start_order($conn);
    $stmt = $conn->prepare('
        SELECT id, user_id, startzeit, endzeit, pause, standort,
            beschreibung, absence_kind, planned_minutes
        FROM zeiterfassung
        WHERE user_id = :user_id
        ORDER BY chrono_report_start_epoch(startzeit) DESC, id DESC
    ');
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $exportRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    if (report_absence_conflicts($exportRecords)!==[]) {
        http_response_code(409);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'CSV-Export nicht abschließend verfügbar: Arbeitszeit und ganztägige Abwesenheit überschneiden sich. Bitte zuerst über die Arbeitszeitliste und Korrekturanträge manuell klären. Es wurden keine Zeiten gelöscht oder gekürzt.';
        exit;
    }

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
        'Arbeitszeit / Gutschrift',
        'Standort',
        'Beschreibung',
        'Art',
        'Gutschrift (Min)'
    ], ';', '"', '\\');

    // Same instant/ID order as the native list, without a page limit.
    foreach ($exportRecords as $record) {
        $startzeit = booking_local_date(booking_time($record['startzeit']));
        $endzeit = $record['endzeit'] ? booking_local_date(booking_time($record['endzeit'])) : null;

        fputcsv($output, [
            $startzeit->format('d.m.Y'),
            $record['absence_kind'] ? '' : $startzeit->format('H:i'),
            $record['absence_kind'] ? '' : ($endzeit ? $endzeit->format('H:i') : ''),
            csv_safe($record['pause'] ?: ''),
            calculateDuration($record['startzeit'], $record['endzeit'], $record['pause']),
            csv_safe($record['standort']),
            csv_safe($record['beschreibung']),
            csv_safe($record['absence_kind'] ?? 'Arbeitszeit'),
            $record['planned_minutes'] ?? ''
        ], ';', '"', '\\');
    }

    // Output the file with UTF-8 encoding
    rewind($output);
    echo mb_convert_encoding(stream_get_contents($output), 'UTF-8', 'UTF-8');
    fclose($output);
    exit;

} catch (Exception $e) {
    die('Error exporting data: ' . $e->getMessage());
}
