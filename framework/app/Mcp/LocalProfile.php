<?php

namespace App\Mcp;

/** One immutable local process/account/profile. Never serialize this object. */
final class LocalProfile
{
    private readonly string $token;
    public readonly string $origin;
    public readonly string $account;
    public readonly string $binding;

    public function __construct(array $config)
    {
        $origin = $config['origin'] ?? null;
        $deployment = $config['deployment'] ?? null;
        $account = $config['account_id'] ?? null;
        $file = $config['token_file'] ?? null;
        if (($config['enabled'] ?? false) !== true || ! is_string($origin)
            || ! preg_match('~\Ahttps://[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?(?::[1-9][0-9]{0,4})?\z~D', $origin)
            || ! is_string($deployment) || ! preg_match('/\A[A-Za-z0-9_-]{16,80}\z/D', $deployment)
            || ! self::id($account) || ! is_string($file) || ! str_starts_with($file, '/')) {
            throw new ApiFailure('configuration_refused');
        }
        // Reject FIFOs/devices before opening: fopen on a named pipe can block
        // indefinitely before the post-open regular-file check is reached.
        $before = @lstat($file);
        if (! self::privateFile($before)) { throw new ApiFailure('configuration_refused'); }
        $handle = @fopen($file, 'rb');
        if ($handle === false) { throw new ApiFailure('configuration_refused'); }
        try {
            $stat = fstat($handle);
            if (! self::privateFile($stat) || $stat['dev'] !== $before['dev'] || $stat['ino'] !== $before['ino']) {
                throw new ApiFailure('configuration_refused');
            }
            $token = stream_get_contents($handle, 8193);
        } finally { fclose($handle); }
        $token = is_string($token) ? rtrim($token, "\r\n") : '';
        if (! preg_match('/\A[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\z/D', $token)) {
            throw new ApiFailure('configuration_refused');
        }
        $this->origin = $origin;
        $this->account = $account;
        $this->token = $token;
        // Rotating credentials deliberately invalidates pending confirmations too.
        $this->binding = hash('sha256', json_encode([$origin, $deployment, $account, hash('sha256', $token)], JSON_THROW_ON_ERROR));
    }

    public static function id(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[1-9][0-9]{0,18}\z/D', $value)
            && (strlen($value) < 19 || strcmp($value, '9223372036854775807') <= 0);
    }

    private static function privateFile(array|false $stat): bool
    {
        return $stat !== false && ($stat['mode'] & 0170000) === 0100000 && ($stat['mode'] & 0077) === 0
            && $stat['size'] >= 1 && $stat['size'] <= 8192;
    }

    public function authorization(): string { return 'Bearer '.$this->token; }

    public function __debugInfo(): array { return ['private_local_profile' => true]; }
}
