<?php

// Definitions only. No source fetch, secret decryption, bootstrap or schema/UDF setup.
/** A safe endpoint projection, never userinfo, query, path or multiple LDAP URIs. */
function chrono_ldap_settings_audit_endpoint(mixed $uri): ?string
{
    if (!is_string($uri) || $uri === '' || strlen($uri) > 512 || preg_match('/[\x00-\x20\x7f]/', $uri)) { return null; }
    $candidate = str_contains($uri, '://') ? $uri : 'ldap://'.$uri;
    try { $parts = parse_url($candidate); } catch (Throwable) { return null; }
    if (!is_array($parts) || array_diff(array_keys($parts), ['scheme', 'host', 'port']) !== []
        || !in_array($parts['scheme'] ?? null, ['ldap', 'ldaps'], true) || !is_string($parts['host'] ?? null)) { return null; }
    $host = $parts['host'];
    $validHost = str_starts_with($host, '[') && str_ends_with($host, ']')
        ? filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
        : filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    if (!$validHost || (isset($parts['port']) && ($parts['port'] < 1 || $parts['port'] > 65535))) { return null; }
    return $parts['scheme'].'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
}

/** Only safe values/booleans leave SQL; bind identity, ciphertext and raw BaseDN never selected. */
function chrono_ldap_settings_audit_read(PDO $db, #[\SensitiveParameter] string $baseDn, #[\SensitiveParameter] string $bindUser): ?array
{
    $statement = $db->prepare("SELECT id,ldap_host,ldap_port,
        CASE WHEN ldap_base_dn<>'' THEN 1 ELSE 0 END AS base_dn_present,
        CASE WHEN ldap_user<>'' THEN 1 ELSE 0 END AS bind_identity_present,
        CASE WHEN ldap_pass<>'' THEN 1 ELSE 0 END AS credential_present,
        CASE WHEN ldap_base_dn=? THEN 0 ELSE 1 END AS base_dn_changed,
        CASE WHEN ldap_user=? THEN 0 ELSE 1 END AS bind_identity_changed
        FROM ldap_settings ORDER BY id DESC LIMIT 1");
    $statement->execute([$baseDn, $bindUser]);
    $row = $statement->fetch(PDO::FETCH_ASSOC); $statement->closeCursor();
    if ($row === false) { return null; }
    $endpoint = chrono_ldap_settings_audit_endpoint($row['ldap_host']);
    $port = (is_int($row['ldap_port']) || is_string($row['ldap_port'])) && preg_match('/\A[0-9]{1,5}\z/', (string)$row['ldap_port']) === 1
        && (int)$row['ldap_port'] >= 1 && (int)$row['ldap_port'] <= 65535 ? (int)$row['ldap_port'] : null;
    // PDO returns INTEGER PRIMARY KEY exactly on supported 64-bit PHP/SQLite.
    if (!is_int($row['id'])) { throw new UnexpectedValueException('LDAP settings audit ID unavailable.'); }
    $snapshot = ['id'=>$row['id'], 'endpoint'=>$endpoint, 'endpoint_redacted'=>$endpoint === null,
        'port'=>$port, 'port_redacted'=>$port === null, 'base_dn_present'=>(bool)$row['base_dn_present'],
        'bind_identity_present'=>(bool)$row['bind_identity_present'], 'credential_present'=>(bool)$row['credential_present']];
    return ['snapshot'=>$snapshot, 'base_dn_changed'=>(bool)$row['base_dn_changed'], 'bind_identity_changed'=>(bool)$row['bind_identity_changed']];
}

/** One installation-wide journal row in the caller's existing import transaction. */
function chrono_ldap_settings_audit_append_locked(PDO $db, ?array $before, array $after): void
{
    if (!$db->inTransaction()) { throw new LogicException('LDAP settings journal requires the caller-owned transaction.'); }
    $right = $after['snapshot'];
    $right['base_dn_changed'] = $before === null ? null : $before['base_dn_changed'];
    $right['bind_identity_changed'] = $before === null ? null : $before['bind_identity_changed'];
    // The import always supplies a newly encrypted credential. This does NOT
    // assert that its plaintext differs from before, and no hash is retained.
    $right['credential_supplied'] = true;
    $statement = $db->prepare("INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,before_json,after_json)
        VALUES(unixepoch(),audit_actor(),audit_reason(),'ldap_settings',?,0,?,?,?)");
    $statement->execute([$right['id'], $before === null ? 'INSERT' : 'UPDATE',
        $before === null ? null : json_encode($before['snapshot'], JSON_THROW_ON_ERROR), json_encode($right, JSON_THROW_ON_ERROR)]);
    $statement->closeCursor();
}
