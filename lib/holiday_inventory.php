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
    return ['year' => $year, 'rows' => $rows, 'truncated' => $truncated, 'invalid_dates' => $invalid]
        + holiday_inventory_stored_years($db);
}

/** Bounded discovery of stored YYYY- prefixes; never completeness or provenance. */
function holiday_inventory_stored_years(PDO $db): array
{
    // Counts include malformed dates within a recognized nonzero year prefix.
    // Other malformed prefixes are not claimed to be covered by this overview.
    $statement = $db->prepare("SELECT substr(datum,1,4) AS year_prefix,COUNT(*) AS row_count
        FROM Feiertage WHERE typeof(datum)='text'
        AND datum GLOB '[0-9][0-9][0-9][0-9]-*' AND substr(datum,1,4)<>'0000'
        GROUP BY substr(datum,1,4) ORDER BY year_prefix DESC LIMIT 21");
    $statement->execute();
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement->closeCursor();
    return [
        'stored_years' => array_map(static fn(array $row): array => [
            'year' => (int)$row['year_prefix'], 'count' => (int)$row['row_count'],
        ], array_slice($rows, 0, 20)),
        'stored_years_truncated' => count($rows)>20,
    ];
}
