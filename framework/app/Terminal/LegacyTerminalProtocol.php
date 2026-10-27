<?php

namespace App\Terminal;

use App\LegacyDatabase\AttendanceWriter;

/** Concrete native-rule backend; one admitted writer for this operation scope. */
final readonly class LegacyTerminalProtocol implements TerminalProtocol
{
    public function __construct(
        private AttendanceWriter $writer,
        private AdmittedTerminalKeys $keys,
    ) {}

    public function requireEnabled(): void
    {
        $this->writer->terminalRequireEnabled();
    }

    public function handle(LegacyTerminalRequest $request): array
    {
        return $this->writer->terminal($request, $this->keys);
    }
}
