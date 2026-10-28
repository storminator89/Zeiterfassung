<?php

namespace App\Accounts;

use App\LegacyDatabase\IntakePolicyV4References;
use App\LegacyDatabase\MemoryOnly;
use LogicException;
use PDO;

/** NEW anonymous canonical source only; no upgrade, file opener or runtime activation. */
final class AccountLifecycleSchema
{
    public const PROFILE = 'account-lifecycle-v5';

    /** Exact V5 additions; deliberately no implicit account/grant authority defaults. */
    public static function columns(): array
    {
        return [
            'users.account_status' => "TEXT NOT NULL CHECK(typeof(account_status)='text' AND account_status IN ('active','inactive','departed'))",
            'users.auth_generation' => "INTEGER NOT NULL CHECK(typeof(auth_generation)='integer' AND auth_generation>=1)",
            'terminal_grants.account_generation' => "INTEGER NOT NULL CHECK(typeof(account_generation)='integer' AND account_generation>=1)",
        ];
    }

    /** Fixed redacted user snapshots: passwords, email, token and directory data excluded. */
    public static function definitions(): array
    {
        return [
            'audit_users_INSERT' => "CREATE TRIGGER audit_users_INSERT AFTER INSERT ON users BEGIN INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,before_json,after_json) VALUES(unixepoch(),audit_actor(),audit_reason(),'users',NEW.id,NEW.id,'INSERT',NULL,json_object('id',NEW.id,'username',NEW.username,'regelarbeitszeit',NEW.regelarbeitszeit,'ueberstunden',NEW.ueberstunden,'role',NEW.role,'department_id',NEW.department_id,'supervisor_id',NEW.supervisor_id,'account_status',NEW.account_status,'auth_generation',NEW.auth_generation)); END",
            'audit_users_UPDATE' => "CREATE TRIGGER audit_users_UPDATE AFTER UPDATE ON users BEGIN INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,before_json,after_json) VALUES(unixepoch(),audit_actor(),audit_reason(),'users',NEW.id,NEW.id,'UPDATE',json_object('id',OLD.id,'username',OLD.username,'regelarbeitszeit',OLD.regelarbeitszeit,'ueberstunden',OLD.ueberstunden,'role',OLD.role,'department_id',OLD.department_id,'supervisor_id',OLD.supervisor_id,'account_status',OLD.account_status,'auth_generation',OLD.auth_generation),json_object('id',NEW.id,'username',NEW.username,'regelarbeitszeit',NEW.regelarbeitszeit,'ueberstunden',NEW.ueberstunden,'role',NEW.role,'department_id',NEW.department_id,'supervisor_id',NEW.supervisor_id,'account_status',NEW.account_status,'auth_generation',NEW.auth_generation)); END",
            'audit_users_DELETE' => "CREATE TRIGGER audit_users_DELETE AFTER DELETE ON users BEGIN INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,before_json,after_json) VALUES(unixepoch(),audit_actor(),audit_reason(),'users',OLD.id,OLD.id,'DELETE',json_object('id',OLD.id,'username',OLD.username,'regelarbeitszeit',OLD.regelarbeitszeit,'ueberstunden',OLD.ueberstunden,'role',OLD.role,'department_id',OLD.department_id,'supervisor_id',OLD.supervisor_id,'account_status',OLD.account_status,'auth_generation',OLD.auth_generation),NULL); END",
            'require_users_initial_authority_INSERT' => "CREATE TRIGGER require_users_initial_authority_INSERT BEFORE INSERT ON users WHEN NEW.account_status<>'active' OR NEW.auth_generation<>1 BEGIN SELECT RAISE(ABORT,'New account authority must be explicit'); END",
            'protect_users_authority_UPDATE' => "CREATE TRIGGER protect_users_authority_UPDATE BEFORE UPDATE ON users WHEN (NEW.account_status=OLD.account_status AND NEW.auth_generation<>OLD.auth_generation) OR (NEW.account_status<>OLD.account_status AND (OLD.auth_generation=9223372036854775807 OR NEW.auth_generation<>OLD.auth_generation+1)) BEGIN SELECT RAISE(ABORT,'Account authority transition required'); END",
            'require_terminal_grants_authority_INSERT' => "CREATE TRIGGER require_terminal_grants_authority_INSERT BEFORE INSERT ON terminal_grants WHEN NOT EXISTS(SELECT 1 FROM users WHERE id=NEW.user_id AND account_status='active' AND auth_generation=NEW.account_generation) BEGIN SELECT RAISE(ABORT,'Current account authority required'); END",
            'protect_terminal_grants_authority_UPDATE' => "CREATE TRIGGER protect_terminal_grants_authority_UPDATE BEFORE UPDATE ON terminal_grants WHEN NEW.token_hash IS NOT OLD.token_hash OR NEW.terminal_id IS NOT OLD.terminal_id OR NEW.user_id IS NOT OLD.user_id OR NEW.credential_version IS NOT OLD.credential_version OR NEW.expires IS NOT OLD.expires OR NEW.account_generation IS NOT OLD.account_generation BEGIN SELECT RAISE(ABORT,'Grant authority is immutable'); END",
        ];
    }

    /** @internal Only the fixture calls this on canonical empty V4 in its own transaction. */
    public static function initializeFreshLocked(PDO $pdo, array $mapping): void
    {
        MemoryOnly::connection($pdo);
        if (! $pdo->inTransaction()) {
            throw new LogicException('Fresh V5 creation requires a caller-owned transaction.');
        }
        // Exact empty source/seed/schema admission, not an old-data migration.
        IntakePolicyV4References::build($mapping)->requireFreshLoginBudget($pdo);
        foreach (self::columns() as $name => $declaration) {
            [$table, $column] = explode('.', $name);
            $pdo->exec('ALTER TABLE '.$table.' ADD COLUMN '.$column.' '.$declaration);
        }
        foreach (self::definitions() as $name => $sql) {
            if (str_starts_with($name, 'audit_users_')) {
                $pdo->exec('DROP TRIGGER '.$name);
            }
            $pdo->exec($sql);
        }
        $pdo->exec('INSERT INTO app_migrations(version) VALUES(5)');
    }
}
