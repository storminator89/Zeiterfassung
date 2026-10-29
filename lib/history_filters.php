<?php
/** Read-only audit filtering; no connection, session or live-record lookup. */
function chrono_history_entities(): array {
    return ['zeiterfassung'=>'Buchung','work_models'=>'Arbeitszeitmodell','time_adjustments'=>'Korrekturantrag','users'=>'Kontostammdaten','Feiertage'=>'Feiertagskalender','work_sessions'=>'Arbeitsintervall','break_intervals'=>'Pausenintervall','pause_settings'=>'Pausenregel','terminal_sites'=>'Terminal-Standort','terminal_settings'=>'Terminal-Modul','terminals'=>'Terminal-Gerät (redigiert)','ldap_settings'=>'LDAP-Konfiguration (redigiert)','user_intake_policy'=>'Aufnahmewege','api_service_policy'=>'API-Betriebsfreigabe'];
}

function chrono_history_operations(): array {
    return ['INSERT'=>'Angelegt','UPDATE'=>'Geändert','DELETE'=>'Gelöscht','BASELINE'=>'Übernahmestand'];
}

function chrono_history_filters(array $query): array {
    $values=[];$errors=[];$ids=[];
    foreach (['user_id'=>'Person-ID','record_id'=>'Datensatz-ID','request_id'=>'Antrags-ID','before'=>'Seitenmarke','entity'=>'Bereich','operation'=>'Vorgang','from'=>'Von-Datum','to'=>'Bis-Datum'] as $key=>$label) {
        $raw=array_key_exists($key,$query)?$query[$key]:'';
        if (!is_string($raw)||str_contains($raw,"\0")) { $errors[]="$label ist ungültig.";$raw=''; }
        $values[$key]=trim($raw);
        if (in_array($key,['user_id','record_id','request_id','before'],true)) {
            $id=$values[$key]===''?null:filter_var($values[$key],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if ($id===false) { $errors[]="$label muss eine positive ganze Zahl sein."; }
            $ids[$key]=$id;
        }
    }
    if ($values['entity']!==''&&!isset(chrono_history_entities()[$values['entity']])) { $errors[]='Bereich ist ungültig.'; }
    if ($values['operation']!==''&&!isset(chrono_history_operations()[$values['operation']])) { $errors[]='Vorgang ist ungültig.'; }
    $dates=[];
    foreach (['from'=>'Von-Datum','to'=>'Bis-Datum'] as $key=>$label) {
        $dates[$key]=null;
        if ($values[$key]==='') { continue; }
        $date=preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/',$values[$key])&&substr($values[$key],0,4)!=='0000'
            ?DateTimeImmutable::createFromFormat('!Y-m-d',$values[$key],new DateTimeZone('Europe/Berlin')):false;
        if (!$date||$date->format('Y-m-d')!==$values[$key]) { $errors[]="$label muss ein gültiges Datum (JJJJ-MM-TT) sein."; }
        else { $dates[$key]=$date; }
    }
    if ($dates['from']&&$dates['to']&&$dates['from']>$dates['to']) { $errors[]='Das Von-Datum darf nicht nach dem Bis-Datum liegen.'; }
    return ['values'=>$values,'ids'=>$ids,'from_at'=>$dates['from']?->getTimestamp(),'until_at'=>$dates['to']?->modify('+1 day')->getTimestamp(),'errors'=>$errors];
}

/** Optional filters only narrow the original native history scope and cursor. */
function chrono_history_query(array $filters,int $user,string $role): array {
    if ($filters['errors']) { throw new InvalidArgumentException('Ungültige Historienfilter.'); }
    $where=['a.id<?',"(a.user_id=? OR ?='admin' OR (?='supervisor' AND u.supervisor_id=?))"];
    $params=[$filters['ids']['before']??PHP_INT_MAX,$user,$role,$role,$user];
    foreach (['user_id','record_id'] as $key) {
        if ($filters['ids'][$key]!==null) { $where[]="a.$key=?";$params[]=$filters['ids'][$key]; }
    }
    if ($filters['values']['entity']!=='') { $where[]='a.entity=?';$params[]=$filters['values']['entity']; }
    if ($filters['values']['operation']!=='') { $where[]='a.operation=?';$params[]=$filters['values']['operation']; }
    // This is the audit row's own request ID, not an inferred booking relation.
    if ($filters['ids']['request_id']!==null) { $where[]="a.entity='time_adjustments' AND a.record_id=?";$params[]=$filters['ids']['request_id']; }
    if ($filters['from_at']!==null) { $where[]='a.occurred_at>=?';$params[]=$filters['from_at']; }
    if ($filters['until_at']!==null) { $where[]='a.occurred_at<?';$params[]=$filters['until_at']; }
    return ['sql'=>'SELECT a.* FROM audit_log a LEFT JOIN users u ON u.id=a.user_id WHERE '.implode(' AND ',$where).' ORDER BY a.id DESC LIMIT 100','params'=>$params];
}

function chrono_history_url(array $filters,?int $before=null): string {
    $params=array_filter($filters['values'],static fn($value,$key)=>$key!=='before'&&$value!=='',ARRAY_FILTER_USE_BOTH);
    if ($before!==null) { $params['before']=$before; }
    return 'history.php'.($params?'?'.http_build_query($params,'','&',PHP_QUERY_RFC3986):'');
}
