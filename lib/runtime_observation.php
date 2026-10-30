<?php

require_once __DIR__.'/terminal_status.php';

/** Explicit local-Admin observations on the caller's admitted query-only snapshot. */
function admin_runtime_observation_snapshot(PDO $db, int $now, bool $includeTerminal): array
{
    if ($now < 1 || !$db->inTransaction() || (int)$db->query('PRAGMA query_only')->fetchColumn() !== 1) {
        throw new LogicException('Runtime observations require a query-only snapshot.');
    }

    // Only SQLite's already-open main database, never a request/configured backup path.
    // In-memory or unrecognizable storage remains unknown, not zero or sufficient.
    $statement = $db->query('PRAGMA database_list');
    try { $databases = $statement->fetchAll(PDO::FETCH_ASSOC); }
    finally { $statement->closeCursor(); }
    $available = null;
    foreach ($databases as $database) {
        if (($database['name'] ?? null) !== 'main') { continue; }
        $file = $database['file'] ?? null;
        if (is_string($file) && str_starts_with($file, '/') && !str_contains($file, "\0")) {
            try {
                $free = @disk_free_space(dirname($file));
                // A double rounded up to PHP_INT_MAX+1 must never wrap negative.
                if (is_float($free) && is_finite($free) && $free >= 0 && $free < (float)PHP_INT_MAX) {
                    $available = (int)$free;
                }
            } catch (Throwable) { /* Unknown observation; no private warning/path escapes. */ }
        }
        break;
    }
    $minimum = 134217728; // Same 128 MiB observation default as the private CLI, not an admission gate.
    $terminal = ['state' => 'not_selected'];
    if ($includeTerminal) {
        // Fixed bounded aggregate only: no IDs, names, locations, users or credentials.
        $statement = $db->query('SELECT last_seen FROM terminals WHERE enabled=1 ORDER BY id LIMIT 1001');
        try { $contacts = $statement->fetchAll(PDO::FETCH_COLUMN); }
        finally { $statement->closeCursor(); }
        $terminal = terminal_contact_observation($contacts, $now);
    }

    return ['checked_at_epoch' => $now,
        'storage' => ['state' => $available === null ? 'unknown' : ($available < $minimum ? 'low' : 'sufficient'),
            'available_bytes' => $available, 'minimum_bytes' => $minimum],
        'terminal_contact' => $terminal];
}
