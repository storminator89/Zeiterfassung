<?php
require_once __DIR__.'/attendance.php';
require_once __DIR__.'/home_projection.php';
require_once __DIR__.'/csv_safety.php';

/**
 * Pure native CSV formatting, not an access gate or second report formula.
 * Caller supplies the complete own-user snapshot, checks absence conflicts
 * BEFORE calling, and creates no attachment response until all bytes exist.
 * No row filtering, extra queries, bootstrap, output, headers or global clock.
 */
function report_csv_document(array $exportRecords): string {
    $output = fopen('php://temp', 'w+');
    if ($output === false) {
        throw new RuntimeException('CSV materialization failed.');
    }
    try {
        if (fprintf($output, "\xEF\xBB\xBF") === false) {
            throw new RuntimeException('CSV materialization failed.');
        }

        // Add headers
        if (fputcsv($output, [
            'Datum',
            'Start',
            'Ende',
            'Pause (Min)',
            'Arbeitszeit / Gutschrift',
            'Standort',
            'Beschreibung',
            'Art',
            'Gutschrift (Min)'
        ], ';', '"', '\\') === false) {
            throw new RuntimeException('CSV materialization failed.');
        }

        // Same instant/ID order as the native list, without a page limit.
        foreach ($exportRecords as $record) {
            $startzeit = booking_local_date(booking_time($record['startzeit']));
            $endzeit = $record['endzeit'] ? booking_local_date(booking_time($record['endzeit'])) : null;

            if (fputcsv($output, [
                $startzeit->format('d.m.Y'),
                $record['absence_kind'] ? '' : $startzeit->format('H:i'),
                $record['absence_kind'] ? '' : ($endzeit ? $endzeit->format('H:i') : ''),
                csv_safe_text($record['pause'] ?: ''),
                calculateDuration($record['startzeit'], $record['endzeit'], $record['pause']),
                csv_safe_text($record['standort']),
                csv_safe_text($record['beschreibung']),
                csv_safe_text($record['absence_kind'] ?? 'Arbeitszeit'),
                $record['planned_minutes'] ?? ''
            ], ';', '"', '\\') === false) {
                throw new RuntimeException('CSV materialization failed.');
            }
        }

        if (!rewind($output)) {
            throw new RuntimeException('CSV materialization failed.');
        }
        $document = stream_get_contents($output);
        if ($document === false) {
            throw new RuntimeException('CSV materialization failed.');
        }
        return mb_convert_encoding($document, 'UTF-8', 'UTF-8');
    } finally {
        fclose($output);
    }
}
