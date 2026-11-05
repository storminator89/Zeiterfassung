<?php
namespace App\Chrono;

/** Own, already-authorized snapshot in. Display only; never a booking/account writer. */
final class TodayProgress
{
    public static function fromRecords(array $records, array $status, ?array $live, string $day, int $targetMinutes, int $now): ?array
    {
        require_once dirname(__DIR__,3).'/lib/reporting.php';
        if ($targetMinutes < 0 || $targetMinutes > 1440 || $now < 0
            || !in_array($status['state'] ?? null,['absent','working','paused'],true)) return null;
        if (\report_conflicts_in_period(\report_absence_conflicts($records),$day,$day)) return null;
        $work=0; $credit=0;
        foreach ($records as $record) {
            $row=\report_record_calculation($record);
            if ($row['status']==='invalid' && ($row['day']===null || $row['day']===$day)) return null;
            // An overnight row cannot allocate its aggregate pause to calendar days.
            if ($row['start']!==null && $row['end']!==null && $row['status']==='completed') {
                $endDay=\booking_local_date($row['end'])->format('Y-m-d');
                if ($row['day']!==$endDay && $row['day']<=$day && $endDay>=$day) return null;
            }
            if ($row['day']!==$day || $row['status']!=='completed') continue;
            if (!empty($record['absence_kind'])) $credit += $row['minutes'] * 60;
            else $work += $row['minutes'] * 60;
        }
        $running=($status['state']??'absent')!=='absent';
        if ($running && $credit > 0) return null;
        if ($running) {
            if ($live===null || !is_int($live['started_at_epoch']??null)
                || \booking_local_date($live['started_at_epoch'])->format('Y-m-d')!==$day
                || !is_int($live['elapsed_work_seconds']??null)) return null;
            $work += $live['elapsed_work_seconds'];
        }
        if ($work<0 || $credit<0 || $work>PHP_INT_MAX-$credit) return null;
        return ['version'=>1,'day'=>$day,'work_seconds'=>$work,'credit_seconds'=>$credit,
            'target_seconds'=>$targetMinutes*60,'as_of_epoch'=>$now,'advancing'=>$running && $status['state']==='working'];
    }
}
