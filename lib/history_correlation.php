<?php
/** Bounded structural links only. Caller owns the same fresh-role read snapshot. */
require_once __DIR__.'/history_presentation.php';

function chrono_history_correlation_integer(mixed $value, int $minimum = 1): ?int {
    if (!is_int($value) && (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]*)\z/',$value))) { return null; }
    $number=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>$minimum]]);
    return $number===false?null:$number;
}

/** Fixed owner/id pairs only; identifiers are supplied solely by the fixed queries below. */
function chrono_history_correlation_pairs(array $pairs,string $ownerColumn,string $idColumn): array {
    $where=[];$params=[];
    foreach ($pairs as [$owner,$id]) {
        $where[]="($ownerColumn=? AND $idColumn=?)";$params[]=$owner;$params[]=$id;
    }
    return [$where?'('.implode(' OR ',$where).')':'0',$params];
}

/** Same own/Admin/current direct-team scope; missing accounts are Admin-only. */
function chrono_history_correlation_scope(string $ownerColumn,int $actor,string $role): array {
    return ["(u.id IS NOT NULL OR ?='admin') AND ($ownerColumn=? OR ?='admin' OR (?='supervisor' AND u.supervisor_id=?))",
        [$role,$actor,$role,$role,$actor]];
}

function chrono_history_correlation_rows(PDO $pdo,string $sql,array $params,bool &$truncated): array {
    $statement=$pdo->prepare($sql);$statement->execute($params);
    $rows=$statement->fetchAll(PDO::FETCH_ASSOC);$statement->closeCursor();
    if (count($rows)>100) { $truncated=true;array_pop($rows); }
    return $rows;
}

/** Only the specific structural field is read; never infer ownership from JSON. */
function chrono_history_correlation_snapshot(array $anchor,string $field): array {
    $ids=[];$seen=false;
    foreach (['before_json','after_json'] as $side) {
        $raw=$anchor[$side];
        if ($raw===null) { continue; }
        $snapshot=chrono_history_decode_presentation($raw);
        if (!($snapshot instanceof stdClass)) { return ['valid'=>false,'id'=>null]; }
        if (property_exists($snapshot,'id') && chrono_history_correlation_integer($snapshot->id)!==$anchor['record_id']) {
            return ['valid'=>false,'id'=>null];
        }
        if (!property_exists($snapshot,$field)) { return ['valid'=>false,'id'=>null]; }
        $seen=true;
        if ($snapshot->{$field}===null) { continue; }
        $id=chrono_history_correlation_integer($snapshot->{$field});
        if ($id===null) { return ['valid'=>false,'id'=>null]; }
        $ids[$id]=$id;
    }
    return ['valid'=>$seen && count($ids)<=1,'id'=>count($ids)===1?reset($ids):null];
}

/**
 * Read-only, at most 100 anchors/rows per public collection. All relations are
 * structural, never evidence that an event caused an audit row. No live booking
 * join: cancelled projections can be gone while their audited ownership remains.
 * SQL/schema failures propagate. An unresolved link says nothing about existence.
 */
function chrono_history_correlations(PDO $pdo,array $authorizedAnchorRows,int $actor,string $role): array {
    $result=['anchors'=>[],'sessions'=>[],'events'=>[],'requests'=>[],
        'truncated'=>['anchors'=>count($authorizedAnchorRows)>100,'sessions'=>false,'events'=>false,'requests'=>false,'references'=>false]];
    $wanted=[];
    foreach (array_slice($authorizedAnchorRows,0,100) as $row) {
        $id=is_array($row)?chrono_history_correlation_integer($row['id']??null):null;
        if ($id!==null) { $wanted[$id]=$id; }
    }
    foreach ($wanted as $id) {
        $result['anchors'][$id]=['audit_id'=>$id,'status'=>'unresolved','session_ids'=>[],'event_ids'=>[],
            'request_ids'=>[],'current_request_relation'=>false];
    }
    if (!$wanted || $actor<1 || !in_array($role,['user','supervisor','admin'],true)) {
        $result['anchors']=array_values($result['anchors']);return $result;
    }
    if (!$pdo->inTransaction()) { throw new LogicException('History correlation requires the caller-owned active read snapshot.'); }

    // Rehydrate only the requested authorized IDs, never trust supplied row owners/seeds.
    [$scope,$scopeParams]=chrono_history_correlation_scope('a.user_id',$actor,$role);
    $rows=chrono_history_correlation_rows($pdo,
        'SELECT a.id,a.entity,a.record_id,a.user_id,a.before_json,a.after_json FROM audit_log a LEFT JOIN users u ON u.id=a.user_id WHERE a.id IN ('.implode(',',array_fill(0,count($wanted),'?')).") AND $scope ORDER BY a.id LIMIT 101",
        [...array_values($wanted),...$scopeParams],$result['truncated']['anchors']);
    $anchors=[];$bookingPairs=[];$sessionPairs=[];$requestPairs=[];
    foreach ($rows as $row) {
        $id=chrono_history_correlation_integer($row['id']);
        if ($id===null || !isset($wanted[$id])) { continue; }
        if (!in_array($row['entity'],['zeiterfassung','work_sessions','break_intervals','time_adjustments'],true)) {
            $result['anchors'][$id]['status']='not_applicable';continue;
        }
        $owner=chrono_history_correlation_integer($row['user_id']);$record=chrono_history_correlation_integer($row['record_id']);
        if ($owner===null || $record===null) { continue; }
        $row['id']=$id;$row['user_id']=$owner;$row['record_id']=$record;
        $row['seed']=null;$row['historical_record']=null;
        if ($row['entity']==='zeiterfassung') {
            $row['seed']=$record;$bookingPairs[$owner.':'.$record]=[$owner,$record];
        } elseif ($row['entity']==='work_sessions') {
            $row['seed']=$record;$sessionPairs[$owner.':'.$record]=[$owner,$record];
        } elseif ($row['entity']==='break_intervals') {
            $snapshot=chrono_history_correlation_snapshot($row,'session_id');
            if (!$snapshot['valid'] || $snapshot['id']===null) { continue; }
            $row['seed']=$snapshot['id'];$sessionPairs[$owner.':'.$row['seed']]=[$owner,$row['seed']];
        } else {
            $snapshot=chrono_history_correlation_snapshot($row,'record_id');
            if (!$snapshot['valid']) { continue; }
            $row['historical_record']=$snapshot['id'];$requestPairs[$owner.':'.$record]=[$owner,$record];
        }
        $anchors[$id]=$row;
    }

    // Current retained requests can supplement an old NULL relation, explicitly labelled below.
    $directRequests=[];$badRequests=[];
    if ($requestPairs) {
        [$pairs,$params]=chrono_history_correlation_pairs($requestPairs,'r.user_id','r.id');
        [$scope,$scopeParams]=chrono_history_correlation_scope('r.user_id',$actor,$role);
        $rows=chrono_history_correlation_rows($pdo,"SELECT r.id,r.user_id,r.record_id,r.status,r.cancel FROM time_adjustments r LEFT JOIN users u ON u.id=r.user_id WHERE $pairs AND $scope ORDER BY r.id LIMIT 101",[...$params,...$scopeParams],$result['truncated']['requests']);
        foreach ($rows as $row) {
            $id=chrono_history_correlation_integer($row['id']);$owner=chrono_history_correlation_integer($row['user_id']);
            $record=$row['record_id']===null?null:chrono_history_correlation_integer($row['record_id']);
            $cancel=chrono_history_correlation_integer($row['cancel'],0);
            if ($id===null || $owner===null) { continue; }
            if (($row['record_id']!==null && $record===null) || !in_array($row['status'],['pending','approved','rejected'],true) || !in_array($cancel,[0,1],true)) {
                $badRequests[$owner.':'.$id]=true;continue;
            }
            $directRequests[$owner.':'.$id]=['id'=>$id,'owner'=>$owner,'record_id'=>$record,'status'=>$row['status'],'cancel'=>$cancel];
            if ($record!==null) { $bookingPairs[$owner.':'.$record]=[$owner,$record]; }
        }
    }

    $sessions=[];
    if ($bookingPairs || $sessionPairs) {
        [$byBooking,$bookingParams]=chrono_history_correlation_pairs($bookingPairs,'s.user_id','s.record_id');
        [$bySession,$sessionParams]=chrono_history_correlation_pairs($sessionPairs,'s.user_id','s.id');
        [$scope,$scopeParams]=chrono_history_correlation_scope('s.user_id',$actor,$role);
        $rows=chrono_history_correlation_rows($pdo,"SELECT s.id,s.user_id,s.record_id FROM work_sessions s LEFT JOIN users u ON u.id=s.user_id WHERE ($byBooking OR $bySession) AND $scope ORDER BY s.id LIMIT 101",[...$bookingParams,...$sessionParams,...$scopeParams],$result['truncated']['sessions']);
        foreach ($rows as $row) {
            $id=chrono_history_correlation_integer($row['id']);$owner=chrono_history_correlation_integer($row['user_id']);$record=chrono_history_correlation_integer($row['record_id']);
            if ($id===null || $owner===null || $record===null) { continue; }
            $sessions[$id]=['id'=>$id,'owner'=>$owner,'record_id'=>$record];$bookingPairs[$owner.':'.$record]=[$owner,$record];
        }
    }

    // Scoped retained sessions already prove their owner's structural record relation.
    // Audit ownership supplements legacy/cancelled records without a retained session.
    $ownedBookings=[];
    foreach ($sessions as $session) {
        $ownedBookings[$session['owner'].':'.$session['record_id']]=[$session['owner'],$session['record_id']];
    }
    if ($bookingPairs) {
        [$pairs,$params]=chrono_history_correlation_pairs($bookingPairs,'a.user_id','a.record_id');
        [$scope,$scopeParams]=chrono_history_correlation_scope('a.user_id',$actor,$role);
        $rows=chrono_history_correlation_rows($pdo,"SELECT DISTINCT a.user_id,a.record_id FROM audit_log a LEFT JOIN users u ON u.id=a.user_id WHERE a.entity='zeiterfassung' AND $pairs AND $scope ORDER BY a.user_id,a.record_id LIMIT 101",[...$params,...$scopeParams],$result['truncated']['references']);
        foreach ($rows as $row) {
            $owner=chrono_history_correlation_integer($row['user_id']);$record=chrono_history_correlation_integer($row['record_id']);
            if ($owner!==null && $record!==null) { $ownedBookings[$owner.':'.$record]=[$owner,$record]; }
        }
    }
    $sessions=array_filter($sessions,static fn($s)=>isset($ownedBookings[$s['owner'].':'.$s['record_id']]));
    $requests=array_filter($directRequests,static fn($r)=>$r['record_id']===null || isset($ownedBookings[$r['owner'].':'.$r['record_id']]));
    if ($ownedBookings) {
        [$pairs,$params]=chrono_history_correlation_pairs($ownedBookings,'r.user_id','r.record_id');
        [$scope,$scopeParams]=chrono_history_correlation_scope('r.user_id',$actor,$role);
        $rows=chrono_history_correlation_rows($pdo,"SELECT r.id,r.user_id,r.record_id,r.status,r.cancel FROM time_adjustments r LEFT JOIN users u ON u.id=r.user_id WHERE $pairs AND $scope ORDER BY r.id LIMIT 101",[...$params,...$scopeParams],$result['truncated']['requests']);
        foreach ($rows as $row) {
            $id=chrono_history_correlation_integer($row['id']);$owner=chrono_history_correlation_integer($row['user_id']);$record=chrono_history_correlation_integer($row['record_id']);$cancel=chrono_history_correlation_integer($row['cancel'],0);
            if ($id===null || $owner===null || $record===null || !in_array($row['status'],['pending','approved','rejected'],true) || !in_array($cancel,[0,1],true)) { continue; }
            $requests[$owner.':'.$id]=['id'=>$id,'owner'=>$owner,'record_id'=>$record,'status'=>$row['status'],'cancel'=>$cancel];
        }
    }
    uasort($requests,static fn($a,$b)=>$a['id']<=>$b['id']);
    if (count($requests)>100) { $result['truncated']['requests']=true;$requests=array_slice($requests,0,100,true); }

    $events=[];$eventSessionPairs=[];
    foreach ($sessions as $session) { $eventSessionPairs[$session['owner'].':'.$session['id']]=[$session['owner'],$session['id']]; }
    if ($eventSessionPairs) {
        [$pairs,$params]=chrono_history_correlation_pairs($eventSessionPairs,'e.user_id','e.session_id');
        [$scope,$scopeParams]=chrono_history_correlation_scope('e.user_id',$actor,$role);
        $rows=chrono_history_correlation_rows($pdo,"SELECT e.id,e.event_id,e.user_id,e.session_id,e.kind,e.received_at FROM attendance_events e JOIN work_sessions s ON s.id=e.session_id AND s.user_id=e.user_id LEFT JOIN users u ON u.id=e.user_id WHERE $pairs AND $scope ORDER BY e.id LIMIT 101",[...$params,...$scopeParams],$result['truncated']['events']);
        foreach ($rows as $row) {
            $id=chrono_history_correlation_integer($row['id']);$owner=chrono_history_correlation_integer($row['user_id']);$session=chrono_history_correlation_integer($row['session_id']);$received=chrono_history_correlation_integer($row['received_at'],0);
            if ($id===null || $owner===null || $session===null || $received===null || !is_string($row['event_id']) || !preg_match('/\A[A-Za-z0-9_-]{16,80}\z/',$row['event_id']) || !in_array($row['kind'],['start','pause_start','pause_end','end'],true)) { continue; }
            $events[$id]=['id'=>$id,'event_id'=>$row['event_id'],'owner'=>$owner,'session_id'=>$session,'kind'=>$row['kind'],'received_at'=>$received];
        }
    }

    $usedSessions=[];$usedEvents=[];$usedRequests=[];
    foreach ($anchors as $id=>$anchor) {
        $owner=$anchor['user_id'];$booking=null;$direct=null;$current=false;
        if ($anchor['entity']==='zeiterfassung') { $booking=$anchor['seed']; }
        elseif ($anchor['entity']==='time_adjustments') {
            $direct=$requests[$owner.':'.$anchor['record_id']]??null;
            if ($direct===null || isset($badRequests[$owner.':'.$anchor['record_id']])) { continue; }
            $booking=$direct['record_id'];
            if ($anchor['historical_record']!==null && $anchor['historical_record']!==$booking) { continue; }
            $current=$booking!==null && $anchor['historical_record']===null;
        } else {
            $session=$sessions[$anchor['seed']]??null;
            if ($session===null || $session['owner']!==$owner) { continue; }
            $booking=$session['record_id'];
        }
        if ($booking!==null && !isset($ownedBookings[$owner.':'.$booking])) { continue; }
        $links=&$result['anchors'][$id];
        foreach ($sessions as $session) {
            if ($booking!==null && $session['owner']===$owner && $session['record_id']===$booking) {
                $links['session_ids'][]=$session['id'];$usedSessions[$session['id']]=true;
            }
        }
        foreach ($events as $event) {
            if ($event['owner']===$owner && in_array($event['session_id'],$links['session_ids'],true)) {
                $links['event_ids'][]=$event['id'];$usedEvents[$event['id']]=true;
            }
        }
        foreach ($requests as $request) {
            if ($request['owner']===$owner && (($booking!==null && $request['record_id']===$booking) || ($direct!==null && $request['id']===$direct['id']))) {
                $links['request_ids'][]=$request['id'];$usedRequests[$request['id']]=true;
            }
        }
        if ($links['session_ids'] || $links['event_ids'] || $links['request_ids']) { $links['status']='linked';$links['current_request_relation']=$current; }
        unset($links);
    }
    foreach ($sessions as $session) {
        if (isset($usedSessions[$session['id']])) { $result['sessions'][]=['id'=>$session['id'],'record_id'=>$session['record_id']]; }
    }
    foreach ($events as $event) {
        if (isset($usedEvents[$event['id']])) { unset($event['owner']);$result['events'][]=$event; }
    }
    foreach ($requests as $request) {
        if (isset($usedRequests[$request['id']])) { unset($request['owner']);$result['requests'][]=$request; }
    }
    $result['anchors']=array_values($result['anchors']);
    return $result;
}
