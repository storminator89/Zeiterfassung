<?php

namespace App\Chrono;

use LogicException;

/** Ephemeral confirmed enrollment result, never session/flash/URL/audit/log data. */
final class TerminalDevicePairing
{
    public function __construct(
        public readonly int $deviceId,
        #[\SensitiveParameter] private ?string $code,
        public readonly int $expiresAt,
    ) {
        if ($deviceId < 1 || ! is_string($code) || preg_match('/\A[a-f0-9]{48}\z/', $code) !== 1 || $expiresAt < 1) {
            throw new LogicException('Invalid confirmed device enrollment result.');
        }
    }

    /** Consumed only by the authorized no-store response, at most once. */
    public function takeCode(): string
    {
        $code = $this->code;
        $this->code = null;
        if ($code === null || $this->expiresAt <= time()) {
            throw new LogicException('The one-time device enrollment display is unavailable.');
        }

        return $code;
    }

    public function __debugInfo(): array
    {
        return ['device_id' => $this->deviceId, 'code' => '[redacted]', 'expires_at' => $this->expiresAt];
    }

    private function __clone() {}

    public function __serialize(): array
    {
        throw new LogicException('Device enrollment codes must not be serialized.');
    }
}
