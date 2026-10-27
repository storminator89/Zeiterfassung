<?php

namespace App\Terminal;

use Illuminate\Http\Request;

/** Untrusted transport data only; no authenticated device, account or grant. */
final readonly class LegacyTerminalRequest
{
    private function __construct(
        public string $body,
        public array $input,
        public string $method,
        public string $requestUri,
        public string $remoteAddress,
        public string $terminalId,
        public string $terminalTime,
        public string $terminalNonce,
        public string $terminalSignature,
    ) {}

    public static function capture(Request $request, string $body): ?self
    {
        $input = json_decode($body, true);
        if (! is_array($input)) {
            return null;
        }
        // Keep the raw server values used by terminal_api.php/lib/terminal.php.
        // No forwarded IP, method override, decoded path, framework-parsed body or re-encoding.
        // v1 signs ID/time/nonce/body only: method and URI are NOT signed fields.
        // Only this factory creates the capsule: input always decodes these bytes.
        return new self(
            $body, $input,
            (string) $request->server('REQUEST_METHOD', ''),
            (string) $request->server('REQUEST_URI', ''),
            (string) $request->server('REMOTE_ADDR', ''),
            (string) $request->server('HTTP_X_TERMINAL_ID', ''),
            (string) $request->server('HTTP_X_TERMINAL_TIME', ''),
            (string) $request->server('HTTP_X_TERMINAL_NONCE', ''),
            (string) $request->server('HTTP_X_TERMINAL_SIGNATURE', ''),
        );
    }
}
