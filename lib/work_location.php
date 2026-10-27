<?php

/** Resolve known stored codes/legacy labels without changing historical data. */
function work_location_code(?string $storedValue): ?string
{
    return match (mb_strtolower(trim($storedValue ?? ''), 'UTF-8')) {
        'office', 'büro', '辦公室' => 'office',
        'home_office', 'home office', '居家辦公' => 'home_office',
        'business_trip', 'business trip', 'dienstreise', '出差' => 'business_trip',
        default => null,
    };
}

/** Translate only at the display boundary, never when interpreting stored data. */
function work_location_label(?string $storedValue): string
{
    return match (work_location_code($storedValue)) {
        'office' => LOCATION_OFFICE,
        'home_office' => LOCATION_HOME_OFFICE,
        'business_trip' => LOCATION_BUSINESS_TRIP,
        default => 'Unbekannt',
    };
}
