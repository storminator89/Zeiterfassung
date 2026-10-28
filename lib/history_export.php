<?php
/** Selected authorized history page only. No connection, lookup, file or HTTP side effect. */
require_once __DIR__.'/history_filters.php';
require_once __DIR__.'/history_presentation.php';
require_once __DIR__.'/attendance.php';

/** Presence with an unknown/non-scalar value is an invalid export, never HTML/raw fallback. */
function chrono_history_export_mode(array $query): array {
    return ['requested'=>array_key_exists('export',$query),
        'valid'=>!array_key_exists('export',$query) || (is_string($query['export']) && in_array($query['export'],['selected-json','correlated-json'],true))];
}

/** Fixed relative destination; only the existing selected filter/cursor keys survive. */
function chrono_history_export_url(array $values): string {
    $params=[];
    foreach (['user_id','from','to','entity','record_id','request_id','before'] as $key) {
        if (isset($values[$key]) && is_string($values[$key]) && $values[$key]!=='') { $params[$key]=$values[$key]; }
    }
    $params['export']='selected-json';
    return 'history.php?'.http_build_query($params,'','&',PHP_QUERY_RFC3986);
}

function chrono_history_export_headers(bool $attachment): array {
    $headers=['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store','X-Content-Type-Options'=>'nosniff'];
    if ($attachment) { $headers['Content-Disposition']='attachment; filename="chrono-history-selected-page.json"'; }
    return $headers;
}

/** Complete fixed error body before any transport output; never exception/user text. */
function chrono_history_export_failure(int $status = 500): array {
    $status=$status===400?400:500;
    return ['status'=>$status,'headers'=>chrono_history_export_headers(false),
        'body'=>$status===400?'{"success":false,"message":"Ungültige Historienfilter oder ungültiges Exportformat."}':
            '{"success":false,"message":"Historienexport nicht verfügbar."}'];
}

function chrono_history_export_id(mixed $value, bool &$limited): ?int {
    if ((!is_int($value) && !is_string($value)) || preg_match('/\A[1-9][0-9]*\z/',(string)$value)!==1) {
        $limited=true;return null;
    }
    $id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if ($id===false) { $limited=true;return null; }
    return $id;
}

/** Already-authorized query result, in its unchanged order. Never walk a cursor or related table. */
function chrono_history_export_page(array $rows, array $values): array {
    if (count($rows)>100) { throw new LogicException('Selected history page exceeds its existing limit.'); }
    $entries=[];$labels=chrono_history_entities();
    foreach ($rows as $row) {
        $limited=false;$entity=is_string($row['entity']??null)&&isset($labels[$row['entity']])?$row['entity']:null;
        $operation=in_array($row['operation']??null,['INSERT','UPDATE','DELETE','BASELINE'],true)?$row['operation']:null;
        if ($entity===null || $operation===null) { $limited=true; }
        $id=chrono_history_export_id($row['id']??null,$limited);
        $record=chrono_history_export_id($row['record_id']??null,$limited);
        $actor=chrono_history_selected_scalar($row['actor']??null,$limited);
        $reason=chrono_history_selected_scalar($row['reason']??null,$limited);
        // Audit timestamps use the existing Berlin formatter; malformed metadata is not guessed.
        $at=$row['occurred_at']??null;
        if (!is_int($at)) { $at=null;$limited=true; }
        $presentation=chrono_history_presentation($entity,$row['before_json']??null,$row['after_json']??null);
        $entries[]=['audit_id'=>$id,'occurred_at'=>$at===null?'Nicht verfügbar':attendance_local($at),
            'entity'=>$entity??'unrecognized','entity_label'=>$entity===null?'Nicht verfügbar':$labels[$entity],
            'record_id'=>$record,'operation'=>$operation??'unrecognized','actor'=>$actor['value'],'reason'=>$reason['value'],
            // Only the public selected result: NEVER internal cells/signatures or original JSON.
            'presentation'=>$presentation,'metadata_limited'=>$limited];
    }
    $selectedFilters=[];
    foreach (['user_id','from','to','entity','record_id','request_id','before'] as $key) {
        if (isset($values[$key]) && is_string($values[$key]) && $values[$key]!=='') { $selectedFilters[$key]=$values[$key]; }
    }
    $body=json_encode(['format'=>'chrono-selected-history-page-v1','filters'=>(object)$selectedFilters,
        'row_limit'=>100,'returned_rows'=>count($entries),'older_matches_may_exist'=>count($entries)===100,
        'complete_audit'=>false,'related_records_included'=>false,
        'notice'=>'Aktuelle autorisierte Treffer dieser Filterseite, nicht alle Seiten. Ausgewählte und gegebenenfalls gekürzte Felder; kein Rohjournal, vollständiger Korrelations- oder forensischer Nachweis.',
        'entries'=>$entries],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
    return ['status'=>200,'headers'=>chrono_history_export_headers(true),'body'=>$body."\n"];
}

/** Explicit separate mode; the existing selected-json v1 contract stays unchanged. */
function chrono_history_correlated_requested(array $query): bool {
    return isset($query['export']) && is_string($query['export']) && $query['export']==='correlated-json';
}

function chrono_history_correlated_export_url(array $values): string {
    $selected=chrono_history_export_url($values);
    return substr($selected,0,-strlen('selected-json')).'correlated-json';
}

/** Fresh native actor disappeared/changed; no exception text or role/identity is returned. */
function chrono_history_correlated_authorization_failure(int $status): array {
    return ['status'=>$status===403?403:401,'headers'=>chrono_history_export_headers(false),
        'body'=>'{"success":false,"message":"Historienzugriff nicht verfügbar. Bitte erneut anmelden."}'];
}

/** Canonical graph identifiers are exported as decimal strings, never lossy JS numbers. */
function chrono_history_correlation_export_id(mixed $value): string {
    $limited=false;$id=chrono_history_export_id($value,$limited);
    if ($limited || $id===null) { throw new LogicException('Invalid selected relation identifier.'); }
    return (string)$id;
}

/** Only the explicit public relation schema crosses this boundary, never internal rows/owners. */
function chrono_history_correlated_export_page(array $bundle, array $values): array {
    if (!isset($bundle['rows'],$bundle['correlation']) || !is_array($bundle['rows']) || !is_array($bundle['correlation'])) {
        throw new LogicException('Bounded history relations are required.');
    }
    $source=$bundle['correlation'];$selected=[];
    foreach (['anchors','sessions','events','requests'] as $key) {
        if (!isset($source[$key]) || !is_array($source[$key]) || !array_is_list($source[$key]) || count($source[$key])>100) {
            throw new LogicException('Invalid selected relation collection.');
        }
        $selected[$key]=[];
    }
    foreach ($source['sessions'] as $row) {
        $selected['sessions'][]=['id'=>chrono_history_correlation_export_id($row['id']??null),
            'record_id'=>chrono_history_correlation_export_id($row['record_id']??null)];
    }
    $sessionIds=array_fill_keys(array_column($selected['sessions'],'id'),true);
    foreach ($source['events'] as $row) {
        $session=chrono_history_correlation_export_id($row['session_id']??null);
        if (!isset($sessionIds[$session]) || !is_string($row['event_id']??null)
            || preg_match('/\A[A-Za-z0-9_-]{16,80}\z/',$row['event_id'])!==1
            || !in_array($row['kind']??null,['start','pause_start','pause_end','end'],true)
            || !is_int($row['received_at']??null)) { throw new LogicException('Invalid selected event relation.'); }
        $selected['events'][]=['id'=>chrono_history_correlation_export_id($row['id']??null),
            'event_id'=>$row['event_id'],'session_id'=>$session,'kind'=>$row['kind'],'received_at'=>$row['received_at']];
    }
    foreach ($source['requests'] as $row) {
        if (!array_key_exists('record_id',$row) || !in_array($row['status']??null,['pending','approved','rejected'],true)
            || !in_array($row['cancel']??null,[0,1],true)) { throw new LogicException('Invalid selected request relation.'); }
        $selected['requests'][]=['id'=>chrono_history_correlation_export_id($row['id']??null),
            'record_id'=>$row['record_id']===null?null:chrono_history_correlation_export_id($row['record_id']),
            'status'=>$row['status'],'cancel'=>$row['cancel']];
    }
    $available=['session_ids'=>$sessionIds,'event_ids'=>array_fill_keys(array_column($selected['events'],'id'),true),
        'request_ids'=>array_fill_keys(array_column($selected['requests'],'id'),true)];
    foreach ($source['anchors'] as $row) {
        if (!in_array($row['status']??null,['linked','unresolved','not_applicable'],true)
            || !is_bool($row['current_request_relation']??null)) { throw new LogicException('Invalid selected anchor relation.'); }
        $anchor=['audit_id'=>chrono_history_correlation_export_id($row['audit_id']??null),'status'=>$row['status']];
        foreach ($available as $key=>$allowed) {
            if (!isset($row[$key]) || !is_array($row[$key]) || !array_is_list($row[$key]) || count($row[$key])>100) {
                throw new LogicException('Invalid selected anchor references.');
            }
            $anchor[$key]=[];
            foreach ($row[$key] as $value) {
                $id=chrono_history_correlation_export_id($value);
                if (!isset($allowed[$id])) { throw new LogicException('Selected reference is not in its bounded collection.'); }
                $anchor[$key][]=$id;
            }
        }
        $anchor['current_request_relation']=$row['current_request_relation'];$selected['anchors'][]=$anchor;
    }
    $selected['truncated']=[];
    foreach (['anchors','sessions','events','requests','references'] as $key) {
        if (!is_bool($source['truncated'][$key]??null)) { throw new LogicException('Missing relation completeness marker.'); }
        $selected['truncated'][$key]=$source['truncated'][$key];
    }
    // Compose the existing selected projection, not any raw snapshot or related free text.
    $response=chrono_history_export_page($bundle['rows'],$values);
    $body=json_decode($response['body'],true,512,JSON_THROW_ON_ERROR);
    $body['format']='chrono-correlated-history-page-v1';$body['related_records_included']=true;
    $body['complete_correlation']=false;$body['relation_identifier_encoding']='decimal-string';
    $body['notice']='Begrenzte strukturelle Bezüge derselben autorisierten Ankerseite. Referenzen können außerhalb der Ankerfilter liegen; aktuelle gespeicherte Beziehungen sind kein Nachweis verursachender Ereignisse oder einer vollständigen Historie. Fehlende oder abgeschnittene Bezüge bleiben ungeklärt.';
    $body['correlation']=$selected;
    $response['headers']['Content-Disposition']='attachment; filename="chrono-history-correlated-page.json"';
    $response['body']=json_encode($body,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR)."\n";
    return $response;
}
