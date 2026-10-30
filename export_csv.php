<?php
require_once 'config.php';
// Definitions only: export does not need dashboard queries or holiday loading.
require_once __DIR__ . '/lib/csv_export.php';
require_once __DIR__ . '/lib/csv_period.php';
require_once __DIR__ . '/lib/report_ordering.php';

// Ensure user is logged in
require_once __DIR__ . '/security.php';
if (!isset($_SESSION['user_id'])) {
    // Preserve the effective native redirect formerly performed by functions.php.
    header("Location: login.php");
    exit();
}

try {
    $csvPeriod = chrono_csv_export_period_from_query_string((string) ($_SERVER['QUERY_STRING'] ?? ''));
} catch (InvalidArgumentException) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Ungültiger CSV-Zeitraum. Bitte Von/Bis als gültige Berliner Daten (JJJJ-MM-TT) wählen; Von darf nicht nach Bis liegen.';
    exit;
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

    // Canonical diagnostics inspect the same complete source, never drop a row.
    $hasInvalidRecords = false;
    $invalidRecordExample = null;
    foreach ($exportRecords as $record) {
        if (report_record_calculation($record)['status'] === 'invalid') {
            $hasInvalidRecords = true;
            $invalidRecordExample ??= $record;
        }
    }
    if ($hasInvalidRecords) {
        http_response_code(409);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'CSV-Export nicht verfügbar: Mindestens ein Zeiteintrag ist ungültig. Bitte die Originalwerte in der eigenen Arbeitszeitliste prüfen und über Korrekturanträge klären. Es wurden keine Zeiten gelöscht oder gekürzt.' . report_csv_invalid_record_hint($invalidRecordExample);
        exit;
    }

    // Capture the filename date at its original point, before serialization.
    $filenameDate = date('Y-m-d');
    // Existing pure serializer closes its temporary stream even on failure.
    // Only complete bytes may become an attachment; no late streamed callback.
    $document = report_csv_document($csvPeriod === null ? $exportRecords
        : chrono_csv_export_period_select($exportRecords, $csvPeriod['from'], $csvPeriod['to']));
    $filename = $csvPeriod === null ? 'Arbeitszeiterfassung_' . $filenameDate . '.csv'
        : 'Arbeitszeiterfassung_' . $csvPeriod['from'] . '_' . $csvPeriod['to'] . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $document;
    exit;

} catch (Throwable) {
    // Do not expose SQL, paths, source values or a misleading successful CSV.
    http_response_code(500);
    if (function_exists('chrono_native_error_correlation')) { chrono_native_error_correlation(500); }
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'CSV-Export konnte nicht erstellt werden. Bitte erneut versuchen.';
    exit;
}
