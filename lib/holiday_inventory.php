<?php
/** Read-only inventory, not a completeness/region/source attestation. */
function holiday_inventory_year($requested, int $default): array
{
    if ($requested === null) {
        return ['year' => $default, 'invalid' => false];
    }
    if (!is_string($requested) || !preg_match('/^[1-9][0-9]{0,3}$/D', $requested)) {
        return ['year' => $default, 'invalid' => true];
    }
    return ['year' => (int)$requested, 'invalid' => false];
}

function holiday_inventory(PDO $db, int $year): array
{
    if ($year < 1 || $year > 9999) {
        throw new InvalidArgumentException('Invalid holiday inventory year.');
    }
    $prefix = sprintf('%04d', $year);
    // Prefix bounds include malformed dates within this year for inspection.
    // Limit the display; more than 366 rows is not silently presented as all.
    $statement = $db->prepare('SELECT datum,name FROM Feiertage WHERE datum>=? AND datum<? ORDER BY datum LIMIT 367');
    $statement->execute([$prefix . '-', $prefix . '.']);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement->closeCursor();
    $truncated = count($rows) > 366;
    $rows = array_slice($rows, 0, 366);
    $invalid = false;
    foreach ($rows as $row) {
        if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $row['datum'], $parts)
            || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) {
            $invalid = true;
        }
    }
    return ['year' => $year, 'rows' => $rows, 'truncated' => $truncated, 'invalid_dates' => $invalid];
}
