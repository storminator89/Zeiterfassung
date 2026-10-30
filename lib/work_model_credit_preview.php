<?php
// Definitions only. Caller owns an authenticated, CSRF-checked read snapshot.
require_once __DIR__.'/attendance.php';
require_once __DIR__.'/practice.php';
require_once __DIR__.'/reporting.php';
require_once __DIR__.'/legacy_timestamp_assessment.php';

/** A paging submitter is always a read intent, never an unmarked Save. */
function work_model_preview_action(array $form): mixed {
    if(array_key_exists('preview_page',$form)){
        return !array_key_exists('model_action',$form)||$form['model_action']==='preview'?'preview':null;
    }
    return array_key_exists('model_action',$form)?$form['model_action']:'save';
}

/** Bounded redisplay values, never writer authority or a replacement command. */
function work_model_preview_form_values(array $command, string $today): array {
    $values=['user_id'=>'','valid_from'=>$today,'reason'=>''];
    foreach(range(1,7) as $day){$values['day'.$day]='0';}
    foreach($values as $key=>$default){
        if(array_key_exists($key,$command)){
            $values[$key]=is_string($command[$key])&&strlen($command[$key])<=1000?$command[$key]:'';
        }
    }
    return $values;
}

/** Hypothetical daily target versus retained net duration, not a recalculation. */
function work_model_credit_preview_locked(PDO $db, int $actorId, #[SensitiveParameter] mixed $version, array $command, mixed $requestedPage=null): array {
    $paged=func_num_args()===5;
    if(!$db->inTransaction()){throw new LogicException('An existing read snapshot is required.');}
    $actor=att_one($db,'SELECT role,password FROM users WHERE id=?',[$actorId]);
    if($actorId<1||!$actor||$actor['role']!=='admin'||!is_string($version)
        ||preg_match('/\A[a-f0-9]{64}\z/',$version)!==1||!is_string($actor['password'])||$actor['password']===''
        ||!hash_equals($version,hash('sha256',$actor['password']))){
        throw new AttendanceError('Sitzung nicht mehr gültig. Bitte neu anmelden.',401);
    }
    $page=1;
    if($paged){
        if(!is_string($requestedPage)||preg_match('/\A[1-9][0-9]*\z/',$requestedPage)!==1
            ||($page=filter_var($requestedPage,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]))===false){
            throw new AttendanceError('Ungültige Vorschauseite. Die Vorschau wurde nicht ausgeführt.',400);
        }
    }
    $target=is_string($command['user_id']??null)?filter_var($command['user_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
    $from=$command['valid_from']??null;
    if($target===false||!is_string($from)||preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/',$from)!==1){throw new AttendanceError('Person und Gültigkeitsdatum prüfen.',400);}
    $start=practice_date($from);
    $minutes=[];
    foreach(range(1,7) as $day){
        $value=is_string($command['day'.$day]??null)?filter_var($command['day'.$day],FILTER_VALIDATE_INT):false;
        if($value===false||$value<0||$value>1440){throw new AttendanceError('Sollminuten müssen zwischen 0 und 1440 liegen.',400);}
        $minutes[]=$value;
    }
    $person=att_one($db,'SELECT id,username FROM users WHERE id=?',[$target]);
    if(!$person){throw new AttendanceError('Person ist nicht mehr verfügbar. Bitte neu auswählen.',409);}
    $next=att_one($db,'SELECT valid_from FROM work_models WHERE user_id=? AND valid_from>? ORDER BY valid_from,id LIMIT 1',[$target,$from]);
    $until=$next?practice_date($next['valid_from']):null;
    if(!$db->createFunction('chrono_model_credit_epoch','report_stored_epoch',1)){throw new RuntimeException('Preview timestamp reader unavailable.');}
    $absence="(COALESCE(absence_kind,'') NOT IN ('','0') OR beschreibung IN ('Urlaub','Krankheit','Feiertag'))";
    $range='chrono_model_credit_epoch(startzeit)>=CAST(? AS INTEGER)';$parameters=[$target,$start->getTimestamp()];
    if($until){$range.=' AND chrono_model_credit_epoch(startzeit)<CAST(? AS INTEGER)';$parameters[]=$until->getTimestamp();}
    // Preserve the existing model-section selection, including unplaceable starts.
    $where='user_id=? AND '.$absence.' AND (('.$range.') OR chrono_model_credit_epoch(startzeit) IS NULL)';
    $total=null;$pages=1;$offset=0;
    if($paged){
        $statement=$db->prepare('SELECT COUNT(*) FROM zeiterfassung WHERE '.$where);$statement->execute($parameters);$total=(int)$statement->fetchColumn();$statement->closeCursor();
        $pages=max(1,intdiv($total,100)+($total%100===0?0:1));$page=min($page,$pages);$offset=($page-1)*100;
    }
    $statement=$db->prepare('SELECT id,user_id,startzeit,endzeit,pause,beschreibung,absence_kind,planned_minutes FROM zeiterfassung WHERE '.$where.' ORDER BY (chrono_model_credit_epoch(startzeit) IS NULL) DESC,chrono_model_credit_epoch(startzeit),id LIMIT 101 OFFSET '.$offset);
    $statement->execute($parameters);$records=$statement->fetchAll(PDO::FETCH_ASSOC);$statement->closeCursor();
    $truncated=$paged?$total>100:count($records)>100;$records=array_slice($records,0,100);$dates=[];$items=[];
    foreach($records as $record){
        $calculation=report_record_calculation($record);
        $startSupported=legacy_timestamp_assessment($record['startzeit'])['category']===null;
        $endMissing=$record['endzeit']===null||$record['endzeit']==='';
        $timestampReview=!$startSupported||(!$endMissing&&legacy_timestamp_assessment($record['endzeit'])['category']!==null);
        $day=$startSupported?$calculation['day']:null;
        if($day!==null){$dates[$day]=true;}
        $items[]=['id'=>(int)$record['id'],'day'=>$day,'stored_minutes'=>$calculation['status']==='completed'&&!$timestampReview?$calculation['minutes']:null,'timestamp_review'=>$timestampReview];
    }
    $holidays=[];
    if($dates){
        $statement=$db->prepare('SELECT datum FROM Feiertage WHERE datum IN ('.implode(',',array_fill(0,count($dates),'?')).')');
        $statement->execute(array_keys($dates));
        foreach($statement->fetchAll(PDO::FETCH_ASSOC) as $holiday){$holidays[$holiday['datum']]=true;}$statement->closeCursor();
    }
    $candidate=['minutes_json'=>json_encode($minutes,JSON_THROW_ON_ERROR)];$unknown=false;
    foreach($items as &$item){
        $item['hypothetical_target']=$item['day']===null?null:(isset($holidays[$item['day']])?0:planned_minutes_value(practice_date($item['day']),$candidate,null));
        $item['difference']=$item['stored_minutes']===null||$item['hypothetical_target']===null?null:$item['hypothetical_target']-$item['stored_minutes'];
        $unknown=$unknown||$item['difference']===null;
    }unset($item);
    $result=['person_id'=>(int)$person['id'],'username'=>$person['username'],'from'=>$from,'until'=>$until?->format('Y-m-d'),'items'=>$items,'truncated'=>$truncated,'unknown'=>$unknown];
    return $paged?$result+['pagination_version'=>1,'total'=>$total,'page'=>$page,'pages'=>$pages,'page_size'=>100]:$result;
}
