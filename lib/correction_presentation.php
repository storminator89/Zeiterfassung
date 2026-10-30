<?php
// Definitions only: selected, already-authorized request presentation; no lookups/writes.
require_once __DIR__.'/reporting.php';
require_once __DIR__.'/legacy_timestamp_assessment.php';

/** Only the three selected time fields, never arbitrary original JSON content. */
function chrono_correction_duration(array $record): array {
    $unavailable=['state'=>'unavailable','text'=>'Nicht auswertbar','minutes'=>null];
    if (!array_key_exists('startzeit',$record)||!array_key_exists('endzeit',$record)||!array_key_exists('pause',$record)
        ||!is_string($record['startzeit'])||(!is_string($record['endzeit'])&&$record['endzeit']!==null)
        ||(!is_int($record['pause'])&&!is_string($record['pause']))) { return $unavailable; }
    try {
        // A permissive report parse may choose an instant in a DST fold/gap.
        // The existing strict diagnosis must permit both persisted endpoints first.
        if (legacy_timestamp_assessment($record['startzeit'])['category']!==null
            || ($record['endzeit']!==null && $record['endzeit']!==''
                && legacy_timestamp_assessment($record['endzeit'])['category']!==null)) { return $unavailable; }
        $calculation=report_record_calculation($record);
        return match ($calculation['status']) {
            'completed'=>['state'=>'completed','text'=>report_format_hours($calculation['minutes']/60),'minutes'=>$calculation['minutes']],
            'open'=>['state'=>'open','text'=>'Offen – keine abgeschlossene Buchungsdauer','minutes'=>null],
            default=>$unavailable,
        };
    } catch (Throwable) { return $unavailable; }
}

/** Stored epoch representation only; date/instant interpretation stays canonical. */
function chrono_correction_preview_integer(mixed $value): int|false {
    if (!is_int($value)&&(!is_string($value)||strlen($value)>strlen((string)PHP_INT_MIN)||preg_match('/\A-?[0-9]+\z/',$value)!==1)) { return false; }
    return filter_var($value,FILTER_VALIDATE_INT);
}

/** Booking-duration comparison only, never an account balance or approval decision. */
function chrono_correction_preview(array $request): array {
    $before=['state'=>'unavailable','text'=>'Vorherige Buchungsdauer nicht auswertbar','minutes'=>null];
    $raw=$request['original_json']??null;
    if (array_key_exists('record_id',$request)&&$request['record_id']===null&&$raw===null) {
        $before=['state'=>'absent','text'=>'Kein vorheriger Eintrag (Nachtrag)','minutes'=>null];
    } elseif (is_string($raw)&&$raw!==''&&strlen($raw)<=65536) {
        try {
            $original=json_decode($raw,false,8,JSON_THROW_ON_ERROR);
            // Do not accept decoder last-value-wins or normalize stored snapshot bytes.
            if (!$original instanceof stdClass || json_encode($original,JSON_THROW_ON_ERROR)!==$raw) { $original=null; }
        }
        catch (JsonException) { $original=null; }
        if ($original instanceof stdClass) {
            $selected=[];
            foreach (['startzeit','endzeit','pause'] as $field) {
                if (property_exists($original,$field)) { $selected[$field]=$original->{$field}; }
            }
            $before=chrono_correction_duration($selected);
        }
    }

    $after=['state'=>'unavailable','text'=>'Beantragte Buchungsdauer nicht auswertbar','minutes'=>null];
    $cancel=$request['cancel']??null;
    if (in_array($cancel,[1,'1',true],true)) {
        $after=['state'=>'cancelled','text'=>'Stornierung – kein neuer Zeitwert','minutes'=>null];
    } elseif (in_array($cancel,[0,'0',false],true)) {
        $start=chrono_correction_preview_integer($request['proposed_start']??null);
        $end=chrono_correction_preview_integer($request['proposed_end']??null);
        $pause=chrono_correction_preview_integer($request['proposed_pause']??null);
        if ($start!==false&&$end!==false&&$pause!==false&&$end>$start) {
            try {
                $localStart=booking_local_date($start);$localEnd=booking_local_date($end);
                // The existing ISO display has minute offsets, not historic offset seconds.
                if ($localStart->getOffset()%60===0 && $localEnd->getOffset()%60===0) {
                    $after=chrono_correction_duration([
                        'startzeit'=>$localStart->format('Y-m-d\TH:i:sP'),
                        'endzeit'=>$localEnd->format('Y-m-d\TH:i:sP'),'pause'=>$pause,
                    ]);
                }
            } catch (Throwable) { /* Fixed unavailable text, never source data or exception detail. */ }
        }
    }
    $difference=null;
    if ($before['state']==='completed'&&$after['state']==='completed') {
        $minutes=$after['minutes']-$before['minutes'];
        $difference=($minutes>0?'+':'').$minutes.' min';
    }
    return ['before'=>$before['text'],'after'=>$after['text'],'difference'=>$difference,'proposed_usable'=>$after['state']==='completed'];
}


/** Selected stored lifecycle fields only; never a clock, duration or decision authority. */
function chrono_correction_lifecycle(array $request): array {
    $stamp=static function(mixed $value): ?string {
        $epoch=chrono_correction_preview_integer($value);
        if ($epoch===false) { return null; }
        try {
            $date=booking_local_date($epoch);
            $year=$date->format('Y');
            // No coerced epoch zero, expanded year or lossy sub-minute historical offset.
            if (strlen($year)!==4 || $year==='0000' || $date->getOffset()%60!==0) { return null; }
            return $date->format('Y-m-d\TH:i:sP');
        } catch (Throwable) { return null; }
    };
    $kind=match ($request['status']??null) {
        'approved','rejected'=>'decision',
        'withdrawn'=>'withdrawal',
        default=>null,
    };
    return ['created'=>$stamp($request['created_at']??null),'resolved_kind'=>$kind,
        'resolved'=>$kind===null?null:$stamp($request['reviewed_at']??null)];
}
