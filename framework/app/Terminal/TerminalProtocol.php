<?php

namespace App\Terminal;

/**
 * Trusted, operation-scoped native-service boundary, not a protocol implementation.
 * No implementation or container binding is shipped by this transport prerequisite.
 */
interface TerminalProtocol
{
    /** Native module refusal must precede every method/body check, on the same scope. */
    public function requireEnabled(): void;

    /**
     * Verify the native device/grant protocol; never trust capsule fields as identity.
     * Preserve native nonce/limit ordering and the admitted single-PDO writer/audit
     * lifecycle. Do not call the raw-BEGIN terminal_handle() inside AttendanceWriter.
     * Native AttendanceError decisions may cross this boundary unchanged.
     */
    public function handle(LegacyTerminalRequest $request): array;
}
