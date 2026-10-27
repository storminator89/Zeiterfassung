<?php

namespace App\LegacyDatabase;

use Closure;
use LogicException;

final class AuditContext
{
    private ?array $current = null;

    public function run(string $actor, string $reason, Closure $operation): mixed
    {
        if ($actor === '' || $reason === '') {
            throw new LogicException('Explicit audit actor and reason are required.');
        }

        $previous = $this->current;
        $this->current = [$actor, $reason];

        try {
            return $operation();
        } finally {
            $this->current = $previous;
        }
    }

    public function actor(): string
    {
        return $this->current[0] ?? throw new LogicException('No active audit context.');
    }

    public function reason(): string
    {
        return $this->current[1] ?? throw new LogicException('No active audit context.');
    }
}
