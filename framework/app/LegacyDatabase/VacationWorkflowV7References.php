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
final class VacationWorkflowV7References
{
    public const SCHEMA_SQL = "SELECT type,name,tbl_name,sql FROM sqlite_schema WHERE name NOT LIKE 'sqlite_%' ORDER BY type,name";

    /** Native online-snapshot validation of the exact canonical table/index/trigger inventory. */
    public function schemaInventory(): array
    {
        return $this->v7->query(self::SCHEMA_SQL)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function requireSourceConnection(PDO $pdo, RuntimeAdmission $admission): void
    {
        if ($admission->schemaProfile !== 'vacation-requests-v7') { throw new RuntimeException('Separate vacation source required.'); }
        $databases = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
        $main = array_values(array_filter($databases,static fn(array $row):bool=>$row['name']==='main'));
        if (count($main)!==1 || $main[0]['file']!==$admission->path) { throw new RuntimeException('Vacation main identity differs.'); }
        foreach ($databases as $database) {
            if (!in_array($database['name'],['main','temp'],true)
                || ($database['name']==='temp' && $database['file']!=='')) { throw new RuntimeException('No attached or file-backed temporary source is admitted.'); }
        }
        // Preflight consults sqlite_temp_schema and initializes its empty handle.
        if ($pdo->query('SELECT COUNT(*) FROM sqlite_temp_schema')->fetchColumn()!==0) {
            throw new RuntimeException('Temporary schema objects are not admitted.');
        }
    }

    /** Caller owns the exact admitted handle and snapshot/lock; no opener or transaction here. */
    public static function requireAdmitted(PDO $pdo, RuntimeAdmission $admission): void
    {
        if ($admission->schemaProfile !== 'vacation-requests-v7'
            || $pdo->query('PRAGMA schema_version')->fetchColumn() !== $admission->schemaVersion
            || $pdo->query('SELECT id,migration,batch FROM framework_migrations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) !== $admission->expectedLedgerRows()) {
            throw new RuntimeException('Exact vacation profile admission required.');
        }
        self::requireSourceConnection($pdo,$admission);
        $mapping = json_decode(file_get_contents(dirname(__DIR__, 3).'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
        $references = self::build($mapping);
        if ($references->signatures() !== ['base_schema_signature'=>$admission->baseSchemaSignature,
            'user_audit_schema_signature'=>$admission->userAuditSchemaSignature,
            'intake_policy_schema_signature'=>$admission->intakePolicySchemaSignature,
            'account_lifecycle_schema_signature'=>$admission->accountLifecycleSchemaSignature(),
            'schema_signature'=>$admission->schemaSignature]) {
            throw new RuntimeException('Canonical vacation lineage required.');
        }
        $references->inspect($pdo);
    }

    private function __construct(
        private readonly array $mapping,
        private readonly V2Preflight $preflight,
        private readonly PDO $v7,
        private readonly array $signatures,
    ) {}

    /** Trusted unchanged source inventory, not request/configuration mapping. */
    public static function build(array $mapping): self
    {
        $preflight = new V2Preflight($mapping);
        $predecessors = AccountLifecycleV5References::build($mapping)->signatures();
        $v7 = (new SQLiteConnector)->connect(['database' => ':memory:', 'busy_timeout' => 5000]);
        MemoryOnly::connection($v7);
        $build = require dirname(__DIR__, 2).'/database/fixtures/vacation_workflow_v7.php';
        $build($v7, $mapping);
        $connection = new SQLiteConnection($v7, ':memory:', '', ['name' => 'chrono_lifecycle_reference']);
        $repository = new DatabaseMigrationRepository(new ConnectionResolver(['chrono_lifecycle_reference' => $connection]), V2Preflight::LEDGER);
        $repository->setSource('chrono_lifecycle_reference');
        $repository->createRepository();
        $signatures = [
            'base_schema_signature' => $predecessors['base_schema_signature'],
            'user_audit_schema_signature' => $predecessors['user_audit_schema_signature'],
            'intake_policy_schema_signature' => $predecessors['intake_policy_schema_signature'],
            'account_lifecycle_schema_signature' => $predecessors['schema_signature'],
            'schema_signature' => $preflight->inspectVacationWorkflowV7($v7, $v7)['schema_signature'],
        ];
        RuntimeAdmission::vacationWorkflowV7LedgerRows(...array_values($signatures));

        return new self($mapping, $preflight, $v7, $signatures);
    }

    public function signatures(): array
    {
        return $this->signatures;
    }

    /** Structural V7 preflight also works after legitimate account/policy changes. */
    public function inspect(PDO $target): array
    {
        return $this->preflight->inspectVacationWorkflowV7($target, $this->v7);
    }

    /** Initial registration only: exact canonical empty state, no old-data path. */
    public function requireFresh(PDO $target): array
    {
        $checked = $this->inspect($target);
        if ($checked['schema_signature'] !== $this->signatures['schema_signature']) {
            throw new RuntimeException('The canonical fresh vacation-requests-v7 source is required.');
        }
        $mapping = $this->sourceMapping();
        if (SourceSnapshot::capture($target, $mapping) !== SourceSnapshot::capture($this->v7, $mapping)) {
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
        $mapping['tables'] = [...$mapping['tables'], ...\App\Vacation\VacationSchema::tables()];
        // Absent vs empty lazy table is equivalent only after exact schema
        // preflight and the explicit zero-row check above, never for live data.
        $mapping['tables'] = array_values(array_filter($mapping['tables'], static fn (array $table): bool => $table['name'] !== 'login_attempts'));

        return $mapping;
    }
}
