<?php
/** Pure DN/mapping helpers: no connection, credentials, role or account writes. */
function chrono_ldap_dn_key(string $dn, ?callable $explodeDn = null): ?string
{
    if ($dn === '' || str_contains($dn, "\0")) { return null; }
    // Let the LDAP implementation validate/split DNs, retaining attribute names.
    if ($explodeDn === null && !function_exists('ldap_explode_dn')) { return null; }
    $explodeDn ??= static fn(string $value, int $attributes) => @ldap_explode_dn($value, $attributes);
    try { $parts = $explodeDn($dn, 0); }
    catch (ValueError $e) { return null; }
    if (!is_array($parts) || !isset($parts['count']) || !is_int($parts['count']) || $parts['count'] < 1) { return null; }
    $rdns = [];
    for ($i = 0; $i < $parts['count']; $i++) {
        if (!isset($parts[$i]) || !is_string($parts[$i])) { return null; }
        // The parser returns escaped RDNs. Split only unescaped multi-value '+';
        // never split the original DN on commas or infer an identity from a CN.
        $avas = []; $start = 0; $rdn = $parts[$i]; $length = strlen($rdn);
        for ($offset = 0; $offset <= $length; $offset++) {
            if ($offset < $length && $rdn[$offset] === '\\') {
                if ($offset + 1 >= $length) { return null; }
                $offset++; continue;
            }
            if ($offset < $length && $rdn[$offset] !== '+') { continue; }
            $ava = substr($rdn, $start, $offset - $start); $start = $offset + 1;
            $equals = strpos($ava, '=');
            if ($equals === false || $equals === 0) { return null; }
            $attribute = strtolower(substr($ava, 0, $equals));
            $value = substr($ava, $equals + 1);
            if (str_starts_with($value, '#')) {
                // BER values are opaque: do not equate them with guessed text.
                if (!preg_match('/\A#(?:[0-9a-fA-F]{2})+\z/', $value)) { return null; }
                $avas[] = [$attribute, 'ber', strtolower($value)];
                continue;
            }
            $decoded = '';
            for ($j = 0; $j < strlen($value); $j++) {
                if ($value[$j] !== '\\') { $decoded .= $value[$j]; continue; }
                if (++$j >= strlen($value)) { return null; }
                if ($j + 1 < strlen($value) && ctype_xdigit($value[$j] . $value[$j + 1])) {
                    $decoded .= chr(hexdec(substr($value, $j, 2))); $j++;
                } elseif (str_contains(' ,+"\\<>;=#', $value[$j])) {
                    $decoded .= $value[$j];
                } else { return null; }
            }
            // Preserve value case/bytes; schema-dependent matching is not guessed.
            $avas[] = [$attribute, 'text', bin2hex($decoded)];
        }
        sort($avas);
        $rdns[] = $avas;
    }
    return json_encode($rdns, JSON_THROW_ON_ERROR);
}

/**
 * Plan only supervisor_id changes after all eligible identities have IDs.
 * Conflicts produce no assignment, so their existing relationship is retained.
 * @param array $identities Rows with id, dn and manager_dn from the same import.
 * @param array $currentSupervisors Current user ID => nullable supervisor ID map.
 */
function chrono_ldap_manager_plan(array $identities, array $currentSupervisors, ?callable $explodeDn = null): array
{
    $byDn = []; $byId = []; $conflicts = []; $assignments = [];
    foreach ($identities as $identity) {
        $id = (int)$identity['id'];
        $key = chrono_ldap_dn_key($identity['dn'], $explodeDn);
        $byId[$id][] = $identity;
        if ($key !== null) { $byDn[$key][$id] = true; }
    }
    foreach ($byId as $id => $rows) {
        if (count($rows) !== 1) { $conflicts[$id] = 'ambiguous_identity'; continue; }
        $managerDn = $rows[0]['manager_dn'];
        $identityKey = chrono_ldap_dn_key($rows[0]['dn'], $explodeDn);
        if ($identityKey === null) {
            $conflicts[$id] = 'invalid_identity_dn'; continue;
        }
        if (count($byDn[$identityKey]) !== 1) { $conflicts[$id] = 'ambiguous_identity'; continue; }
        if ($managerDn === '') { $assignments[$id] = null; continue; }
        $managerKey = chrono_ldap_dn_key($managerDn, $explodeDn);
        if ($managerKey === null) { $conflicts[$id] = 'invalid_manager_dn'; continue; }
        $matches = array_keys($byDn[$managerKey] ?? []);
        if (!$matches) { $conflicts[$id] = 'unknown_manager'; continue; }
        if (count($matches) !== 1 || count($byId[$matches[0]]) !== 1) {
            $conflicts[$id] = 'ambiguous_manager'; continue;
        }
        if ($matches[0] === $id) { $conflicts[$id] = 'self_manager'; continue; }
        $assignments[$id] = $matches[0];
    }
    // Validate the resulting graph, including preserved existing relationships.
    // Removing a conflicting proposal restores its old edge; recheck until stable.
    do {
        $graph = array_replace($currentSupervisors, $assignments); $cyclic = [];
        foreach ($assignments as $id => $manager) {
            $seen = []; $cursor = $id;
            while ($cursor !== null && array_key_exists($cursor, $graph)) {
                if (isset($seen[$cursor])) { $cyclic[] = $id; break; }
                $seen[$cursor] = true; $cursor = $graph[$cursor];
            }
        }
        foreach ($cyclic as $id) { unset($assignments[$id]); $conflicts[$id] = 'cyclic_manager'; }
    } while ($cyclic);
    ksort($assignments); ksort($conflicts);
    return ['assignments' => $assignments, 'conflicts' => $conflicts];
}
