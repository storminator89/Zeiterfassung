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
 */
function report_days(array $records, ?array &$invalidRecords=null): array {
    $hasDiagnostics=func_num_args()>1;
    if ($hasDiagnostics) { $invalidRecords=[]; }
    $days=[];
    foreach ($records as $record) {
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
        $day=$result['day'];$days[$day]=($days[$day]??0)+$result['minutes'];
    }
    ksort($days);return $days;
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
