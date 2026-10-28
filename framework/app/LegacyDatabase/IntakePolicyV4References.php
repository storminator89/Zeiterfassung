<?php

namespace App\LegacyDatabase;

use App\Accounts\IntakePolicy;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\SQLiteConnection;
use PDO;
use RuntimeException;

/** Explicit canonical anonymous references only; never opens a user-supplied path. */
final class IntakePolicyV4References
{
    private function __construct(
        private readonly array $mapping,
        private readonly V2Preflight $preflight,
        private readonly PDO $v2,
        private readonly PDO $v3,
        private readonly PDO $v4,
        private readonly array $signatures,
    ) {}

    /** Trusted unchanged source inventory, not request/configuration mapping. */
    public static function build(array $mapping): self
    {
        $preflight = new V2Preflight($mapping);
        $references = [];
        $tables = array_column($mapping['tables'], null, 'name');
        foreach ([2, 3, 4] as $version) {
            $pdo = (new SQLiteConnector)->connect(['database' => ':memory:', 'busy_timeout' => 5000]);
            MemoryOnly::connection($pdo);
            if ($version === 4) {
                $build = require dirname(__DIR__, 2).'/database/fixtures/intake_policy_v4.php';
                $build($pdo, $mapping);
            } else {
                $build = require dirname(__DIR__, 2).'/database/fixtures/legacy_v2.php';
                $build($pdo, $mapping, false);
                if ($version === 3) {
                    require_once dirname(__DIR__, 3).'/lib/user_audit_v3.php';
                    $pdo->beginTransaction();
                    try { \user_audit_v3_apply_locked($pdo); $pdo->commit(); }
                    catch (\Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
                }
            }
            // Canonical optional-table shape, independent of whether the new
            // source has ever needed an empty lazy login-attempt table.
            $pdo->exec($tables['login_attempts']['create_sql']);
            $connection = new SQLiteConnection($pdo, ':memory:', '', ['name' => 'chrono_intake_reference']);
            $repository = new DatabaseMigrationRepository(new ConnectionResolver(['chrono_intake_reference' => $connection]), V2Preflight::LEDGER);
            $repository->setSource('chrono_intake_reference');
            $repository->createRepository();
            $references[$version] = $pdo;
        }
        $signatures = [
            'base_schema_signature' => $preflight->inspect($references[2], $references[2])['schema_signature'],
            'user_audit_schema_signature' => $preflight->inspectUserAuditV3($references[3], $references[3])['schema_signature'],
            'schema_signature' => $preflight->inspectIntakePolicyV4($references[4], $references[4])['schema_signature'],
        ];
        RuntimeAdmission::intakePolicyV4LedgerRows(...array_values($signatures));

        return new self($mapping, $preflight, $references[2], $references[3], $references[4], $signatures);
    }

    public function signatures(): array
    {
        return $this->signatures;
    }

    /** Structural V4 preflight also works after legitimate account/policy changes. */
    public function inspect(PDO $target): array
    {
        return $this->preflight->inspectIntakePolicyV4($target, $this->v4);
    }

    /** Initial registration only: exact canonical empty state, no old-data path. */
    public function requireFresh(PDO $target): array
    {
        $checked = $this->inspect($target);
        if ($checked['schema_signature'] !== $this->signatures['schema_signature']) {
            throw new RuntimeException('The canonical fresh intake-policy-v4 source is required.');
        }
        $mapping = $this->sourceMapping();
        if (SourceSnapshot::capture($target, $mapping) !== SourceSnapshot::capture($this->v4, $mapping)) {
            throw new RuntimeException('Existing source state cannot be registered as a fresh installation.');
        }
        if ($target->query("SELECT 1 FROM sqlite_schema WHERE type='table' AND name='login_attempts'")->fetchColumn() !== false
            && (int) $target->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn() !== 0) {
            throw new RuntimeException('A fresh source cannot contain existing login attempts.');
        }
        $policy = IntakePolicy::fromRow($target->query(RuntimeAdmission::INTAKE_POLICY_SQL)->fetch(PDO::FETCH_ASSOC));
        if (! $policy->manualEnabled || $policy->ldapSyncEnabled || $policy->registrationEnabled
            || $policy->revision !== 1 || $policy->updatedBy !== null) {
            throw new RuntimeException('The unchanged initial intake policy is required.');
        }

        return $checked;
    }

    /** NEW budget-ready producer only; generic absent/empty compatibility stays exact. */
    public function requireFreshLoginBudget(PDO $target): array
    {
        // Includes full canonical structural preflight for a PRESENT table, not
        // merely the signature that historically normalizes absent vs empty.
        $checked = $this->requireFresh($target);
        $statement = null;
        try {
            $statement = $target->query("SELECT type FROM main.sqlite_schema WHERE name='login_attempts'");
            if ($statement === false || $statement->fetchColumn() !== 'table'
                || $statement->fetchColumn() !== false) {
                throw new RuntimeException('The physical canonical fresh login-budget table is required.');
            }
        } finally {
            try {
                if ($statement !== null && $statement !== false && ! $statement->closeCursor()) {
                    throw new RuntimeException('Fresh login-budget presence cursor was not closed.');
                }
            } finally {
                $statement = null;
            }
        }
        try {
            $statement = $target->query('SELECT 1 FROM main.login_attempts LIMIT 1');
            if ($statement === false || $statement->fetchColumn() !== false) {
                throw new RuntimeException('A fresh login-budget table must be empty.');
            }
        } finally {
            try {
                if ($statement !== null && $statement !== false && ! $statement->closeCursor()) {
                    throw new RuntimeException('Fresh login-budget emptiness cursor was not closed.');
                }
            } finally {
                $statement = null;
            }
        }

        return $checked;
    }

    /** Hash preservation evidence includes policy metadata but never returns its row. */
    public function preservedSource(PDO $target): array
    {
        $source = SourceSnapshot::capture($target, $this->sourceMapping());
        $source['policy_signature'] = hash('sha256', json_encode(
            $target->query(RuntimeAdmission::INTAKE_POLICY_SQL)->fetchAll(PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR,
        ));

        return $source;
    }

    private function sourceMapping(): array
    {
        $mapping = $this->mapping;
        // Absent vs empty lazy table is equivalent only after exact schema
        // preflight and the explicit zero-row check above, never for live data.
        $mapping['tables'] = array_values(array_filter($mapping['tables'], static fn (array $table): bool => $table['name'] !== 'login_attempts'));

        return $mapping;
    }
}
