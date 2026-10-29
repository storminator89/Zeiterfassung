<?php

namespace App\Terminal;

use App\LegacyDatabase\AttendanceWriter;
use Closure;

/** Concrete native-rule backend; one admitted writer for this operation scope. */
final readonly class LegacyTerminalProtocol implements TerminalProtocol
{
    public function __construct(
        private AttendanceWriter $writer,
        private AdmittedTerminalKeys $keys,
        private ?Closure $admission = null,
    ) {}

    public function requireEnabled(): void
    {
        ($this->admission ?? static fn () => null)();
        $this->writer->terminalRequireEnabled();
    }

    public function handle(LegacyTerminalRequest $request): array
    {
        ($this->admission ?? static fn () => null)();
        return $this->writer->terminal($request, $this->keys);
    }
}
