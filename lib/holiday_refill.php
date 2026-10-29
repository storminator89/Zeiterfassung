<?php

// Definitions only: no source fetch, connection, bootstrap or transaction lifecycle.
/** Explicit operator-supplied BW response; structural validation is not source attestation. */
function chrono_holiday_refill_stage(mixed $year, mixed $region, mixed $json): array
{
    if (!is_string($year) || preg_match('/\A[1-9][0-9]{0,3}\z/', $year) !== 1
        || $region !== 'BW' || !is_string($json) || strlen($json) > 131072 || $json === '') {
        throw new InvalidArgumentException('Jahr, BW-Bestätigung und vollständige JSON-Antwort prüfen.');
    }
    try { $data = json_decode($json, false, 8, JSON_THROW_ON_ERROR); }
    catch (JsonException) { throw new InvalidArgumentException('Die Feiertagsantwort ist kein gültiges JSON-Objekt.'); }
    // JSON decoding alone loses duplicate/escaped-equivalent properties. Scan
    // strings as whole tokens so punctuation inside names/hints is never syntax.
    if (preg_match_all('/"(?:[^"\\\\]++|\\\\.)*+"|[{}:]/s', $json, $matches) === false) {
        throw new InvalidArgumentException('Die Feiertagsantwort konnte nicht sicher geprüft werden.');
    }
    $stack = [];
    foreach ($matches[0] as $i => $token) {
        if ($token === '{') { $stack[] = []; }
        elseif ($token === '}') { array_pop($stack); }
        elseif (str_starts_with($token, '"') && ($matches[0][$i + 1] ?? null) === ':') {
            $key = json_decode($token, true, 2, JSON_THROW_ON_ERROR);
            $n = count($stack) - 1;
            if ($n < 0 || array_key_exists($key, $stack[$n])) {
                throw new InvalidArgumentException('Doppelte JSON-Felder sind nicht zulässig.');
            }
            $stack[$n][$key] = true;
        }
    }
    if (!$data instanceof stdClass || count((array)$data) < 1 || count((array)$data) > 366) {
        throw new InvalidArgumentException('Eine nicht leere, begrenzte Feiertagsantwort wird benötigt.');
    }
    $rows = []; $conditional = 0; $prefix = sprintf('%04d', (int)$year);
    foreach ($data as $name => $value) {
        if (!is_string($name) || $name === '' || trim($name) !== $name || strlen($name) > 250
            || preg_match('/[\x00-\x1f\x7f]/', $name) === 1 || !$value instanceof stdClass
            || array_diff(array_keys((array)$value), ['datum', 'hinweis']) !== []
            || !property_exists($value, 'datum') || !is_string($value->hinweis ?? null)
            || strlen($value->hinweis) > 4000) {
            throw new InvalidArgumentException('Alle Feiertagsfelder müssen zum gewählten Jahr passen.');
        }
        // The upstream BW response also includes conditional, undated entries.
        // Only those may have null dates; they never become stored rows.
        if ($value->datum !== null || $value->hinweis === '') {
            if (!is_string($value->datum)
                || preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $value->datum, $parts) !== 1
                || $parts[1] !== $prefix || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) {
                throw new InvalidArgumentException('Alle Feiertagsdaten müssen zum gewählten Jahr passen.');
            }
        }
        // Preserve the historical statewide BW policy, not local exceptions.
        if ($value->hinweis !== '') { $conditional++; continue; }
        if (array_key_exists($value->datum, $rows)) {
            throw new InvalidArgumentException('Mehrere Feiertage am selben Datum erfordern manuelle Klärung.');
        }
        $rows[$value->datum] = $name;
    }
    if ($rows === []) { throw new InvalidArgumentException('Keine landesweiten Feiertagszeilen vorhanden.'); }
    ksort($rows, SORT_STRING);
    return ['year' => (int)$year, 'region' => 'BW', 'source' => 'operator_supplied_feiertage_api_json',
        'source_sha256' => hash('sha256', $json), 'rows' => $rows, 'conditional_omitted' => $conditional];
}

/**
 * Caller owns an authenticated IMMEDIATE transaction and audit context. Reparse
 * raw input here: no caller-supplied staged row or role can become write authority.
 * Refuse conflicting/extra existing rows before the first INSERT; never replace
 * originals, repair names, delete rows, or recalculate prior absence credits.
 */
function chrono_holiday_refill_locked(PDO $db, mixed $year, mixed $region, mixed $json): array
{
    if (!$db->inTransaction()) { throw new LogicException('Holiday refill requires a caller-owned transaction.'); }
    $stage = chrono_holiday_refill_stage($year, $region, $json);
    $prefix = sprintf('%04d', $stage['year']);
    $query = $db->prepare('SELECT datum,name FROM Feiertage WHERE datum>=? AND datum<? ORDER BY datum LIMIT 367');
    $query->execute([$prefix.'-', $prefix.'.']);
    $existing = $query->fetchAll(PDO::FETCH_ASSOC); $query->closeCursor();
    $missing = $stage['rows'];
    foreach ($existing as $row) {
        if (!is_string($row['datum']) || !array_key_exists($row['datum'], $missing)
            || $row['name'] !== $missing[$row['datum']]) {
            throw new DomainException('Bestandskonflikt: Jahr unverändert. Datum, Namen und Standort zuerst manuell klären.');
        }
        unset($missing[$row['datum']]);
    }
    $insert = $db->prepare('INSERT INTO Feiertage(datum,name) VALUES(?,?)');
    foreach ($missing as $day => $name) { $insert->execute([$day, $name]); }
    $insert->closeCursor();
    return ['year' => $stage['year'], 'region' => 'BW', 'inserted' => count($missing),
        'unchanged' => count($existing), 'conditional_omitted' => $stage['conditional_omitted'],
        'source_sha256' => $stage['source_sha256']];
}
