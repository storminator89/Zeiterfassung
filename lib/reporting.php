<?php
require_once __DIR__.'/booking.php';
/** Completed report rows only; open records must never turn NULL into "now". */
function report_minutes(array $record): ?int {
    if (empty($record['endzeit'])) { return null; }
    $start=booking_time($record['startzeit']);$end=booking_time($record['endzeit']);
    $minutes=intdiv($end-$start,60)-(int)($record['pause']??0);
    return $end<$start || $minutes<0 ? null : $minutes;
}
function report_day(array $record): string { return booking_local_date(booking_time($record['startzeit']))->format('Y-m-d'); }
function report_week(array $record): string { return booking_local_date(booking_time($record['startzeit']))->format('o-W'); }
function report_week_bounds(string $date): array {
    $day=new DateTimeImmutable($date,new DateTimeZone('Europe/Berlin'));
    $monday=$day->modify('-'.((int)$day->format('N')-1).' days');
    return [$monday->format('Y-m-d'),$monday->modify('+6 days')->format('Y-m-d')];
}
/** Existing reports assign completed shifts to their start day, including overnight shifts. */
function report_days(array $records): array {
    $days=[];
    foreach ($records as $record) {
        $minutes=report_minutes($record);if ($minutes===null) { continue; }
        $day=report_day($record);$days[$day]=($days[$day]??0)+$minutes;
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
