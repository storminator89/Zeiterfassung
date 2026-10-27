<?php

// One coarse old-issuer/profile compatibility proof, not an exhaustive JWT suite.
use App\Api\LegacyIssuedTokenVerifier;
use Firebase\JWT\JWT;

require dirname(__DIR__).'/vendor/autoload.php';
require_once dirname(__DIR__, 2).'/lib/api_tokens.php';
$ensure = static function (bool $condition): void {
    if (! $condition) {
        throw new RuntimeException('Coarse issued-token profile invariant failed.');
    }
};
$previous = [JWT::$leeway, JWT::$timestamp];

try {
    $ensure($argc === 1 && ! defined('CHRONO_SECURITY_LOADED'));
    $secret = bin2hex(random_bytes(32)); // Process-local only; no key IO.
    $profile = [
        'iss' => 'localhost', 'aud' => 'localhost', 'iat' => time(), 'exp' => time() + 3600,
        'user_id' => 1, 'ver' => hash('sha256', 'synthetic-password-version'),
    ];
    // Fixture issuer bytes match the existing api.php/admin.php issuer. The
    // maintained package is also able to sign them; runtime issuance is untouched.
    $issue = static function (array $payload, array $header = ['alg' => 'HS256', 'typ' => 'JWT']) use ($secret): string {
        $head = base64UrlEncode(json_encode($header));
        $body = base64UrlEncode(json_encode($payload));
        return $head.'.'.$body.'.'.base64UrlEncode(hash_hmac('SHA256', $head.'.'.$body, $secret, true));
    };
    $verifier = new LegacyIssuedTokenVerifier;
    foreach ([$issue($profile), $issue(['iat' => time() + 29] + $profile), JWT::encode($profile, $secret, 'HS256')] as $token) {
        $expected = validateJWT($token, $secret);
        $ensure($expected !== false && $expected === $verifier->verify($token, $secret));
    }
    $valid = $issue($profile);
    $parts = explode('.', $valid);
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
    $last = substr($parts[2], -1);
    $parts[2] = substr($parts[2], 0, -1).$alphabet[strpos($alphabet, $last) + 1];
    foreach ([
        $valid.'x', implode('.', $parts), $issue(['exp' => time() - 1] + $profile),
        $issue(['user_id' => '1'] + $profile), $issue($profile, ['alg' => 'HS512', 'typ' => 'JWT']),
        $issue($profile, ['alg' => 'HS256']),
    ] as $invalid) {
        $ensure(validateJWT($invalid, $secret) === false && $verifier->verify($invalid, $secret) === false);
    }

    // Isolated settings must not inherit other package consumers' mutable globals
    // or bleed into the next request. Only this proof mutates/restores the parent.
    JWT::$leeway = 3600;
    JWT::$timestamp = time() + 86400;
    $ensure($verifier->verify($valid, $secret) === $profile
        && $verifier->verify($issue(['exp' => time() - 1] + $profile), $secret) === false
        && JWT::$leeway === 3600 && JWT::$timestamp > time() + 80000);
    [JWT::$leeway, JWT::$timestamp] = $previous;
    $ensure($verifier->verify($valid, $secret) === $profile);

    // Known narrowing, NOT full arbitrary-signed-token equivalence. The actual
    // productive issuers never emit nbf; future-nbf tokens were ignored before.
    $notIssued = $issue(['nbf' => time() + 300] + $profile);
    $ensure(validateJWT($notIssued, $secret) !== false && $verifier->verify($notIssued, $secret) === false);
    $ensure(! defined('CHRONO_SECURITY_LOADED') && [JWT::$leeway, JWT::$timestamp] === $previous);
    echo json_encode([
        'passed' => true,
        'scope' => 'actual legacy-issued HS256 profile, not arbitrary signed-token equivalence',
        'legacy_and_package_issued_profile' => true,
        'strict_exp_iat_claims_and_canonical_signature' => true,
        'isolated_clock_and_leeway_no_parent_mutation' => true,
        'future_nbf_difference_explicit' => true,
        'persistent_keys_or_live_switch' => false,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL issued-token profile ('.$error::class.").\n");
    exit(1);
} finally {
    [JWT::$leeway, JWT::$timestamp] = $previous;
}
