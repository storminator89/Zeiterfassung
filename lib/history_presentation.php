<?php
/** Read-only selected audit presentation. No bootstrap, lookup, mutation or HTML. */
function chrono_history_presentation_fields(string $entity): array {
    $booking = [
        'id'=>['Datensatz-ID','scalar'], 'user_id'=>['Person-ID','scalar'],
        'startzeit'=>['Beginn','scalar'], 'endzeit'=>['Ende','scalar'],
        'pause'=>['Pause (Minuten)','scalar'], 'beschreibung'=>['Notiz','scalar'],
        'standort'=>['Arbeitsort','scalar'], 'absence_kind'=>['Abwesenheitsart','scalar'],
        'planned_minutes'=>['Gespeicherte Gutschrift (Minuten)','scalar'],
    ];
    return match ($entity) {
        'zeiterfassung'=>$booking,
        'users'=>['id'=>['Person-ID','scalar'], 'username'=>['Benutzername','scalar'],
            'regelarbeitszeit'=>['Tages-Soll (Stunden)','scalar'], 'ueberstunden'=>['Anfangsguthaben (Stunden)','scalar'],
            'role'=>['Rolle','scalar'], 'department_id'=>['Abteilungs-ID','scalar'], 'supervisor_id'=>['Vorgesetzten-ID','scalar']],
        'Feiertage'=>['id'=>['Datensatz-ID','scalar'], 'datum'=>['Datum','scalar'], 'name'=>['Bezeichnung','scalar']],
        'pause_settings'=>['id'=>['Regel-ID','scalar'], 'hours_threshold'=>['Stundengrenze','scalar'], 'minimum_pause'=>['Mindestpause (Minuten)','scalar']],
        'work_sessions'=>['id'=>['Intervall-ID','scalar'], 'user_id'=>['Person-ID','scalar'], 'record_id'=>['Buchungs-ID','scalar'],
            'started_at'=>['Beginn (Unixsekunden)','scalar'], 'ended_at'=>['Ende (Unixsekunden)','scalar']],
        'break_intervals'=>['id'=>['Pausen-ID','scalar'], 'session_id'=>['Intervall-ID','scalar'],
            'started_at'=>['Beginn (Unixsekunden)','scalar'], 'ended_at'=>['Ende (Unixsekunden)','scalar']],
        'time_adjustments'=>['id'=>['Antrags-ID','scalar'], 'user_id'=>['Person-ID','scalar'], 'record_id'=>['Buchungs-ID','scalar'],
            'proposed_start'=>['Beantragter Beginn (Unixsekunden)','scalar'], 'proposed_end'=>['Beantragtes Ende (Unixsekunden)','scalar'],
            'proposed_pause'=>['Beantragte Pause (Minuten)','scalar'], 'reason'=>['Antragsbegründung','scalar'],
            'status'=>['Status','scalar'], 'reviewer_id'=>['Entscheidende Person-ID','scalar'],
            'reviewed_at'=>['Entscheidungs-/Rücknahmezeit (Unixsekunden)','scalar'], 'review_reason'=>['Entscheidungs-/Rücknahmebegründung','scalar'],
            'original_json'=>['Ursprüngliche Buchung','booking'], 'created_at'=>['Antragszeit (Unixsekunden)','scalar'],
            'cancel'=>['Storno beantragt','scalar']],
        'work_models'=>['id'=>['Modell-ID','scalar'], 'user_id'=>['Person-ID','scalar'], 'valid_from'=>['Gültig ab','scalar'],
            'minutes_json'=>['Sollminuten je Wochentag','week'], 'reason'=>['Begründung','scalar'],
            'created_by'=>['Erstellende Person-ID','scalar'], 'created_at'=>['Erstellungszeit (Unixsekunden)','scalar']],
        default=>[],
    };
}

/** Only bounded standard JSON, never PHP deserialization or guessed encodings. */
function chrono_history_decode_presentation(mixed $json): mixed {
    if (!is_string($json) || $json==='' || strlen($json)>65536) { return false; }
    try { return json_decode($json, false, 8, JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING); }
    catch (JsonException) { return false; }
}

function chrono_history_selected_scalar(mixed $value, bool &$omitted): array {
    if ((!is_scalar($value) && $value!==null) || (is_float($value) && !is_finite($value))) {
        $omitted=true;
        return ['value'=>'[Nicht darstellbar]', 'text'=>'[Nicht darstellbar]', 'signature'=>'hidden'];
    }
    // Compare the complete permitted scalar, not only its shortened display.
    $signature=get_debug_type($value).':'.json_encode($value,JSON_PRESERVE_ZERO_FRACTION);
    if (is_string($value)) {
        preg_match('/\A.{0,512}/us',$value,$match);
        $short=$match[0]??'';
        if ($short!==$value) { $value=$short.' … [gekürzt]';$omitted=true; }
    }
    $text=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);
    return ['value'=>$value, 'text'=>$text, 'signature'=>$signature];
}

/** Fixed one-level booking projection; never recursively discover arbitrary keys. */
function chrono_history_select_presentation(stdClass $object,array $fields,bool &$omitted,string $prefix='',string $labelPrefix=''): array {
    $cells=[];$selected=new stdClass;
    foreach (get_object_vars($object) as $key=>$_) {
        if (!array_key_exists($key,$fields)) { $omitted=true; }
    }
    foreach ($fields as $key=>[$label,$kind]) {
        if (!property_exists($object,$key)) { continue; }
        $value=$object->{$key};$path=$prefix.$key;$fieldLabel=$labelPrefix.$label;
        if ($kind==='scalar' || $value===null) {
            $cell=chrono_history_selected_scalar($value,$omitted);
            $selected->{$key}=$cell['value'];$cells[$path]=['label'=>$fieldLabel]+$cell;
            continue;
        }
        $nested=is_string($value)?chrono_history_decode_presentation($value):$value;
        if ($kind==='booking' && $nested instanceof stdClass) {
            $bookingFields=chrono_history_presentation_fields('zeiterfassung');
            if ($path==='original_json') { $bookingFields['_chrono_metadata_request_v1']=['Beantragte Metadaten (v1)','metadata_proposal_v1']; }
            [$childSelected,$childCells]=chrono_history_select_presentation($nested,$bookingFields,$omitted,$path.'.',$fieldLabel.' / ');
            $selected->{$key}=$childSelected;
            $cells[$path]=['label'=>$fieldLabel,'text'=>'Ausgewählte Buchungsfelder','signature'=>'booking'];
            $cells+=$childCells;
            continue;
        }
        if ($kind==='metadata_proposal_v1') {
            try {
                require_once __DIR__.'/correction_metadata.php';
                $metadata=correction_metadata_v1_values($nested);
                $selected->{$key}=(object)(['version'=>1]+$metadata);
                $cells[$path]=['label'=>$fieldLabel,'text'=>'Nur Ort und Notiz beantragt','signature'=>'metadata-v1'];
                foreach (['location'=>'Beantragter Arbeitsort','note'=>'Beantragte Notiz'] as $name=>$label) {
                    $cell=chrono_history_selected_scalar($metadata[$name],$omitted);
                    $selected->{$key}->{$name}=$cell['value'];$cells[$path.'.'.$name]=['label'=>$label]+$cell;
                }
                continue;
            } catch (Throwable) { /* Fixed unavailable marker below, never arbitrary JSON. */ }
        }
        if ($kind==='week' && is_array($nested) && array_is_list($nested) && count($nested)===7
            && count(array_filter($nested,static fn($day)=>is_int($day)||(is_float($day)&&is_finite($day))))===7) {
            $selected->{$key}=$nested;
            $cells[$path]=['label'=>$fieldLabel,'text'=>'Sieben Wochentagswerte','signature'=>'week'];
            foreach (['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'] as $index=>$day) {
                $cell=chrono_history_selected_scalar($nested[$index],$omitted);
                $cells[$path.'.'.$index]=['label'=>$fieldLabel.' / '.$day]+$cell;
            }
            continue;
        }
        $omitted=true;$selected->{$key}='[Nicht darstellbar]';
        $cells[$path]=['label'=>$fieldLabel,'text'=>'[Nicht darstellbar]','signature'=>'hidden'];
    }
    return [$selected,$cells];
}

function chrono_history_present_snapshot(mixed $json,array $fields): array {
    if ($json===null) { return ['text'=>'Kein Snapshot vorhanden','cells'=>[],'omitted'=>false,'state'=>'absent']; }
    $decoded=chrono_history_decode_presentation($json);
    if (!$fields || !($decoded instanceof stdClass)) {
        return ['text'=>'Snapshot nicht darstellbar','cells'=>[],'omitted'=>true,'state'=>'unavailable'];
    }
    $omitted=false;
    [$selected,$cells]=chrono_history_select_presentation($decoded,$fields,$omitted);
    return ['text'=>json_encode($selected,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION),
        'cells'=>$cells,'omitted'=>$omitted,'state'=>'selected'];
}

/** Caller supplies only already-authorized rows; SQL, scopes and paging stay elsewhere. */
function chrono_history_presentation(mixed $entity,mixed $before,mixed $after): array {
    $fields=is_string($entity)?chrono_history_presentation_fields($entity):[];
    $left=chrono_history_present_snapshot($before,$fields);$right=chrono_history_present_snapshot($after,$fields);
    $rows=[];
    // If a whole side is unrecognised, don't invent a missing/deleted-field diff.
    if ($left['state']!=='unavailable' && $right['state']!=='unavailable') {
        $hiddenParents=[];
        foreach ([$left['cells'],$right['cells']] as $side) {
            foreach ($side as $path=>$cell) {
                if ($cell['signature']==='hidden') { $hiddenParents[]=$path; }
            }
        }
        foreach (array_unique(array_merge(array_keys($left['cells']),array_keys($right['cells']))) as $path) {
            // Unreadable nested content is not evidence that its children were removed.
            foreach ($hiddenParents as $parent) {
                if (str_starts_with($path,$parent.'.')) { continue 2; }
            }
            $a=$left['cells'][$path]??null;$b=$right['cells'][$path]??null;
            if ($a!==null && $b!==null && $a['signature']===$b['signature']) { continue; }
            $rows[]=['field'=>($b??$a)['label'],'before'=>$a['text']??'Feld nicht vorhanden','after'=>$b['text']??'Feld nicht vorhanden'];
        }
    }
    return ['rows'=>$rows,'before'=>$left['text'],'after'=>$right['text'],
        'limited'=>$left['omitted']||$right['omitted'],
        'available'=>$left['state']!=='unavailable'&&$right['state']!=='unavailable'];
}
