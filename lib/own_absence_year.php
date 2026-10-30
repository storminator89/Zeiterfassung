<?php
// Definitions only: stored own-credit review, never entitlement, approval or a writer.
require_once __DIR__.'/time_record_review.php';

function chrono_own_absence_year_selection(mixed $year, mixed $page, DateTimeImmutable $loaded): array
{
    $year ??= $loaded->setTimezone(new DateTimeZone('Europe/Berlin'))->format('Y');
    if (!is_string($year) || preg_match('/\A[0-9]{1,4}\z/', $year)!==1 || (int)$year<1) {
        throw new InvalidArgumentException('Invalid own absence year.');
    }
    $page ??= '1';
    if (!is_string($page) || preg_match('/\A[1-9][0-9]{0,17}\z/', $page)!==1) {
        throw new InvalidArgumentException('Invalid own absence page.');
    }
    return ['year'=>sprintf('%04d',(int)$year), 'page'=>(int)$page];
}

function chrono_own_absence_year_day(mixed $value): ?string
{
    if (legacy_timestamp_assessment($value)['category']!==null) { return null; }
    try { $day=booking_local_date(booking_time($value))->format('Y-m-d'); }
    catch (Throwable) { return null; }
    return preg_match('/\A(?!0000)[0-9]{4}-[0-9]{2}-[0-9]{2}\z/',$day)===1?$day:null;
}

/** Bound interpretation fields identically for SQL and already-loaded Native own rows. */
function chrono_own_absence_year_bounded(array $row): array
{
    $bounded=[];
    foreach (['id','user_id'] as $key) { $bounded[$key]=$row[$key]??null; }
    foreach (['startzeit','endzeit'] as $key) {
        $raw=$row[$key]??null;
        $bounded[$key]=is_string($raw)&&strlen($raw)<=25?$raw:null;
        if ($key==='endzeit' && $raw!==null && $raw!=='' && $bounded[$key]===null) { $bounded[$key]='unknown'; }
    }
    $pause=$row['pause']??null;
    $bounded['pause_bounded']=($row['pause_bounded']??1)===1 && ($pause===null || ((is_string($pause)||is_int($pause))&&strlen((string)$pause)<=20));
    $bounded['pause']=$bounded['pause_bounded']?$pause:null;
    $kind=$row['absence_kind']??null;
    $bounded['absence_kind']=$kind===null?null:((is_string($kind)||is_int($kind))&&strlen((string)$kind)<=32?$kind:'stored-credit');
    $description=$row['beschreibung']??null;
    $bounded['beschreibung']=in_array($description,['Urlaub','Krankheit','Feiertag'],true)?$description:'';
    return $bounded;
}

/** Complete already-authorized own context BEFORE year/page selection; no new credit formula. */
function chrono_own_absence_year_projection(array $authorizedRows, mixed $year, mixed $page, DateTimeImmutable $loaded): array
{
    $selection=chrono_own_absence_year_selection($year,$page,$loaded);
    $today=$loaded->setTimezone(new DateTimeZone('Europe/Berlin'))->format('Y-m-d');
    $rows=array_map('chrono_own_absence_year_bounded',$authorizedRows);
    $conflicts=report_conflict_lookup(report_absence_conflicts($rows));
    $incomplete=false;$unresolved=false;$unknown=0;$visible=[];$positiveDays=[];
    $summary=[];
    foreach (['vacation','sickness','holiday','other'] as $kind) {
        foreach (['through_today','after_today'] as $phase) { $summary[$kind][$phase]=['records'=>0,'positive_days'=>0,'minutes'=>0]; }
    }
    foreach ($rows as $row) {
        $day=chrono_own_absence_year_day($row['startzeit']);
        if (!booking_is_absence($row)) {
            // An unplaceable/bad work span cannot certify absence conflicts as absent.
            // An invalid end/pause may reach into another year; start-year filtering
            // cannot prove that it is outside the selected year's credits.
            if ($day===null || time_record_local_time_findings($row)!==[]
                || report_record_calculation($row)['status']==='invalid' || !$row['pause_bounded']) { $incomplete=true; }
            continue;
        }
        if ($day!==null && substr($day,0,4)!==$selection['year']) { continue; }
        $rawKind=!empty($row['absence_kind'])?$row['absence_kind']:$row['beschreibung'];
        $kind=['Urlaub'=>'vacation','Krankheit'=>'sickness','Feiertag'=>'holiday'][$rawKind]??'other';
        $phase=$day===null?'unknown':($day<=$today?'through_today':'after_today');
        $calculation=report_record_calculation($row);
        $valid=$day!==null && $row['pause_bounded'] && time_record_local_time_findings($row)===[] && $calculation['status']==='completed';
        $id=(string)($row['id']??'');
        if (preg_match('/\A[1-9][0-9]{0,18}\z/',$id)!==1 || (strlen($id)===19 && strcmp($id,'9223372036854775807')>0)) { $id=null;$valid=false; }
        $conflict=isset($conflicts[report_conflict_key($row)]);
        if ($day===null) { $unknown++; }
        if (!$valid) { $incomplete=true; }
        if ($conflict) { $unresolved=true; }
        if ($phase!=='unknown') {
            $summary[$kind][$phase]['records']++;
            if ($valid) {
                $minutes=$calculation['minutes'];
                if ($summary[$kind][$phase]['minutes']>PHP_INT_MAX-$minutes) { $incomplete=true; }
                else { $summary[$kind][$phase]['minutes']+=$minutes; }
                if ($minutes>0) { $positiveDays[$kind][$phase][$day]=true; }
            }
        }
        $visible[]=['id'=>$id,'day'=>$day,'kind'=>$kind,'phase'=>$phase,'minutes'=>$valid?$calculation['minutes']:null,'state'=>$conflict?'unresolved':($valid?'current':'incomplete')];
    }
    // Multiple positive credit projections for one day are not silently double-counted.
    $creditedDays=[];$duplicateDays=[];
    foreach ($visible as $row) {
        if ($row['day']!==null && $row['minutes']!==null && $row['minutes']>0) {
            if (isset($creditedDays[$row['day']])) { $unresolved=true;$duplicateDays[$row['day']]=true; }
            $creditedDays[$row['day']]=true;
        }
    }
    foreach ($visible as &$row) {
        if ($row['day']!==null && isset($duplicateDays[$row['day']]) && $row['minutes']!==null && $row['minutes']>0) { $row['state']='unresolved'; }
    }
    unset($row);
    $state=$unresolved?'unresolved':($incomplete?'incomplete':'current');
    foreach ($summary as $kind=>&$phases) {
        foreach ($phases as $phase=>&$values) {
            $values['positive_days']=$state==='current'?count($positiveDays[$kind][$phase]??[]):null;
            if ($state!=='current') { $values['minutes']=null; }
        }
        unset($values);
    }
    unset($phases,$rows,$conflicts,$positiveDays,$creditedDays,$duplicateDays);
    usort($visible,static function(array $a,array $b):int {
        if (($a['day']===null)!==($b['day']===null)) { return $a['day']===null?-1:1; }
        return strcmp($b['day']??'',$a['day']??'') ?: (strlen($b['id']??'')<=>strlen($a['id']??'')) ?: strcmp($b['id']??'',$a['id']??'');
    });
    $total=count($visible);$pages=max(1,(int)ceil($total/25));$selected=min($selection['page'],$pages);
    return ['year'=>$selection['year'],'today'=>$today,'loaded_at'=>$loaded->setTimezone(new DateTimeZone('Europe/Berlin'))->format(DateTimeInterface::ATOM),
        'state'=>$state,'summary'=>$summary,'unknown_date_records'=>$unknown,'total_records'=>$total,'page'=>$selected,'pages'=>$pages,
        'rows'=>array_slice($visible,($selected-1)*25,25)];
}

/** Caller owns the existing authenticated read snapshot; owner never comes from the query. */
function chrono_own_absence_year_snapshot(PDO $db,int $owner,mixed $year,mixed $page,DateTimeImmutable $loaded): array
{
    chrono_own_absence_year_selection($year,$page,$loaded);
    if (!$db->inTransaction() || $owner<1) { throw new LogicException('An authenticated own snapshot is required.'); }
    $s=$db->prepare("SELECT CAST(id AS TEXT) AS id,user_id,
        CASE WHEN typeof(startzeit) IN ('text','blob') AND length(CAST(startzeit AS BLOB))<=25 THEN startzeit ELSE NULL END AS startzeit,
        CASE WHEN endzeit IS NULL OR endzeit='' THEN endzeit WHEN typeof(endzeit) IN ('text','blob') AND length(CAST(endzeit AS BLOB))<=25 THEN endzeit ELSE 'unknown' END AS endzeit,
        CASE WHEN pause IS NULL OR length(CAST(pause AS BLOB))<=20 THEN pause ELSE NULL END AS pause,
        CASE WHEN pause IS NULL OR length(CAST(pause AS BLOB))<=20 THEN 1 ELSE 0 END AS pause_bounded,
        CASE WHEN length(CAST(absence_kind AS BLOB))<=32 THEN absence_kind WHEN absence_kind IS NULL THEN NULL ELSE 'stored-credit' END AS absence_kind,
        CASE WHEN CAST(beschreibung AS TEXT) IN ('Urlaub','Krankheit','Feiertag') THEN CAST(beschreibung AS TEXT) ELSE '' END AS beschreibung
        FROM zeiterfassung WHERE user_id=? ORDER BY id DESC");
    try { $s->execute([$owner]);$rows=$s->fetchAll(PDO::FETCH_ASSOC); }
    finally { $s->closeCursor(); }
    return chrono_own_absence_year_projection($rows,$year,$page,$loaded);
}
