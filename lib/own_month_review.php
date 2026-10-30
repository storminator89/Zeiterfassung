<?php
// Definitions only: stored own-row precheck facts, never period closure or a time repair.
require_once __DIR__.'/attendance.php';
require_once __DIR__.'/legacy_timestamp_assessment.php';
require_once __DIR__.'/correction_presentation.php';
require_once __DIR__.'/correction_selection.php';

function chrono_own_month_review_month(mixed $value): string
{
    if (!is_string($value) || preg_match('/\A(?!0000)[0-9]{4}-(?:0[1-9]|1[0-2])\z/',$value)!==1) {
        throw new InvalidArgumentException('Invalid own month review.');
    }
    return $value;
}

/** Existing strict diagnosis before canonical Berlin projection; never choose an ambiguous instant. */
function chrono_own_month_review_day(mixed $value): ?string
{
    if (legacy_timestamp_assessment($value)['category']!==null) { return null; }
    try { $day=booking_local_date(booking_time($value))->format('Y-m-d'); }
    catch (Throwable) { return null; }
    return preg_match('/\A(?!0000)[0-9]{4}-[0-9]{2}-[0-9]{2}\z/',$day)===1?$day:null;
}

/** Stored proposal epoch uses the existing integer projection, not a new date parser. */
function chrono_own_month_review_proposed_day(mixed $value): ?string
{
    $epoch=chrono_correction_preview_integer($value);
    if ($epoch===false) { return null; }
    try { $day=booking_local_date($epoch)->format('Y-m-d'); }
    catch (Throwable) { return null; }
    return preg_match('/\A(?!0000)[0-9]{4}-[0-9]{2}-[0-9]{2}\z/',$day)===1?$day:null;
}

/** Original/proposal START-day facts only; not affected-period or approval classification. */
function chrono_own_month_review_request(array $row,string $month): string
{
    if (!in_array($row['cancel']??null,[0,'0',1,'1'],true)) { return 'unclear'; }
    $days=[];$raw=$row['original_json']??null;
    if (($row['record_id']??null)===null) {
        if ($raw!==null || in_array($row['cancel'],[1,'1'],true)) { return 'unclear'; }
    } else {
        try { correction_selection_id($row['record_id']); }
        catch (InvalidArgumentException) { return 'unclear'; }
        if (!is_string($raw) || $raw==='' || strlen($raw)>65536) { return 'unclear'; }
        try {
            $original=json_decode($raw,false,8,JSON_THROW_ON_ERROR);
            // Canonical JSON encoding only, not a full row/schema check: duplicates/overflow stay unknown.
            if (!$original instanceof stdClass || json_encode($original,JSON_THROW_ON_ERROR)!==$raw) { return 'unclear'; }
        } catch (JsonException) { return 'unclear'; }
        $days[]=chrono_own_month_review_day($original->startzeit??null);
    }
    if (in_array($row['cancel'],[0,'0'],true)) { $days[]=chrono_own_month_review_proposed_day($row['proposed_start']??null); }
    if (in_array(null,$days,true)) { return 'unclear'; }
    foreach ($days as $day) { if (substr($day,0,7)===$month) { return 'selected'; } }
    return 'outside';
}

/** Closed-row hints reuse existing strict timestamp diagnosis and canonical pause/duration checks. */
function chrono_own_month_review_closed_relation(array $row,string $month): ?string
{
    $day=chrono_own_month_review_day($row['startzeit']??null);
    if ($day===null) { return 'unclear'; }
    if (substr($day,0,7)!==$month) { return null; }
    // Oversized pause input is unassessed, not silently truncated into a valid number.
    if (legacy_timestamp_assessment($row['endzeit']??null)['category']!==null
        || (int)($row['pause_bounded']??1)!==1
        || report_record_calculation($row)['status']==='invalid') { return 'selected'; }
    return null;
}

/** Caller already owns the authenticated read snapshot; no owner/query/role from a form. */
function chrono_own_month_review_snapshot(PDO $db,int $owner,string $month,DateTimeImmutable $loaded): array
{
    chrono_own_month_review_month($month);
    if (!$db->inTransaction() || $owner<1) { throw new LogicException('An authenticated own-row snapshot is required.'); }
    $counts=array_fill_keys(['open_selected','open_earlier','open_unclear','pending_selected','pending_unclear'],0);$samples=[];$relevant=0;
    $s=$db->prepare("SELECT CAST(id AS TEXT) AS id,
        CASE WHEN typeof(startzeit)='text' AND length(CAST(startzeit AS BLOB))<=25 THEN startzeit ELSE NULL END AS startzeit
        FROM zeiterfassung WHERE user_id=? AND (endzeit IS NULL OR endzeit='') ORDER BY id DESC");
    try {
        $s->execute([$owner]);
        while (($row=$s->fetch(PDO::FETCH_ASSOC))!==false) {
            $day=chrono_own_month_review_day($row['startzeit']);$relation=$day===null?'unclear':(substr($day,0,7)===$month?'selected':($day<$month.'-01'?'earlier':'outside'));
            if ($relation==='outside') { continue; }
            $counts['open_'.$relation]++;$relevant++;
            if (count($samples)<5) { $samples[]=['kind'=>'open','id'=>$row['id'],'relation'=>$relation]; }
        }
    } finally { $s->closeCursor(); }
    // Stream active rows; oversized/foreign representations stay unknown without
    // materializing arbitrary old notes or reconstructing a missing snapshot.
    $s=$db->prepare("SELECT CAST(id AS TEXT) AS id,
        CASE WHEN record_id IS NULL THEN NULL WHEN length(CAST(record_id AS BLOB))<=20 THEN record_id ELSE '' END AS record_id,
        CASE WHEN length(CAST(proposed_start AS BLOB))<=20 THEN proposed_start ELSE NULL END AS proposed_start,
        CASE WHEN length(CAST(cancel AS BLOB))<=1 THEN cancel ELSE NULL END AS cancel,
        CASE WHEN original_json IS NULL THEN NULL
             WHEN typeof(original_json)='text' AND length(CAST(original_json AS BLOB))<=65536 THEN original_json ELSE '' END AS original_json
        FROM time_adjustments WHERE user_id=? AND status='pending' ORDER BY id DESC");
    try {
        $s->execute([$owner]);
        while (($row=$s->fetch(PDO::FETCH_ASSOC))!==false) {
            $relation=chrono_own_month_review_request($row,$month);
            if ($relation==='outside') { continue; }
            $counts['pending_'.$relation]++;$relevant++;
            if (count($samples)<5) { $samples[]=['kind'=>'request','id'=>$row['id'],'relation'=>$relation]; }
        }
    } finally { $s->closeCursor(); }
    // Closed rows were absent from the original open-item precheck. Stream only
    // bounded diagnostic fields; retain no originals, notes, absence kinds or error text.
    $closedCounts=['selected'=>0,'unclear'=>0];
    $s=$db->prepare("SELECT CAST(id AS TEXT) AS id,
        CASE WHEN typeof(startzeit)='text' AND length(CAST(startzeit AS BLOB))<=25 THEN startzeit ELSE NULL END AS startzeit,
        CASE WHEN typeof(endzeit)='text' AND length(CAST(endzeit AS BLOB))<=25 THEN endzeit ELSE NULL END AS endzeit,
        CASE WHEN pause IS NULL OR length(CAST(pause AS BLOB))<=20 THEN pause ELSE NULL END AS pause,
        CASE WHEN pause IS NULL OR length(CAST(pause AS BLOB))<=20 THEN 1 ELSE 0 END AS pause_bounded
        FROM zeiterfassung WHERE user_id=? AND endzeit IS NOT NULL AND endzeit<>'' ORDER BY id DESC");
    try {
        $s->execute([$owner]);
        while (($row=$s->fetch(PDO::FETCH_ASSOC))!==false) {
            $relation=chrono_own_month_review_closed_relation($row,$month);
            if ($relation===null) { continue; }
            $closedCounts[$relation]++;$relevant++;
            if (count($samples)<5) { $samples[]=['kind'=>'closed','id'=>$row['id'],'relation'=>$relation]; }
        }
    } finally { $s->closeCursor(); }
    return ['month'=>$month,'loaded_at'=>$loaded->setTimezone(new DateTimeZone('Europe/Berlin'))->format(DateTimeInterface::ATOM),
        'counts'=>$counts,'closed_counts'=>$closedCounts,'samples'=>$samples,'limited'=>$relevant>count($samples)];
}

/** Native existing session actor/version only; reads and authority share this owned snapshot. */
function chrono_native_own_month_review(PDO $db,int $actor,#[SensitiveParameter] mixed $version,string $month): array
{
    chrono_own_month_review_month($month);
    if ($db->inTransaction()) { throw new LogicException('Own month review requires an idle connection.'); }
    if (!$db->beginTransaction()) { throw new RuntimeException('Own month review unavailable.'); }
    try {
        $current=att_one($db,'SELECT role,password FROM users WHERE id=?',[$actor]);
        if ($actor<1 || !$current || !is_string($version) || preg_match('/\A[a-f0-9]{64}\z/',$version)!==1
            || !is_string($current['password']) || !hash_equals($version,hash('sha256',$current['password']))) {
            throw new AttendanceError('Eigene Monatsprüfung nicht verfügbar. Bitte neu anmelden.',401);
        }
        if (!in_array($current['role'],['user','supervisor','admin'],true)) { throw new AttendanceError('Eigene Monatsprüfung nicht verfügbar.',403); }
        $result=chrono_own_month_review_snapshot($db,$actor,$month,new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')));
        if (!$db->commit()) { throw new RuntimeException('Own month review unavailable.'); }
        return $result;
    } catch (Throwable $error) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $error;
    }
}
