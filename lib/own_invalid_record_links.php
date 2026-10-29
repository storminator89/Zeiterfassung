<?php
// Definitions only; no lookup, parser, session or writer. IDs only, never raw errors.
function own_invalid_record_links(array $diagnostics, array $records, int $user): array {
    if($user<1){return [];}
    $candidates=[];
    foreach($diagnostics as $diagnostic){
        if(!is_array($diagnostic)){continue;}
        $id=filter_var($diagnostic['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($id!==false){$candidates[$id]=true;}
        if(count($candidates)===10){break;}
    }
    $owned=[];
    foreach($records as $record){
        if(!is_array($record)){continue;}
        $owner=filter_var($record['user_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $id=filter_var($record['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($owner===$user&&$id!==false&&isset($candidates[$id])){$owned[$id]=true;}
    }
    return array_values(array_filter(array_keys($candidates),static fn(int $id):bool=>isset($owned[$id])));
}
