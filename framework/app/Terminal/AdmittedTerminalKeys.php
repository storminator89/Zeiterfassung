<?php

namespace App\Terminal;

use Closure;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Trusted external existing-root composition, not file-provenance verification.
 * Two separate zero-argument sources, no loader/default or resolved-value cache.
 * Source admission/lifetime belongs to the trusted operation composition; never
 * supply these callbacks, root values or source selection through request data.
 */
final readonly class AdmittedTerminalKeys
{
    public function __construct(
        #[\SensitiveParameter] private Closure $deviceSource,
        #[\SensitiveParameter] private Closure $cardSource,
    ) {}

    public function deviceSecret(): string
    {
        return $this->resolve($this->deviceSource);
    }

    public function cardSecret(): string
    {
        return $this->resolve($this->cardSource);
    }

    public function __debugInfo(): array
    {
        return ['source' => 'explicit existing terminal roots'];
    }

    public function __serialize(): array
    {
        throw new LogicException('Terminal key sources must not be serialized.');
    }

    private function resolve(#[\SensitiveParameter] Closure $source): string
    {
        try {
            $secret = $source();
            if (! is_string($secret) || ! preg_match('/^[a-f0-9]{64}$/D', $secret)) {
                throw new RuntimeException;
            }

            // Preserve the existing ASCII hex bytes; never decode or substitute.
            return $secret;
        } catch (Throwable) {
            // No source exception, previous error, path or key material escapes.
            throw new RuntimeException('Existing terminal key admission refused.');
        }
    }
}
