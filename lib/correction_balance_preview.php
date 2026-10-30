<?php
// Definitions only. Already-authorized own snapshot in; presentation only out.
require_once __DIR__.'/corrections.php';
require_once __DIR__.'/correction_presentation.php';
require_once __DIR__.'/home_projection.php';

/**
 * Hypothetical existing Home projection, never an approval or continuous account.
 * The trusted reader owns one current read snapshot. No writes or data repair.
 */
function chrono_correction_balance_preview(PDO $db, int $user, array $records, float $carryIn, array $request, int $now): array {
    $unavailable=['available'=>false,'before'=>null,'after'=>null,'difference'=>null];
    if (!is_finite($carryIn) || (int)($request['user_id']??0)!==$user || ($request['status']??null)!=='pending') { return $unavailable; }
    try {
        $recordId=$request['record_id']??null;
        if ($recordId!==null) {
            $recordId=chrono_correction_preview_integer($recordId);
            if ($recordId===false || $recordId<1) { return $unavailable; }
        }
        $old=null;$oldIndex=null;
        foreach ($records as $index=>$record) {
            // Do not accept a mixed-user or duplicate-id array from another caller.
            if ((int)($record['user_id']??0)!==$user) { return $unavailable; }
            // Only completed source rows contribute to Home. Do not certify a
            // numeric before/after from a persisted timestamp the strict preview refuses.
            $sourceEnd=$record['endzeit']??null;
            if ($sourceEnd!==null && $sourceEnd!==''
                && (legacy_timestamp_assessment($record['startzeit']??null)['category']!==null
                    || legacy_timestamp_assessment($sourceEnd)['category']!==null)) { return $unavailable; }
            if ($recordId!==null && (int)($record['id']??0)===$recordId) {
                if ($old!==null) { return $unavailable; }
                $old=$record;$oldIndex=$index;
            }
        }
        if ($recordId!==null) {
            // Same exact current-original comparison and active-session refusal as review.
            if ($old===null || json_encode($old)!==($request['original_json']??null)
                || att_one($db,'SELECT id FROM work_sessions WHERE record_id=? AND ended_at IS NULL',[$recordId])) { return $unavailable; }
        } elseif (($request['original_json']??null)!==null) { return $unavailable; }

        $cancel=$request['cancel']??null;
        if (!in_array($cancel,[0,'0',1,'1'],true)) { return $unavailable; }
        $cancel=(string)$cancel==='1';
        if ($cancel && $old===null) { return $unavailable; }
        $afterRecords=$records;
        if ($cancel) {
            unset($afterRecords[$oldIndex]);
        } else {
            // Reuse the existing strict proposal display/offset-fidelity gate;
            // never derive a numeric balance from its unavailable time projection.
            if (!chrono_correction_preview($request)['proposed_usable']) { return $unavailable; }
            $start=chrono_correction_preview_integer($request['proposed_start']??null);
            $end=chrono_correction_preview_integer($request['proposed_end']??null);
            $pause=chrono_correction_preview_integer($request['proposed_pause']??null);
            if ($start===false || $end===false || $pause===false) { return $unavailable; }
            booking_validate_correction($old,$start,$end,$pause,$now);
            // Identical canonical span/overlap read as the existing approval core.
            $proposal=['startzeit'=>attendance_local($start),'endzeit'=>attendance_local($end),
                'absence_kind'=>$old['absence_kind']??null,'beschreibung'=>$old['beschreibung']??''];
            [$from,$to]=booking_span($proposal);
            booking_assert_no_overlap($db,$user,$from,$to,$recordId??0);
            if ($old!==null) {
                $afterRecords[$oldIndex]=array_replace($old,[
                    'startzeit'=>$proposal['startzeit'],'endzeit'=>$proposal['endzeit'],'pause'=>$pause,
                    'planned_minutes'=>!empty($old['absence_kind'])?(int)floor(($end-$start)/60)-$pause:null,
                ]);
            } else {
                $afterRecords[]=['id'=>0,'user_id'=>$user,'startzeit'=>$proposal['startzeit'],
                    'endzeit'=>$proposal['endzeit'],'pause'=>$pause,'beschreibung'=>'Korrektur freigegeben',
                    'standort'=>'office','absence_kind'=>null,'planned_minutes'=>null];
            }
        }
        // Recompute both COMPLETE start-day projections: a new/removed/moved day
        // changes day Soll, so adding only a duration delta would be incorrect.
        $beforeDays=report_days($records,$beforeInvalid,$beforeConflicts);
        $afterDays=report_days(array_values($afterRecords),$afterInvalid,$afterConflicts);
        if ($beforeInvalid!==[] || $afterInvalid!==[] || $beforeConflicts!==[] || $afterConflicts!==[]) { return $unavailable; }
        $before=home_total_overtime($db,$user,$beforeDays,$carryIn);
        $after=home_total_overtime($db,$user,$afterDays,$carryIn);
        if (!is_finite($before) || !is_finite($after)) { return $unavailable; }
        $difference=$after-$before;
        return ['available'=>true,'before'=>report_format_hours($before),'after'=>report_format_hours($after),
            'difference'=>($difference>0?'+':'').report_format_hours($difference)];
    } catch (Throwable) {
        // No exception text, raw row, partial total or guessed zero in the UI.
        return $unavailable;
    }
}
