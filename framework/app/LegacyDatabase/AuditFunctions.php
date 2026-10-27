<?php

namespace App\LegacyDatabase;

use Closure;
use PDO;
use Pdo\Sqlite;
use RuntimeException;

final class AuditFunctions
{
    // Resolve the current scoped context at invocation, not once at connection creation.
    public function __construct(private readonly Closure $context) {}

    public function register(PDO $pdo): void
    {
        if (! $pdo instanceof Sqlite) {
            throw new RuntimeException('PHP 8.5 SQLite driver subclass is required.');
        }

        $actor = fn (): string => ($this->context)()->actor();
        $reason = fn (): string => ($this->context)()->reason();

        if (! $pdo->createFunction('audit_actor', $actor, 0)
            || ! $pdo->createFunction('audit_reason', $reason, 0)) {
            throw new RuntimeException('Audit function registration failed.');
        }
    }
}
