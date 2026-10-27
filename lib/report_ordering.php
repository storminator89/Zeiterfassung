<?php

// Definitions only: callers keep their existing authorization and user filter.
require_once __DIR__.'/booking.php';

/** Sort stored timestamps by instant, independently of the Berlin display day. */
function report_start_epoch(string $value): ?int
{
    try {
        return booking_time($value);
    } catch (Throwable $error) {
        // Do not invent a time for unreadable legacy data or repair stored text.
        // NULL sorts last in DESC order; visible invalid-row handling is CH-11.
        return null;
    }
}

function report_register_start_order(PDO $db): void
{
    if (!$db->createFunction('chrono_report_start_epoch', 'report_start_epoch', 1)) {
        throw new RuntimeException('Could not register report timestamp ordering.');
    }
}
