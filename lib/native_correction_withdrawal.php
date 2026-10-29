<?php
// Definitions only. The existing native POST caller owns CSRF and its IMMEDIATE
// transaction. No PDO/session/bootstrap/transaction is opened by this adapter.
require_once __DIR__.'/correction_withdrawal.php';

/** Display hint only; the core repeats authorization and pending checks under lock. */
function native_correction_can_withdraw(array $request, int $user, mixed $source, mixed $version): bool {
    return $user>0 && $source==='local' && is_string($version)
        && preg_match('/\A[a-f0-9]{64}\z/',$version)===1
        && (int)($request['user_id']??0)===$user && ($request['status']??null)==='pending'
        && array_key_exists('reviewer_id',$request) && $request['reviewer_id']===null
        && array_key_exists('reviewed_at',$request) && $request['reviewed_at']===null
        && array_key_exists('review_reason',$request) && $request['review_reason']===null;
}

/** Trusted native identity/role/version arguments, never taken from command data. */
function native_correction_withdraw_locked(PDO $db, int $user, string $role, #[SensitiveParameter] mixed $version, array $command): void {
    if (($command['action']??null)!=='withdraw'
        || array_diff(array_keys($command),['csrf_token','action','id','withdraw_reason'])!==[]) {
        throw new AttendanceError('Ungültige Rücknahmeaktion.',400);
    }
    correction_withdraw_locked($db,$user,$role,is_string($version)?$version:'',
        $command['id']??null,$command['withdraw_reason']??null,
        static function (string $reason, Closure $operation) use ($user): void {
            $GLOBALS['audit_actor']='web:'.$user;
            $GLOBALS['audit_reason']=$reason;
            $operation();
        });
}
