<?php
// Definitions only. A separate trusted Laravel writer owns the IMMEDIATE transaction.
require_once __DIR__.'/attendance.php';

/** Fixed owner withdrawal, not a review or a change to the original booking. */
function correction_withdraw_locked(PDO $db, int $user, string $role, #[SensitiveParameter] string $version, mixed $id, mixed $reason, Closure $auditRunner): void {
    if (!$db->inTransaction()) { throw new LogicException('Withdrawal caller must own an active IMMEDIATE transaction on this PDO.'); }
    // Guard-derived fingerprint/role only; re-read under the same writer lock.
    $actor=att_one($db,'SELECT role,auth_source,password FROM users WHERE id=?',[$user]);
    if ($user<1 || !in_array($role,['user','supervisor','admin'],true)
        || preg_match('/\A[a-f0-9]{64}\z/',$version)!==1
        || !$actor || $actor['auth_source']!=='local' || $actor['role']!==$role
        || !is_string($actor['password']) || $actor['password']===''
        || !hash_equals($version,hash('sha256',$actor['password']))) {
        throw new AttendanceError('Sitzung nicht mehr gültig. Bitte neu anmelden.',401);
    }
    if (!is_string($id) || preg_match('/\A[1-9][0-9]*\z/',$id)!==1
        || filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false
        || !is_string($reason) || strlen(trim($reason))<5 || strlen(trim($reason))>1000) {
        throw new AttendanceError('Antrag und Rücknahmebegründung prüfen.',400);
    }
    $reason=trim($reason);
    // Never overwrite prior decision metadata, including malformed pending rows.
    $a=att_one($db,"SELECT id FROM time_adjustments WHERE id=? AND user_id=? AND status='pending' AND reviewer_id IS NULL AND reviewed_at IS NULL AND review_reason IS NULL",[$id,$user]);
    if (!$a) { throw new AttendanceError('Nur eigene noch offene Anträge können zurückgenommen werden. Bitte neu laden.',409); }
    $auditRunner('Rücknahme Korrekturantrag #'.$a['id'].': '.$reason,static function () use ($db,$a,$user,$reason): void {
        // Existing audit UPDATE trigger captures before/after. Original snapshot,
        // proposal, request reason, booking, sessions and reviewer_id stay intact.
        att_exec($db,"UPDATE time_adjustments SET status='withdrawn',reviewed_at=?,review_reason=? WHERE id=? AND user_id=? AND status='pending' AND reviewer_id IS NULL AND reviewed_at IS NULL AND review_reason IS NULL",[time(),$reason,$a['id'],$user]);
    });
}
