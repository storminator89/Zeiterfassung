<?php
require_once __DIR__.'/booking.php';
/** Persisted rows need an absolute date and time, never DateTime's relative "now". */
function report_stored_epoch($value): ?int {
    if (!is_string($value) || trim($value)==='') { return null; }
    $parts=date_parse($value);
    foreach (['year','month','day','hour','minute','second'] as $part) {
        if ($parts[$part]===false) { return null; }
    }
    if ($parts['warning_count'] || $parts['error_count'] || !empty($parts['relative'])) { return null; }
    try { return booking_time($value); }
    catch (Throwable $error) { return null; }
}
/**
 * Read-only checked result. Invalid and open are not legitimate zero-minute work.
 * Keep a parseable start-day on invalid end/pause rows so its total can be flagged.
 * report_day/report_week deliberately retain their existing throwing API contract.
 */
function report_record_calculation(array $record): array {
    $start=report_stored_epoch($record['startzeit']??null);
    $rawEnd=$record['endzeit']??null;
    $isOpen=$rawEnd===null || $rawEnd==='';
    $end=$isOpen ? null : report_stored_epoch($rawEnd);
    $pause=filter_var($record['pause']??0,FILTER_VALIDATE_INT);
    $result=['status'=>'invalid','error'=>null,'start'=>$start,'end'=>$end,
        'day'=>$start===null?null:booking_local_date($start)->format('Y-m-d'),
        'week'=>$start===null?null:booking_local_date($start)->format('o-W'),
        'minutes'=>null,'pause'=>$pause===false?null:$pause];
    if ($start===null) { $result['error']='Startzeit ist ungültig.'; }
    elseif (!$isOpen && $end===null) { $result['error']='Endzeit ist ungültig.'; }
    elseif ($pause===false || $pause<0) { $result['error']='Pause ist keine gültige Minutenzahl.'; }
    elseif ($isOpen) { $result['status']='open'; }
    elseif ($end<$start) { $result['error']='Endzeit liegt vor der Startzeit.'; }
    elseif (!is_int($end-$start)) { $result['error']='Zeitraum ist ungültig.'; }
    elseif ($pause>intdiv($end-$start,60)) { $result['error']='Pause überschreitet die Bruttozeit.'; }
    else {
        $result['status']='completed';
        $result['minutes']=intdiv($end-$start,60)-$pause;
    }
    return $result;
}
/**
 * Current effective projections, not immutable original punches, decide whether
 * a work/whole-day-absence collision is still unresolved after a correction.
 * Supply complete already-authorized rows BEFORE date/page filtering. Invalid
 * rows remain the separate data-quality diagnostic; open work is not credited.
 * Sweep half-open intervals (ends before starts) and never join different users.
 */
/** Internal builder: calculation must come from this operation's source row. */
function report_append_absence_conflict_event(array &$events,array $record,array $calculation,int $index): void {
    if ($calculation['status']!=='completed') { return; }
    $absence=booking_is_absence($record);
    if ($absence) {
        $day=booking_local_date($calculation['start'])->setTime(0,0);
        $from=$day->getTimestamp();$to=$day->modify('+1 day')->getTimestamp();
    } else { $from=$calculation['start'];$to=$calculation['end']; }
    if ($to<=$from) { return; }
    $user=(int)($record['user_id']??0);$kind=$absence?'absence':'work';
    $events[$user][]=[$from,1,$kind,$index];
    $events[$user][]=[$to,0,$kind,$index];
}
/** Internal sweep: records use the same dense indices as the event builder. */
function report_sweep_absence_conflict_events(array $records,array $events): array {
    $conflicts=[];
    foreach ($events as $user=>$timeline) {
        usort($timeline,static fn(array $a,array $b):int=>($a[0]<=>$b[0])?:($a[1]<=>$b[1])?:($a[3]<=>$b[3]));
        $active=['work'=>[],'absence'=>[]];
        foreach ($timeline as [$instant,$starts,$kind,$index]) {
            if (!$starts) { unset($active[$kind][$index]);continue; }
            $other=$kind==='work'?'absence':'work';
            foreach ($active[$other] as $otherIndex=>$_) {
                $work=$records[$kind==='work'?$index:$otherIndex];
                $absence=$records[$kind==='absence'?$index:$otherIndex];
                $conflicts[]=['user_id'=>(int)$user,'work_id'=>(int)($work['id']??0),
                    'absence_id'=>(int)($absence['id']??0),'work_day'=>report_day($work),
                    'absence_day'=>report_day($absence)];
            }
            $active[$kind][$index]=true;
        }
    }
    return $conflicts;
}
function report_absence_conflicts(array $records): array {
    $records=array_values($records);$events=[];
    foreach ($records as $index=>$record) {
        report_append_absence_conflict_event($events,$record,report_record_calculation($record),$index);
    }
    return report_sweep_absence_conflict_events($records,$events);
}
function report_conflict_key(array $record): string {
    return (int)($record['user_id']??0).':'.(int)($record['id']??0);
}
function report_conflict_lookup(array $conflicts): array {
    $lookup=[];
    foreach ($conflicts as $conflict) {
        foreach (['work_id','absence_id'] as $key) { $lookup[$conflict['user_id'].':'.$conflict[$key]]=true; }
    }
    return $lookup;
}
/** Both source start-days contribute to their existing respective report periods. */
function report_conflicts_in_period(array $conflicts,string $start,string $end): bool {
    foreach ($conflicts as $conflict) {
        foreach (['work_day','absence_day'] as $key) {
            if ($conflict[$key]>=$start && $conflict[$key]<=$end) { return true; }
        }
    }
    return false;
}
function report_assert_no_absence_conflicts(array $records): void {
    if (report_absence_conflicts($records)!==[]) {
        throw new UnexpectedValueException('Arbeitszeit und ganztägige Abwesenheit überschneiden sich. Betroffene Summen sind bis zur manuellen Klärung nicht abschließend; bitte Korrekturanträge verwenden.');
    }
}
/** Default/non-opted consumers must not turn malformed rows into partial totals. */
function report_minutes(array $record): ?int {
    $result=report_record_calculation($record);
    if ($result['status']==='invalid') {
        $start=$result['start'];$end=$result['end'];
        $legacyRejected=$start!==null && $end!==null && ($end<$start ||
            (is_int($end-$start) && intdiv($end-$start,60)-(int)($record['pause']??0)<0));
        if (!$legacyRejected) { throw new UnexpectedValueException('Ungültige Bestandszeit: Datenqualitätsprüfung erforderlich.'); }
    }
    return $result['minutes'];
}
function report_day(array $record): string { return booking_local_date(booking_time($record['startzeit']))->format('Y-m-d'); }
function report_week(array $record): string { return booking_local_date(booking_time($record['startzeit']))->format('o-W'); }
function report_week_bounds(string $date): array {
    $day=new DateTimeImmutable($date,new DateTimeZone('Europe/Berlin'));
    $monday=$day->modify('-'.((int)$day->format('N')-1).' days');
    return [$monday->format('Y-m-d'),$monday->modify('+6 days')->format('Y-m-d')];
}
/**
 * Completed shifts belong to their start day, including overnight shifts.
 * Opt in to collecting invalid rows only when the consumer visibly warns about
 * incomplete totals. Otherwise newly malformed data fails closed, not partial.
 * Previously rejected negative intervals/net minutes remain excluded as before.
 * A third output opts into separate work/absence conflict evidence; those
 * callers must mark affected totals unresolved without choosing either as zero.
 */
function report_days(array $records, ?array &$invalidRecords=null, ?array &$absenceConflicts=null): array {
    $hasDiagnostics=func_num_args()>1;
    if ($hasDiagnostics) { $invalidRecords=[]; }
    $records=array_values($records);$days=[];$events=[];
    foreach ($records as $index=>$record) {
        $result=report_record_calculation($record);
        if ($result['status']==='invalid') {
            if ($hasDiagnostics) {
                $invalidRecords[]=['id'=>(int)($record['id']??0),'error'=>$result['error']];
            } else {
                // Same fail-closed default as direct supervisor/formatter callers.
                report_minutes($record);
            }
            continue;
        }
        if ($result['status']!=='completed') { continue; }
        // Reuse this row's checked calculation; no second parse pass or cache.
        report_append_absence_conflict_event($events,$record,$result,$index);
        $day=$result['day'];$days[$day]=($days[$day]??0)+$result['minutes'];
    }
    $conflicts=report_sweep_absence_conflict_events($records,$events);
    if (func_num_args()>2) { $absenceConflicts=$conflicts; }
    elseif ($conflicts!==[]) {
        throw new UnexpectedValueException('Arbeitszeit und ganztägige Abwesenheit überschneiden sich. Betroffene Summen sind bis zur manuellen Klärung nicht abschließend; bitte Korrekturanträge verwenden.');
    }
    // Opted-in consumers keep the measured amounts, but MUST use the conflict
    // context to show affected totals as unresolved, not a final numeric credit.
    ksort($days);return $days;
}
/**
 * Non-diagnostic period consumers still fail closed on malformed data exactly
 * as report_days($records) did. Inspect full-history conflict context, but only
 * refuse periods containing either participating projection's report-start-day.
 */
function report_days_for_period(array $records,string $start,string $end): array {
    foreach ($records as $record) {
        if (report_record_calculation($record)['status']==='invalid') { report_minutes($record); }
    }
    $days=report_days($records,$invalidRecords,$conflicts);
    if (report_conflicts_in_period($conflicts,$start,$end)) {
        throw new UnexpectedValueException('Arbeitszeit und ganztägige Abwesenheit überschneiden sich im angefragten Zeitraum. Betroffene Summen sind bis zur manuellen Klärung nicht abschließend; bitte Korrekturanträge verwenden.');
    }
    return $days;
}
function report_period_minutes(array $days,string $start,string $end): int {
    $sum=0;foreach($days as $day=>$minutes) { if($day>=$start && $day<=$end) { $sum+=$minutes; } }return $sum;
}
function report_format_hours(float $hours): string {
    $minutes=(int)round(abs($hours)*60);
    return ($hours<0 && $minutes>0?'-':'').sprintf('%02d:%02d',intdiv($minutes,60),$minutes%60);
}
function report_percentage(float $actual,float $target): float {
    return $target>0 ? max(0,min(100,$actual/$target*100)) : 0.0;
}
