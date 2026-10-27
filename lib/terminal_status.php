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
