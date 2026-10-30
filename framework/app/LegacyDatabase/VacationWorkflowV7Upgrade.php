<?php

namespace App\LegacyDatabase;

use App\Vacation\VacationSchema;
use Closure;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\SQLiteConnection;
use PDO;
use Pdo\Sqlite;
use RuntimeException;
use Throwable;

/** Explicit additive stopped V5→V7 operation. No path opener, adoption, reset or V6. */
final class VacationWorkflowV7Upgrade
{
    private readonly AccountLifecycleV5References $before;
    private readonly VacationWorkflowV7References $after;

    public function __construct(private readonly array $mapping)
    {
        $this->before=AccountLifecycleV5References::build($mapping);
        $this->after=VacationWorkflowV7References::build($mapping);
    }

    /** Exact known profile, final consistent domain state and source-owned ledger. */
    public function inspect(PDO $pdo): array
    {
        $versions=$pdo->query('SELECT version FROM app_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
        $signatures=$this->after->signatures();
        if ($versions===[1,2,3,4,5]) {
            $checked=$this->before->inspect($pdo);
            $expected=RuntimeAdmission::accountLifecycleV5LedgerRows($signatures['base_schema_signature'],
                $signatures['user_audit_schema_signature'],$signatures['intake_policy_schema_signature'],
                $signatures['account_lifecycle_schema_signature']);
            $signature=$signatures['account_lifecycle_schema_signature'];$profile='account-lifecycle-v5';
        } elseif ($versions===[1,2,3,4,5,7]) {
            $checked=$this->after->inspect($pdo);
            $expected=RuntimeAdmission::vacationWorkflowV7LedgerRows(...array_values($signatures));
            $signature=$signatures['schema_signature'];$profile='vacation-requests-v7';
        } else { throw new RuntimeException('Only exact current V5 or complete vacation V7 is supported.'); }
        if ($checked['schema_signature']!==$signature
            || $pdo->query('SELECT id,migration,batch FROM framework_migrations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)!==$expected
            || $pdo->query("SELECT seq FROM sqlite_sequence WHERE name='framework_migrations'")->fetchColumn()!==count($expected)) {
            throw new RuntimeException('The complete canonical schema and exact migration ledger are required.');
        }
        return $checked+['profile'=>$profile,'schema_version'=>$pdo->query('PRAGMA schema_version')->fetchColumn()];
    }

    /** Every existing row, account generation, policy, login counter and sequence stays covered. */
    public function preservedSource(PDO $pdo): array
    {
        $mapping=$this->mapping;
        $mapping['tables']=array_values(array_filter($mapping['tables'],static fn(array $t):bool=>$t['name']!=='app_migrations'));
        $source=SourceSnapshot::capture($pdo,$mapping);
        $source['intake_policy_sha256']=hash('sha256',json_encode(
            $pdo->query(RuntimeAdmission::INTAKE_POLICY_SQL)->fetchAll(PDO::FETCH_ASSOC),JSON_THROW_ON_ERROR));
        return $source;
    }

    /** Derive only from the same old identity/settings and canonical checked V7 lineage. */
    public function manifest(RuntimeAdmission $old,array $checked): array
    {
        if ($old->schemaProfile!=='account-lifecycle-v5' || $checked['profile']!=='vacation-requests-v7') {
            throw new RuntimeException('A confirmed same-source V5→V7 manifest handoff is required.');
        }
        $s=$this->after->signatures();
        if ($old->baseSchemaSignature!==$s['base_schema_signature'] || $old->userAuditSchemaSignature!==$s['user_audit_schema_signature']
            || $old->intakePolicySchemaSignature!==$s['intake_policy_schema_signature']
            || $old->schemaSignature!==$s['account_lifecycle_schema_signature']) {
            throw new RuntimeException('The original complete V5 lineage is required.');
        }
        return ['path'=>$old->path,'device'=>$old->device,'inode'=>$old->inode,
            'schema_version'=>$checked['schema_version'],'schema_signature'=>$s['schema_signature'],
            'foreign_keys'=>$old->foreignKeys,'journal_mode'=>$old->journalMode,'synchronous'=>$old->synchronous,
            'schema_profile'=>'vacation-requests-v7','base_schema_signature'=>$s['base_schema_signature'],
            'user_audit_schema_signature'=>$s['user_audit_schema_signature'],
            'intake_policy_schema_signature'=>$s['intake_policy_schema_signature'],
            'account_lifecycle_schema_signature'=>$s['account_lifecycle_schema_signature']];
    }

    /** Caller owns verified cold backup, operator fence and stopped-worker prerequisite. */
    public function applyConnection(SQLiteConnection $connection,?Closure $guard=null): array
    {
        $pdo=$connection->getPdo();
        if (!$pdo instanceof Sqlite || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION
            || $connection->getName()!=='chrono_offline_vacation_v7' || $connection->transactionLevel()!==0
            || $pdo->inTransaction() || $connection->getConfig('transaction_mode')!=='IMMEDIATE') {
            throw new RuntimeException('The fixed offline upgrade must own its IMMEDIATE transaction.');
        }
        $checked=$this->inspect($pdo);$guard?->__invoke($pdo);
        if ($checked['profile']==='vacation-requests-v7') { return $checked+['changed'=>false]; }
        $source=$this->preservedSource($pdo);
        $connection->setReconnector(static fn()=>throw new RuntimeException('Upgrade reconnect is forbidden.'));
        try {
            $connection->beginTransaction();$this->active($connection,$pdo);
            if ($this->inspect($pdo)!==$checked || $this->preservedSource($pdo)!==$source) {
                throw new RuntimeException('The stopped source changed before the upgrade lock.');
            }
            $guard?->__invoke($pdo);
            foreach (VacationSchema::createStatements() as $sql) { $pdo->exec($sql); }
            $pdo->exec('INSERT INTO app_migrations(version) VALUES(7)');
            $repository=new DatabaseMigrationRepository(new ConnectionResolver(['chrono_offline_vacation_v7'=>$connection]),V2Preflight::LEDGER);
            $repository->setSource('chrono_offline_vacation_v7');
            $rows=RuntimeAdmission::vacationWorkflowV7LedgerRows(...array_values($this->after->signatures()));
            $repository->log($rows[4]['migration'],5);
            $post=$this->inspect($pdo);
            if ($post['profile']!=='vacation-requests-v7' || $this->preservedSource($pdo)!==$source) {
                throw new RuntimeException('The additive upgrade did not preserve the existing source.');
            }
            $guard?->__invoke($pdo);$this->active($connection,$pdo);$connection->commit();
            if ($connection->getRawPdo()!==$pdo || $connection->transactionLevel()!==0 || $pdo->inTransaction()) {
                throw new RuntimeException('Upgrade commit outcome requires private offline inspection.');
            }
            return $post+['changed'=>true];
        } catch (Throwable) {
            try {
                if ($connection->getRawPdo()===$pdo && $connection->transactionLevel()>0) { $connection->rollBack(0); }
                elseif ($pdo->inTransaction()) { $pdo->rollBack(); }
                if ($pdo->inTransaction() || $connection->transactionLevel()!==0) { throw new RuntimeException; }
            } catch (Throwable) { throw new RuntimeException('Upgrade outcome is uncertain. Keep workers stopped; inspect before explicit resume.'); }
            throw new RuntimeException('Vacation upgrade not confirmed. Keep workers stopped and retain the private upgrade record.');
        }
    }

    private function active(SQLiteConnection $connection,PDO $pdo): void
    {
        if ($connection->getRawPdo()!==$pdo || $connection->transactionLevel()!==1 || !$pdo->inTransaction()) {
            throw new RuntimeException('The one captured upgrade transaction changed.');
        }
    }
}
