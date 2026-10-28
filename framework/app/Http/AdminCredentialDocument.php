<?php

namespace App\Http;

use InvalidArgumentException;

/** Fixed local Admin credential-document policy; no auth/profile or key admission. */
final class AdminCredentialDocument
{
    public static function nonce(): string
    {
        return base64_encode(random_bytes(18));
    }

    public static function headers(string $nonce): array
    {
        if (preg_match('~\A[A-Za-z0-9+/]{24}\z~', $nonce) !== 1) {
            throw new InvalidArgumentException('Invalid Admin document nonce.');
        }

        return ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'none'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self'; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'"];
    }
}
