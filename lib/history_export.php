<?php
/** Selected authorized history page only. No connection, lookup, file or HTTP side effect. */
require_once __DIR__.'/history_filters.php';
require_once __DIR__.'/history_presentation.php';
require_once __DIR__.'/attendance.php';

/** Presence with an unknown/non-scalar value is an invalid export, never HTML/raw fallback. */
function chrono_history_export_mode(array $query): array {
    return ['requested'=>array_key_exists('export',$query),
        'valid'=>!array_key_exists('export',$query) || (is_string($query['export']) && $query['export']==='selected-json')];
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
