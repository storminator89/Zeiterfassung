<?php

namespace App\Accounts;

use InvalidArgumentException;

/** Deliberate target/state/generation-bound command; actor is supplied separately. */
final readonly class AccountLifecycleChange
{
    public function __construct(
        public int $target,
        public string $expectedStatus,
        public int $expectedGeneration,
        public string $nextStatus,
        public string $reason,
    ) {
        if ($target < 1
            || ! in_array($expectedStatus, ['active', 'inactive', 'departed'], true)
            || ! in_array($nextStatus, ['active', 'inactive', 'departed'], true)
            || $expectedStatus === $nextStatus
            || $expectedGeneration < 1 || $expectedGeneration === PHP_INT_MAX
            || trim($reason) !== $reason || $reason === '' || strlen($reason) > 500
            || preg_match('/[\x00-\x1f\x7f]/', $reason) !== 0) {
            throw new InvalidArgumentException('An explicit valid lifecycle transition and reason are required.');
        }
    }
}
