<?php
// The agent waits 30 seconds between heartbeats (plus up to 8 seconds per request).
// This is a display-only freshness window, not a permission or availability check.
const TERMINAL_CONTACT_MAX_AGE = 90;

/** @return array{state: string, label: string, last_seen: ?int} */
function terminal_contact_status(mixed $lastSeen, int $now): array {
    if ($lastSeen === null || $lastSeen === '' || $lastSeen === 0 || $lastSeen === '0') {
        return ['state'=>'missing', 'label'=>'Noch kein Kontakt', 'last_seen'=>null];
    }
    if (!is_int($lastSeen) && (!is_string($lastSeen) || !ctype_digit($lastSeen))) {
        return ['state'=>'invalid', 'label'=>'Kontaktzeit ungültig', 'last_seen'=>null];
    }
    $timestamp = filter_var($lastSeen, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($timestamp === false || $timestamp > $now) {
        return ['state'=>'invalid', 'label'=>'Kontaktzeit ungültig', 'last_seen'=>null];
    }
    $recent = $now - $timestamp <= TERMINAL_CONTACT_MAX_AGE;
    return ['state'=>$recent?'recent':'stale', 'label'=>$recent?'Kontakt aktuell':'Kontakt veraltet', 'last_seen'=>$timestamp];
}


/** Same fixed bounded aggregate for private CLI and explicit Admin observations. No SQL or identities. */
function terminal_contact_observation(array $rows, int $now): array
{
    $counts = ['recent' => 0, 'stale' => 0, 'missing' => 0, 'invalid' => 0];
    $newestAge = $oldestAge = null;
    foreach (array_slice($rows, 0, 1000) as $lastSeen) {
        $contact = \terminal_contact_status($lastSeen, $now);
        $counts[$contact['state']]++;
        if ($contact['last_seen'] !== null) {
            // The existing status recognizer guarantees 1 <= last_seen <= now.
            // Missing/invalid/future values never become a zero-age observation.
            $age = $now - $contact['last_seen'];
            $newestAge = $newestAge === null ? $age : min($newestAge, $age);
            $oldestAge = $oldestAge === null ? $age : max($oldestAge, $age);
        }
    }
    $truncated = count($rows) > 1000;

    return ['state' => $truncated ? 'incomplete' : ($rows === [] ? 'no_active_devices'
        : ($counts['stale'] + $counts['missing'] + $counts['invalid'] > 0 ? 'degraded' : 'recent')),
        'scanned' => min(1000, count($rows)), 'counts' => $counts,
        'truncated' => $truncated, 'maximum_contact_age_seconds' => TERMINAL_CONTACT_MAX_AGE,
        'observed_age_seconds' => ['newest' => $newestAge, 'oldest' => $oldestAge]];
}
