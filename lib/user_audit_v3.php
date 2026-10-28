<?php
// Definitions only. Explicit offline callers own preflight, backup, UDFs and one
// IMMEDIATE transaction; the native V2 bootstrap never calls this upgrade.

/** Fixed V3 user snapshots only; no source values, actor or request parameters. */
function user_audit_v3_trigger_sql(): array {
    return [
        'audit_users_INSERT' => "CREATE TRIGGER audit_users_INSERT AFTER INSERT ON users BEGIN INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,before_json,after_json) VALUES(unixepoch(),audit_actor(),audit_reason(),'users',NEW.id,NEW.id,'INSERT',NULL,json_object('id',NEW.id,'username',NEW.username,'regelarbeitszeit',NEW.regelarbeitszeit,'ueberstunden',NEW.ueberstunden,'role',NEW.role,'department_id',NEW.department_id,'supervisor_id',NEW.supervisor_id)); END",
        'audit_users_UPDATE' => "CREATE TRIGGER audit_users_UPDATE AFTER UPDATE ON users BEGIN INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,before_json,after_json) VALUES(unixepoch(),audit_actor(),audit_reason(),'users',NEW.id,NEW.id,'UPDATE',json_object('id',OLD.id,'username',OLD.username,'regelarbeitszeit',OLD.regelarbeitszeit,'ueberstunden',OLD.ueberstunden,'role',OLD.role,'department_id',OLD.department_id,'supervisor_id',OLD.supervisor_id),json_object('id',NEW.id,'username',NEW.username,'regelarbeitszeit',NEW.regelarbeitszeit,'ueberstunden',NEW.ueberstunden,'role',NEW.role,'department_id',NEW.department_id,'supervisor_id',NEW.supervisor_id)); END",
        'audit_users_DELETE' => "CREATE TRIGGER audit_users_DELETE AFTER DELETE ON users BEGIN INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,before_json,after_json) VALUES(unixepoch(),audit_actor(),audit_reason(),'users',OLD.id,OLD.id,'DELETE',json_object('id',OLD.id,'username',OLD.username,'regelarbeitszeit',OLD.regelarbeitszeit,'ueberstunden',OLD.ueberstunden,'role',OLD.role,'department_id',OLD.department_id,'supervisor_id',OLD.supervisor_id),NULL); END",
    ];
}

/** @internal Apply only after exact canonical V2 schema/ledger preflight. */
function user_audit_v3_apply_locked(PDO $pdo): void {
    if (!$pdo->inTransaction()) {
        throw new LogicException('User-audit V3 requires an active caller-owned transaction.');
    }
    if ($pdo->query('SELECT version FROM app_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN) !== [1, 2]) {
        throw new RuntimeException('User-audit V3 requires exactly application versions one and two.');
    }
    foreach (user_audit_v3_trigger_sql() as $name => $sql) {
        $pdo->exec('DROP TRIGGER '.$name);
        $pdo->exec($sql);
    }
    $pdo->exec('INSERT INTO app_migrations(version) VALUES(3)');
}
