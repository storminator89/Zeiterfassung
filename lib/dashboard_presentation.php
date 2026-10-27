<?php
// Definitions only. Partition already-authorized rows, never query or choose credit policy.
require_once __DIR__.'/reporting.php';

/**
 * Display components of existing report totals, not a second time account.
 * Canonical completed net minutes and whole-shift start-day allocation stay intact.
 * The caller must apply the SAME full-context conflict/incomplete mask as its totals.
 */
function chrono_dashboard_components(array $records,string $weekStart,string $weekEnd,string $monthStart,string $monthEnd): array {
    $periods=['week'=>[$weekStart,$weekEnd],'month'=>[$monthStart,$monthEnd]];
    $parts=['week_work_minutes'=>0,'week_absence_minutes'=>0,'month_work_minutes'=>0,'month_absence_minutes'=>0];
    foreach ($records as $record) {
        $calculation=report_record_calculation($record);
        if ($calculation['status']!=='completed') { continue; }
        $kind=booking_is_absence($record)?'absence':'work';
        foreach ($periods as $period=>[$from,$to]) {
            if ($calculation['day']>=$from && $calculation['day']<=$to) {
                $parts[$period.'_'.$kind.'_minutes']+=$calculation['minutes'];
            }
        }
    }
    return $parts;
}
