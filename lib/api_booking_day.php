<?php

// Definitions only: no bootstrap, connection, authorization or stored-data changes.
require_once __DIR__.'/reporting.php';

/** Same Berlin start-day assignment as reports, not SQLite's UTC date(). */
function api_booking_day(?string $start): ?string
{
    if ($start === null || $start === '') {
        return null;
    }
    try {
        return report_day(['startzeit' => $start]);
    } catch (Throwable $error) {
        // Unreadable legacy values do not acquire an invented day. SQL NULL
        // excludes them only when a date filter is present; no data is repaired.
        return null;
    }
}

function api_register_booking_day(PDO $db): void
{
    if (!$db->createFunction('chrono_api_booking_day', 'api_booking_day', 1)) {
        throw new RuntimeException('Could not register API booking-day filtering.');
    }
}
