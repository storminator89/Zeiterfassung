<?php

namespace App\Api;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use stdClass;
use Throwable;

/** Maintained signature implementation with the actual legacy issuer profile. */
final class LegacyIssuedTokenVerifier
{
    public function verify(string $jwt, #[\SensitiveParameter] string $secret): array|false
    {
        if (strlen($jwt) > 4096 || count($parts = explode('.', $jwt)) !== 3) {
            return false;
        }

        try {
            // Package signature verification compares decoded bytes. The old
            // verifier required a canonical encoded signature, so retain that
            // wire-shape check using package codecs, not another HMAC engine.
            if (JWT::urlsafeB64Encode(JWT::urlsafeB64Decode($parts[2])) !== $parts[2]) {
                return false;
            }

            // Private/unexposed class, fixed defaults, no per-request writes.
            // Redeclaring BOTH fields isolates even another package consumer's
            // mutable parent clock/leeway. Neither instance nor class escapes.
            $decoder = new class extends JWT {
                public static $leeway = 30;
                public static $timestamp = null;
            };
            $header = new stdClass;
            $payload = (array) $decoder::decode($jwt, new Key($secret, 'HS256'), $header);
            $now = time();

            // Application claims/profile, not signature cryptography. Package
            // leeway also affects expiry; never grant that extra 30 seconds.
            if (($header->alg ?? null) !== 'HS256' || ($header->typ ?? null) !== 'JWT'
                || ! is_int($payload['exp'] ?? null) || $payload['exp'] <= $now
                || ! is_int($payload['iat'] ?? null) || $payload['iat'] > $now + 30
                || ($payload['iss'] ?? null) !== 'localhost' || ($payload['aud'] ?? null) !== 'localhost'
                || ! is_int($payload['user_id'] ?? null) || $payload['user_id'] < 1
                || ! is_string($payload['ver'] ?? null)) {
                return false;
            }

            return $payload;
        } catch (Throwable) {
            // Do not expose package exceptions, claims, signatures or key data.
            return false;
        }
    }
}
