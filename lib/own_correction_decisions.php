<?php
// Definitions only: current own stored terminal request statuses, never delivery/acknowledgment or approval.
require_once __DIR__.'/attendance.php';
require_once __DIR__.'/correction_presentation.php';
require_once __DIR__.'/correction_selection.php';

/** Only the existing strict integer recognizer; never parse free-form dates or substitute now. */
function chrono_own_correction_decision_epoch(mixed $value): ?int
{
    $epoch=chrono_correction_preview_integer($value);
    if ($epoch===false) { return null; }
    try {
        $instant=new DateTimeImmutable('@'.$epoch);
        if (preg_match('/\A(?!0000)[0-9]{4}\z/',$instant->format('Y'))!==1
            || preg_match('/\A(?!0000)[0-9]{4}\z/',$instant->setTimezone(new DateTimeZone('Europe/Berlin'))->format('Y'))!==1) { return null; }
    } catch (Throwable) { return null; }
    return $epoch;
}

/** Caller owns current authenticated own authority; no subject/status/date from a request. */
function chrono_own_correction_decisions_snapshot(PDO $pdo,int $owner,DateTimeImmutable $loaded): array
{
    if (!$pdo->inTransaction() || $owner<1 || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION) {
        throw new LogicException('An authenticated own decision snapshot is required.');
    }
    if (!$pdo->createFunction('chrono_own_correction_decision_epoch','chrono_own_correction_decision_epoch',1)) {
        throw new RuntimeException('Own decision timestamp display unavailable.');
    }
    // Bound old metadata before its callback reaches PHP. No notes, original
    // JSON, record/proposal values, reviewer identities or account fields selected.
    $epoch="chrono_own_correction_decision_epoch(CASE WHEN typeof(a.reviewed_at) IN ('integer','text') AND length(CAST(a.reviewed_at AS BLOB))<=20 THEN a.reviewed_at ELSE NULL END)";
    $base="SELECT CAST(a.id AS TEXT) AS request_id,a.id AS numeric_id,a.status,".$epoch." AS decision_epoch
        FROM time_adjustments a WHERE a.user_id=? AND a.status IN ('approved','rejected','withdrawn')";
    $result=['loaded_at'=>$loaded->setTimezone(new DateTimeZone('Europe/Berlin'))->format(DateTimeInterface::ATOM)];
    foreach (['recent'=>'IS NOT NULL','undated'=>'IS NULL'] as $group=>$condition) {
        $statement=$pdo->prepare('SELECT request_id,status,decision_epoch FROM ('.$base.') WHERE decision_epoch '.$condition.' ORDER BY decision_epoch DESC,numeric_id DESC LIMIT 6');
        try {
            if (!$statement->execute([$owner])) { throw new RuntimeException('Own decision read unavailable.'); }
            $rows=$statement->fetchAll(PDO::FETCH_ASSOC);
        } finally { $statement->closeCursor(); }
        $result['more_'.$group]=count($rows)>5;$result[$group]=[];
        foreach (array_slice($rows,0,5) as $row) {
            // Unusable IDs cannot be rounded/guessed into a different detail link.
            $id=correction_selection_id($row['request_id']);
            if (!in_array($row['status'],['approved','rejected','withdrawn'],true)
                || ($group==='recent' && !is_int($row['decision_epoch']))
                || ($group==='undated' && $row['decision_epoch']!==null)) { throw new UnexpectedValueException('Own decision projection unavailable.'); }
            $result[$group][]=['request_id'=>(string)$id,'status'=>$row['status'],'decision_epoch'=>$row['decision_epoch']];
        }
    }
    return $result;
}

/** Same current Native actor/password-version/known-role pattern, one owned read transaction. */
function chrono_native_own_correction_decisions(PDO $pdo,int $actor,#[SensitiveParameter] mixed $version): array
{
    if ($pdo->inTransaction()) { throw new LogicException('Own decisions require an idle connection.'); }
    if (!$pdo->beginTransaction()) { throw new RuntimeException('Own decisions unavailable.'); }
    try {
        $current=att_one($pdo,'SELECT role,password FROM users WHERE id=?',[$actor]);
        if ($actor<1 || $current===null || !is_string($version) || preg_match('/\A[a-f0-9]{64}\z/',$version)!==1
            || !is_string($current['password']) || !hash_equals($version,hash('sha256',$current['password']))) {
            throw new AttendanceError('Eigene Antragsentscheidungen nicht verfügbar. Bitte neu anmelden.',401);
        }
        if (!in_array($current['role'],['user','supervisor','admin'],true)) { throw new AttendanceError('Eigene Antragsentscheidungen nicht verfügbar.',403); }
        unset($current);
        $result=chrono_own_correction_decisions_snapshot($pdo,$actor,new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')));
        if (!$pdo->commit()) { throw new RuntimeException('Own decision read not confirmed.'); }
        return $result;
    } catch (Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); }throw $error; }
}
