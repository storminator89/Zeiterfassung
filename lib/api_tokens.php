<?php

// Temporary legacy compatibility definitions only. No bootstrap, IO, keys or sessions.
// Keep acceptance unchanged until a separately reviewed maintained-library migration.

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

function validateJWT($jwt, $secret) {
    if (!is_string($jwt) || strlen($jwt) > 4096) { return false; }
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) { return false; }
    [$head, $body, $signature] = $parts;
    $header = json_decode(base64_decode(strtr($head, '-_', '+/'), true) ?: '', true);
    $payload = json_decode(base64_decode(strtr($body, '-_', '+/'), true) ?: '', true);
    if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'HS256' || ($header['typ'] ?? '') !== 'JWT') { return false; }
    $expected = base64UrlEncode(hash_hmac('sha256', "$head.$body", $secret, true));
    if (!hash_equals($expected, $signature) || !is_int($payload['exp'] ?? null) || $payload['exp'] <= time()
        || !is_int($payload['iat'] ?? null) || $payload['iat'] > time() + 30
        || ($payload['iss'] ?? '') !== 'localhost' || ($payload['aud'] ?? '') !== 'localhost'
        || !is_int($payload['user_id'] ?? null) || $payload['user_id'] < 1 || !is_string($payload['ver'] ?? null)) {
        return false;
    }
    return $payload;
}
