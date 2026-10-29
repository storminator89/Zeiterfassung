<?php
// Definitions only. Own local credentials; no recovery, target account or LDAP/V5 admission.
final class NativeOwnPasswordError extends RuntimeException {
    public function __construct(public readonly string $publicCode, public readonly int $status) {
        parent::__construct($publicCode);
    }
}
function native_own_password_available(array $user): bool {
    return ($user['auth_source']??null)==='local'
        && in_array($user['role']??null,['user','supervisor','admin'],true)
        && is_string($user['password']??null) && $user['password']!=='';
}
/** Trusted session identity only, freshly checked while owning SQLite's writer lock. */
function native_own_password_principal(PDO $db,int $actor,string $role,#[SensitiveParameter] string $version): array {
    if (!$db->inTransaction()) { throw new LogicException('Own password authority requires a writer transaction.'); }
    $ledger=$db->query('SELECT version FROM app_migrations ORDER BY version');
    try { $versions=$ledger->fetchAll(PDO::FETCH_COLUMN); }
    finally { $ledger->closeCursor(); }
    // Bounded old-profile operation refusal, NOT a database/profile admission gate.
    if ($versions!==[1,2] && $versions!==[1,2,3]) {
        throw new NativeOwnPasswordError('unsupported_profile',503);
    }
    $statement=$db->prepare('SELECT id,role,auth_source,password FROM users WHERE id=?');
    try { $statement->execute([$actor]);$user=$statement->fetch(PDO::FETCH_ASSOC); }
    finally { $statement->closeCursor(); }
    if (!$user || !is_string($user['password']) || !hash_equals($version,hash('sha256',$user['password']))
        || $user['role']!==$role) { throw new NativeOwnPasswordError('stale_identity',401); }
    if (!native_own_password_available($user)) { throw new NativeOwnPasswordError('not_allowed',403); }
    return $user;
}
/** Book both quotas and fresh authority atomically, before expensive password work. */
function native_own_password_consume(PDO $db,int $actor,string $role,#[SensitiveParameter] string $version,string $peer): array {
    if ($db->inTransaction()) { throw new LogicException('Own password accounting requires an idle connection.'); }
    if ($db->exec('BEGIN IMMEDIATE')===false) { throw new RuntimeException('Own password accounting unavailable.'); }
    try {
        $user=native_own_password_principal($db,$actor,$role,$version);
        // Same existing native table/DDL, but independent namespaced buckets.
        if ($db->exec('CREATE TABLE IF NOT EXISTS login_attempts (bucket TEXT PRIMARY KEY, starts INTEGER NOT NULL, attempts INTEGER NOT NULL)')===false) {
            throw new RuntimeException('Own password accounting unavailable.');
        }
        $now=time();$updates=[];
        foreach (['user:'.$actor=>5,'ip:'.$peer=>20] as $subject=>$maximum) {
            $key=hash('sha256','native-own-password:'.$subject);
            $statement=$db->prepare('SELECT starts,attempts FROM login_attempts WHERE bucket=?');
            try { $statement->execute([$key]);$old=$statement->fetch(PDO::FETCH_ASSOC); }
            finally { $statement->closeCursor(); }
            $starts=$now;$attempts=0;
            if ($old) {
                $starts=filter_var($old['starts'],FILTER_VALIDATE_INT);
                $attempts=filter_var($old['attempts'],FILTER_VALIDATE_INT);
                if ($starts===false || $attempts===false || $starts<0 || $starts>$now || $attempts<0) {
                    throw new RuntimeException('Own password accounting unavailable.');
                }
                if ($now-$starts>=600) { $starts=$now;$attempts=0; }
            }
            if ($attempts>=$maximum) { throw new NativeOwnPasswordError('rate_limited',429); }
            $updates[]=[$key,$starts,$attempts+1];
        }
        $statement=$db->prepare('INSERT INTO login_attempts(bucket,starts,attempts) VALUES(?,?,?) ON CONFLICT(bucket) DO UPDATE SET starts=excluded.starts,attempts=excluded.attempts');
        try { foreach ($updates as $update) { if (!$statement->execute($update) || $statement->rowCount()!==1) { throw new RuntimeException('Own password accounting unavailable.'); }$statement->closeCursor(); } }
        finally { $statement->closeCursor(); }
        foreach ($updates as [$key,$starts,$attempts]) {
            $statement=$db->prepare('SELECT starts,attempts FROM login_attempts WHERE bucket=?');
            try { $statement->execute([$key]);$persisted=$statement->fetch(PDO::FETCH_ASSOC); }
            finally { $statement->closeCursor(); }
            if (!$persisted || (string)$persisted['starts']!==(string)$starts || (string)$persisted['attempts']!==(string)$attempts) {
                throw new RuntimeException('Own password accounting unavailable.');
            }
        }
        native_own_password_principal($db,$actor,$role,$version);
        if ($db->exec('COMMIT')===false) { throw new RuntimeException('Own password accounting unavailable.'); }
        return $user;
    } catch (Throwable $error) {
        if ($db->inTransaction()) { try { $db->exec('ROLLBACK'); } catch (Throwable) {} }
        throw $error;
    }
}
/**
 * Only the explicit separate native form is accepted. Secrets stay request-local:
 * no flash/return data, log, exception text, quota, audit payload or stored plaintext.
 */
function native_own_password_change(PDO $db,int $actor,string $role,#[SensitiveParameter] string $version,
    #[SensitiveParameter] array $form,string $peer): void {
    if ($actor<1 || !in_array($role,['user','supervisor','admin'],true)
        || preg_match('/\A[a-f0-9]{64}\z/',$version)!==1
        || $db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite'
        || $db->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION) {
        throw new LogicException('Trusted native own-password authority is required.');
    }
    if (array_diff(array_keys($form),['csrf_token','password_action','current_password','password','password_confirmation'])!==[]
        || ($form['password_action']??null)!=='change') { throw new NativeOwnPasswordError('invalid_form',400); }
    foreach (['current_password','password','password_confirmation'] as $key) {
        if (!is_string($form[$key]??null)) { throw new NativeOwnPasswordError('invalid_form',400); }
    }
    $current=$form['current_password'];$next=$form['password']; // Never trim, normalize or truncate.
    if ($current==='' || strlen($current)>4096 || $current==='defaultpassword'
        || strlen($next)<12 || strlen($next)>72 || $next==='defaultpassword'
        || preg_match('/[\x00-\x1f\x7f]/',$next) || strlen($form['password_confirmation'])>72
        || !hash_equals($next,$form['password_confirmation']) || hash_equals($current,$next)) {
        throw new NativeOwnPasswordError('invalid_form',400);
    }
    $user=native_own_password_consume($db,$actor,$role,$version,$peer);
    if (!password_verify($current,$user['password']) || password_verify($next,$user['password'])) {
        throw new NativeOwnPasswordError('invalid_current',400);
    }
    // Hash outside both short writer locks, matching the current native convention.
    $nextHash=password_hash($next,PASSWORD_DEFAULT);
    if ((password_get_info($nextHash)['algoName']??'unknown')!=='bcrypt') {
        throw new LogicException('Own password changes require the native PHP8.5 bcrypt default.');
    }
    if ($db->inTransaction()) { throw new LogicException('Own password replacement requires an idle connection.'); }
    $saved=[];
    foreach (['audit_actor','audit_reason'] as $key) { $saved[$key]=[array_key_exists($key,$GLOBALS),$GLOBALS[$key]??null]; }
    if ($db->exec('BEGIN IMMEDIATE')===false) { throw new RuntimeException('Own password replacement unavailable.'); }
    try {
        $fresh=native_own_password_principal($db,$actor,$role,$version);
        if (!hash_equals($user['password'],$fresh['password'])) { throw new NativeOwnPasswordError('stale_identity',401); }
        $GLOBALS['audit_actor']='web:'.$actor;$GLOBALS['audit_reason']='settings.php: own local password change';
        $statement=$db->prepare("UPDATE users SET password=? WHERE id=? AND auth_source='local' AND role=? AND password=?");
        try {
            $statement->bindValue(1,$nextHash,PDO::PARAM_STR);$statement->bindValue(2,$actor,PDO::PARAM_INT);
            $statement->bindValue(3,$role,PDO::PARAM_STR);$statement->bindValue(4,$fresh['password'],PDO::PARAM_STR);
            if (!$statement->execute() || $statement->rowCount()!==1) { throw new RuntimeException('Own password replacement not confirmed.'); }
        } finally { $statement->closeCursor(); }
        if ($db->exec('COMMIT')===false) { throw new RuntimeException('Own password replacement not confirmed.'); }
    } catch (Throwable $error) {
        if ($db->inTransaction()) { try { $db->exec('ROLLBACK'); } catch (Throwable) {} }
        throw $error;
    } finally {
        foreach ($saved as $key=>[$existed,$value]) { if ($existed) { $GLOBALS[$key]=$value; } else { unset($GLOBALS[$key]); } }
    }
}
