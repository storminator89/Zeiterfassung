<?php
// Definitions only: an explicit own-period comparison, never a contractual account or write.
require_once __DIR__.'/attendance.php';
require_once __DIR__.'/api_work_model.php';
require_once __DIR__.'/time_record_review.php';

/** Completed Berlin calendar days only; inclusive range, bounded independently of stored row count. */
function own_period_comparison_range(mixed $from,mixed $through,DateTimeImmutable $loaded): array
{
    $from=api_work_model_day($from);$through=api_work_model_day($through);
    $today=$loaded->setTimezone(new DateTimeZone('Europe/Berlin'))->format('Y-m-d');
    if ($from>$through || $through>=$today || practice_date($from)->diff(practice_date($through))->days>365) {
        throw new InvalidArgumentException('Invalid own comparison period.');
    }
    return [$from,$through];
}

function own_period_comparison_day(mixed $raw): ?string
{
    if (legacy_timestamp_assessment($raw)['category']!==null) { return null; }
    try { return api_work_model_day(booking_local_date(booking_time($raw))->format('Y-m-d')); }
    catch (Throwable) { return null; }
}

/** Caller owns one authenticated snapshot. No subject, source, opening balance or employment inference. */
function own_period_comparison_snapshot(PDO $db,int $owner,string $from,string $through,DateTimeImmutable $loaded): array
{
    [$from,$through]=own_period_comparison_range($from,$through,$loaded);
    if (!$db->inTransaction() || $owner<1) { throw new LogicException('An authenticated own snapshot is required.'); }
    $issues=array_fill_keys(['unknown_start','open','invalid','conflict','model'],0);$daily=[];
    for ($day=practice_date($from);$day->format('Y-m-d')<=$through;$day=$day->modify('+1 day')) {
        $daily[$day->format('Y-m-d')]=['work_minutes'=>0,'credit_minutes'=>0,'records'=>0,'target_minutes'=>null];
    }
    // Keep full own conflict context BEFORE selecting start-days, but no notes, location,
    // original JSON, health detail, credentials or arbitrary oversized field contents.
    $s=$db->prepare("SELECT id,user_id,
        CASE WHEN typeof(startzeit)='text' AND length(CAST(startzeit AS BLOB))<=25 THEN startzeit ELSE NULL END AS startzeit,
        CASE WHEN endzeit IS NULL OR endzeit='' THEN endzeit WHEN typeof(endzeit)='text' AND length(CAST(endzeit AS BLOB))<=25 THEN endzeit ELSE 'unknown' END AS endzeit,
        CASE WHEN pause IS NULL OR length(CAST(pause AS BLOB))<=20 THEN pause ELSE NULL END AS pause,
        CASE WHEN pause IS NULL OR length(CAST(pause AS BLOB))<=20 THEN 1 ELSE 0 END AS pause_bounded,
        CASE WHEN length(CAST(absence_kind AS BLOB))<=32 THEN absence_kind WHEN absence_kind IS NULL THEN NULL ELSE 'stored-credit' END AS absence_kind,
        CASE WHEN CAST(beschreibung AS TEXT) IN ('Urlaub','Krankheit','Feiertag') THEN CAST(beschreibung AS TEXT) ELSE '' END AS beschreibung
        FROM zeiterfassung WHERE user_id=? ORDER BY id");
    try { $s->execute([$owner]);$rows=$s->fetchAll(PDO::FETCH_ASSOC); }
    finally { $s->closeCursor(); }
    $review=[];
    foreach (time_record_review_rows($rows,report_absence_conflicts($rows)) as $row) { $review[report_conflict_key($row)]=true; }
    foreach ($rows as $row) {
        $day=own_period_comparison_day($row['startzeit']);
        if ($day===null) { $issues['unknown_start']++;continue; }
        if (!isset($daily[$day])) { continue; }
        $daily[$day]['records']++;
        $calculation=report_record_calculation($row);
        if ($calculation['status']==='open') { $issues['open']++;continue; }
        if ($row['pause_bounded']!==1 || legacy_timestamp_assessment($row['endzeit'])['category']!==null
            || $calculation['status']!=='completed' || $calculation['end']>$loaded->getTimestamp()) {
            $issues['invalid']++;continue;
        }
        if (isset($review[report_conflict_key($row)])) { $issues['conflict']++;continue; }
        $key=booking_is_absence($row)?'credit_minutes':'work_minutes';
        if ($daily[$day][$key]>PHP_INT_MAX-$calculation['minutes']) { $issues['invalid']++;continue; }
        $daily[$day][$key]+=$calculation['minutes'];
    }
    unset($rows,$review);

    // Canonical historical target resolver, with validation before its permissive
    // legacy casts. Same valid_from/id precedence, calendar and fallback formula.
    $s=$db->prepare("SELECT CAST(id AS TEXT) AS model_id,
        CASE WHEN typeof(valid_from)='text' AND length(CAST(valid_from AS BLOB))<=10 THEN valid_from ELSE '' END AS valid_from,
        CASE WHEN typeof(minutes_json)='text' AND length(CAST(minutes_json AS BLOB))<=1024 THEN minutes_json ELSE '' END AS minutes_json
        FROM work_models WHERE user_id=? AND valid_from<=? ORDER BY work_models.valid_from,id");
    try { $s->execute([$owner,$through]);$models=$s->fetchAll(PDO::FETCH_ASSOC); }
    finally { $s->closeCursor(); }
    $s=$db->prepare('SELECT datum FROM Feiertage WHERE datum>=? AND datum<=?');
    try { $s->execute([$from,$through]);$holidays=array_fill_keys($s->fetchAll(PDO::FETCH_COLUMN),true); }
    finally { $s->closeCursor(); }
    $fallback=att_one($db,'SELECT regelarbeitszeit FROM users WHERE id=?',[$owner]);
    $timelineValid=true;
    foreach ($models as $model) {
        try { api_work_model_day($model['valid_from']); }
        catch (Throwable) { $timelineValid=false;break; }
    }
    $index=0;$effective=null;$checked=[];
    foreach ($daily as $day=>&$values) {
        while ($index<count($models) && $models[$index]['valid_from']<=$day) { $effective=$models[$index++]; }
        if (!$timelineValid) { $issues['model']++;continue; }
        if (!isset($holidays[$day])) {
            try {
                if ($effective!==null) {
                    $id=$effective['model_id'];
                    if (!isset($checked[$id])) { api_work_model_row($effective);$checked[$id]=true; }
                } elseif (!$fallback || !is_numeric($fallback['regelarbeitszeit'])
                    || !is_finite((float)$fallback['regelarbeitszeit']) || (float)$fallback['regelarbeitszeit']<0 || (float)$fallback['regelarbeitszeit']>24) {
                    throw new UnexpectedValueException('Unclear stored fallback.');
                }
            } catch (Throwable) { $issues['model']++;continue; }
        }
        $values['target_minutes']=isset($holidays[$day])?0:planned_minutes_value(practice_date($day),$effective,$fallback);
    }
    unset($values);
    $totals=null;
    if (array_sum($issues)===0) {
        $totals=['work_minutes'=>0,'credit_minutes'=>0,'target_minutes'=>0,'unrecorded_target_days'=>0];
        foreach ($daily as $values) {
            foreach (['work_minutes','credit_minutes','target_minutes'] as $key) {
                if ($totals[$key]>PHP_INT_MAX-$values[$key]) { throw new UnexpectedValueException('Unrepresentable own comparison.'); }
                $totals[$key]+=$values[$key];
            }
            if ($values['records']===0 && $values['target_minutes']>0) { $totals['unrecorded_target_days']++; }
        }
        if ($totals['work_minutes']>PHP_INT_MAX-$totals['credit_minutes']) { throw new UnexpectedValueException('Unrepresentable own comparison.'); }
        $totals['difference_minutes']=$totals['work_minutes']+$totals['credit_minutes']-$totals['target_minutes'];
    }
    return ['from'=>$from,'through'=>$through,'loaded_at'=>$loaded->setTimezone(new DateTimeZone('Europe/Berlin'))->format(DateTimeInterface::ATOM),
        'issues'=>$issues,'totals'=>$totals,'days'=>$totals===null?[]:$daily];
}

/** Native current session fingerprint and known role, in the same owned read snapshot. */
function native_own_period_comparison(PDO $db,int $actor,#[SensitiveParameter] mixed $version,string $from,string $through): array
{
    $loaded=new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin'));
    own_period_comparison_range($from,$through,$loaded);
    if ($db->inTransaction()) { throw new LogicException('Own comparison requires an idle connection.'); }
    if (!$db->beginTransaction()) { throw new RuntimeException('Own comparison unavailable.'); }
    try {
        $current=att_one($db,'SELECT role,password FROM users WHERE id=?',[$actor]);
        if ($actor<1 || !$current || !is_string($version) || preg_match('/\A[a-f0-9]{64}\z/',$version)!==1
            || !is_string($current['password']) || !hash_equals($version,hash('sha256',$current['password']))) {
            throw new AttendanceError('Zeitraumvergleich nicht verfügbar. Bitte neu anmelden.',401);
        }
        if (!in_array($current['role'],['user','supervisor','admin'],true)) { throw new AttendanceError('Zeitraumvergleich nicht verfügbar.',403); }
        $result=own_period_comparison_snapshot($db,$actor,$from,$through,$loaded);
        if (!$db->commit()) { throw new RuntimeException('Own comparison unavailable.'); }
        return $result;
    } catch (Throwable $error) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $error;
    }
}
