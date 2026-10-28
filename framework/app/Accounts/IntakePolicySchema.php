<?php

namespace App\Accounts;

use LogicException;
use PDO;

/** Explicit fresh-source definitions only. No runtime/adoption registration or existing-data upgrade. */
final class IntakePolicySchema
{
    /** Fixed canonical additions for a future separately admitted V4 profile. */
    public static function definitions(): array
    {
        return [
            'user_intake_policy' => <<<'SQL'
CREATE TABLE user_intake_policy (
    id INTEGER PRIMARY KEY CHECK(id=1),
    manual_enabled INTEGER NOT NULL CHECK(typeof(manual_enabled)='integer' AND manual_enabled IN (0,1)),
    ldap_sync_enabled INTEGER NOT NULL CHECK(typeof(ldap_sync_enabled)='integer' AND ldap_sync_enabled IN (0,1)),
    registration_enabled INTEGER NOT NULL CHECK(typeof(registration_enabled)='integer' AND registration_enabled IN (0,1)),
    revision INTEGER NOT NULL CHECK(typeof(revision)='integer' AND revision>=1),
    updated_at INTEGER NOT NULL CHECK(typeof(updated_at)='integer' AND updated_at>=0),
    updated_by INTEGER CHECK(updated_by IS NULL OR (typeof(updated_by)='integer' AND updated_by>=1))
)
SQL,
            'retain_user_intake_policy_DELETE' => "CREATE TRIGGER retain_user_intake_policy_DELETE BEFORE DELETE ON user_intake_policy BEGIN SELECT RAISE(ABORT,'Retain intake policy'); END",
            'protect_user_intake_policy_UPDATE' => "CREATE TRIGGER protect_user_intake_policy_UPDATE BEFORE UPDATE ON user_intake_policy WHEN NEW.revision<>OLD.revision+1 OR NEW.updated_by IS NULL BEGIN SELECT RAISE(ABORT,'Intake policy revision required'); END",
            'audit_user_intake_policy_UPDATE' => "CREATE TRIGGER audit_user_intake_policy_UPDATE AFTER UPDATE ON user_intake_policy BEGIN INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,before_json,after_json) VALUES(unixepoch(),audit_actor(),audit_reason(),'user_intake_policy',NEW.id,0,'UPDATE',json_object('manual_enabled',OLD.manual_enabled,'ldap_sync_enabled',OLD.ldap_sync_enabled,'registration_enabled',OLD.registration_enabled,'revision',OLD.revision,'updated_at',OLD.updated_at,'updated_by',OLD.updated_by),json_object('manual_enabled',NEW.manual_enabled,'ldap_sync_enabled',NEW.ldap_sync_enabled,'registration_enabled',NEW.registration_enabled,'revision',NEW.revision,'updated_at',NEW.updated_at,'updated_by',NEW.updated_by)); END",
        ];
    }

    /** @internal Fresh V3 source builder only; caller owns canonical schema admission and transaction. */
    public static function initializeFreshLocked(PDO $pdo): void
    {
        if (! $pdo->inTransaction()
            || $pdo->query('SELECT version FROM app_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN) !== [1, 2, 3]) {
            throw new LogicException('A fresh canonical V3 source and caller-owned transaction are required.');
        }
        // Refuse adoption of populated users, operations, directory/device state
        // or history. This package deliberately provides no existing-data path.
        foreach (['users', 'zeiterfassung', 'work_sessions', 'break_intervals', 'attendance_events',
            'time_adjustments', 'work_models', 'audit_log', 'departments', 'Feiertage', 'ldap_settings',
            'terminals', 'employee_credentials', 'terminal_nonces', 'terminal_limits', 'terminal_grants'] as $table) {
            if ((int) $pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn() !== 0) {
                throw new LogicException('Intake-policy initialization supports only a fresh empty source.');
            }
        }
        // Nonempty seed tables are still state: exact schema identity alone
        // does not prove the terminal module is off or the sites/rules are new.
        $seeds = [
            'terminal_settings' => [['id' => 1, 'enabled' => 0]],
            'terminal_sites' => [['id' => 1, 'name' => 'Praxis']],
            'pause_settings' => [
                ['id' => 1, 'hours_threshold' => 6, 'minimum_pause' => 30],
                ['id' => 2, 'hours_threshold' => 9, 'minimum_pause' => 45],
            ],
        ];
        foreach ($seeds as $table => $expected) {
            if ($pdo->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) !== $expected) {
                throw new LogicException('Intake-policy initialization requires canonical fresh seed values.');
            }
        }
        if ($pdo->query("SELECT 1 FROM sqlite_schema WHERE type='table' AND name='login_attempts'")->fetchColumn() !== false
            && (int) $pdo->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn() !== 0) {
            throw new LogicException('Intake-policy initialization cannot adopt existing login attempts.');
        }
        $definitions = self::definitions();
        $pdo->exec($definitions['user_intake_policy']);
        $pdo->exec('INSERT INTO user_intake_policy(id,manual_enabled,ldap_sync_enabled,registration_enabled,revision,updated_at,updated_by) VALUES(1,1,0,0,1,unixepoch(),NULL)');
        foreach (array_diff_key($definitions, ['user_intake_policy' => true]) as $sql) {
            $pdo->exec($sql);
        }
        $pdo->exec('INSERT INTO app_migrations(version) VALUES(4)');
    }
}
