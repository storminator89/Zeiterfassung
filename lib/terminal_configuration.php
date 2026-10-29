<?php

/** Fixed, secret-free site creation; caller owns authorization and transaction. */
function terminal_create_site_locked(PDO $db, string $name): void {
    if (!$db->inTransaction()) { throw new LogicException('Terminal configuration requires the caller transaction.'); }
    if ($name==='' || trim($name)!==$name || strlen($name)>100) { throw new AttendanceError('Standortnamen prüfen.',400); }
    $statement=$db->prepare('INSERT INTO terminal_sites(name) VALUES(?)');
    $statement->execute([$name]);$statement->closeCursor();
    // Capture before the journal INSERT changes lastInsertId. No caller uses
    // this helper as an arbitrary entity/SQL writer or receives an audit ID.
    $id=$db->lastInsertId();
    $statement=$db->prepare("INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,before_json,after_json)
SELECT unixepoch(),audit_actor(),audit_reason(),'terminal_sites',id,0,'INSERT',NULL,json_object('id',id,'name',name)
FROM terminal_sites WHERE id=?");
    $statement->execute([$id]);$statement->closeCursor();
}

/** Fixed module row only; existing module/open-work/server gates stay with caller. */
function terminal_save_module_settings_locked(PDO $db, bool $enabled): void {
    if (!$db->inTransaction()) { throw new LogicException('Terminal configuration requires the caller transaction.'); }
    $statement=$db->query("SELECT json_object('id',id,'enabled',enabled) FROM terminal_settings WHERE id=1");
    $before=$statement->fetchColumn();$statement->closeCursor();
    $statement=$db->prepare('UPDATE terminal_settings SET enabled=? WHERE id=1');
    $statement->execute([$enabled?1:0]);$statement->closeCursor();
    // Preserve existing zero-row behavior; never invent an old/default row.
    if ($before===false) { return; }
    $statement=$db->prepare("INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,before_json,after_json)
SELECT unixepoch(),audit_actor(),audit_reason(),'terminal_settings',id,0,'UPDATE',?,json_object('id',id,'enabled',enabled)
FROM terminal_settings WHERE id=1");
    $statement->execute([$before]);$statement->closeCursor();
}

/** Fixed redacted device INSERT only; enrollment code and generation never selected. */
function terminal_journal_created_device_locked(PDO $db, int $id): void {
    if (!$db->inTransaction()) { throw new LogicException('Terminal device journal requires the caller transaction.'); }
    $statement=$db->prepare("INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,before_json,after_json)
SELECT unixepoch(),audit_actor(),audit_reason(),'terminals',id,0,'INSERT',NULL,json_object('id',id,'site_id',site_id,'name',name,'enabled',enabled)
FROM terminals WHERE id=?");
    $statement->execute([$id]);$statement->closeCursor();
}

/** Existing revoke UPDATE, with fixed redacted snapshots in the same caller transaction. */
function terminal_revoke_device_locked(PDO $db, int $id): void {
    if (!$db->inTransaction()) { throw new LogicException('Terminal device journal requires the caller transaction.'); }
    $statement=$db->prepare("SELECT json_object('id',id,'site_id',site_id,'name',name,'enabled',enabled) FROM terminals WHERE id=?");
    $statement->execute([$id]);$before=$statement->fetchColumn();$statement->closeCursor();
    $statement=$db->prepare('UPDATE terminals SET enabled=0,generation=?,pairing_hash=NULL,pairing_expires=NULL WHERE id=?');
    $statement->execute([bin2hex(random_bytes(16)), $id]);$statement->closeCursor();
    // Preserve successful no-op and zero-row behavior; never invent a prior device.
    if ($before===false) { return; }
    $statement=$db->prepare("INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,before_json,after_json)
SELECT unixepoch(),audit_actor(),audit_reason(),'terminals',id,0,'UPDATE',?,json_object('id',id,'site_id',site_id,'name',name,'enabled',enabled)
FROM terminals WHERE id=?");
    $statement->execute([$before,$id]);$statement->closeCursor();
}
