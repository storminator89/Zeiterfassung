<?php

namespace App\Auth;

use App\LegacyDatabase\AttendanceWriter;
use Closure;
use RuntimeException;
use Throwable;

/** Unbound shared operation facade, not a guard, HTTP adapter or path opener. */
final readonly class WriterLoginBudget
{
    /** Trusted lazy factory: each call supplies a NEW admitted operation writer. */
    public function __construct(private Closure $writer) {}

    public function limited(string $username, string $remoteAddress): bool
    {
        try {
            $writer = ($this->writer)();
            if (! $writer instanceof AttendanceWriter) {
                throw new RuntimeException;
            }

            // The writer returns only after its single COMMIT and idle checks.
            return $writer->consumeLoginBudget($username, $remoteAddress);
        } catch (Throwable) {
            // Do not retain underlying SQL, private paths or chained diagnostics.
            throw new RuntimeException('Login budget commit was not confirmed.');
        }
    }
}
