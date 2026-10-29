<?php

namespace App\Api\Management;

use App\LegacyDatabase\RuntimeDatabase;
use Illuminate\Contracts\Config\Repository;
use LogicException;
use PDO;

/** A fresh admitted read-only snapshot per check; no cached effective-enabled bit. */
final readonly class ApiPolicyReadSnapshot
{
    public function __construct(private RuntimeDatabase $runtime, private Repository $config) {}

    public function read(): ApiPolicyState
    {
        if (! $this->runtime->isAccountLifecycleV5()) {
            throw new LogicException('API UI operating policy requires the explicit V5 profile.');
        }
        $reader = $pdo = null;
        try {
            $reader = $this->runtime->readConnection($this->config->get('database.connections.chrono', []), 'chrono');
            $pdo = $reader->getPdo();
            if (! $pdo->beginTransaction()) { throw new LogicException('API policy snapshot unavailable.'); }
            $state = (new ApiPolicyJournal)->read($pdo, $this->config->get('chrono_api.enabled', false) === true);
            if (! $pdo->rollBack() || $pdo->inTransaction()) { throw new LogicException('API policy snapshot not ended.'); }
            return $state;
        } finally {
            try { if ($pdo instanceof PDO && $pdo->inTransaction() && ! $pdo->rollBack()) { throw new LogicException('API policy snapshot not ended.'); } }
            finally { $reader?->disconnect(); $pdo = $reader = null; }
        }
    }
}
