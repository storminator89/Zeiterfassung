<?php
// Definitions only: bounded own current SQL values, never time interpretation or CSV admission.
require_once __DIR__.'/attendance.php';

const CHRONO_OWN_STORED_VALUES_MAX_RECORDS = 5000;
const CHRONO_OWN_STORED_VALUES_MAX_FIELD_BYTES = 65536;
const CHRONO_OWN_STORED_VALUES_MAX_DOCUMENT_BYTES = 8388608;

/** Fixed display/export columns already visible in the own time list/CSV; no account or history fields. */
function chrono_own_stored_values_columns(): array
{
    return ['id','startzeit','endzeit','pause','standort','beschreibung','absence_kind','planned_minutes'];
}

/** Preserve SQL value/storage distinctions, including invalid UTF8 and nonfinite REAL; never JS-number IDs. */
function chrono_own_stored_value(string $storage,mixed $value): array
{
    if ($storage==='null' && $value===null) { return ['storage'=>'null','encoding'=>'null','value'=>null]; }
    if ($storage==='integer' && (is_int($value) || is_string($value))
        && preg_match('/\A(?:0|-?[1-9][0-9]*)\z/',(string)$value)===1) {
        return ['storage'=>'integer','encoding'=>'decimal-string','value'=>(string)$value];
    }
    if ($storage==='real' && is_float($value)) {
        return ['storage'=>'real','encoding'=>'ieee754-binary64-be-base64','value'=>base64_encode(pack('E',$value))];
    }
    if (in_array($storage,['text','blob'],true) && is_string($value)) {
        $utf8=$storage==='text' && mb_check_encoding($value,'UTF-8');
        return ['storage'=>$storage,'encoding'=>$utf8?'utf8':'base64','value'=>$utf8?$value:base64_encode($value)];
    }
    throw new UnexpectedValueException('Stored value representation unavailable.');
}

/** All bytes are materialized in bounded memory before the caller creates attachment headers. */
function chrono_own_stored_values_write(mixed $stream,string $bytes,int &$written): void
{
    if (strlen($bytes)>CHRONO_OWN_STORED_VALUES_MAX_DOCUMENT_BYTES-$written) {
        throw new LengthException('Stored-value export exceeds its fixed document limit.');
    }
    $at=0;
    while ($at<strlen($bytes)) {
        $count=fwrite($stream,substr($bytes,$at));
        if (!is_int($count) || $count<1) { throw new RuntimeException('Stored-value export unavailable.'); }
        $at+=$count;$written+=$count;
    }
}

/** Caller owns current authenticated own-row authority and transaction; never accepts a subject from a form. */
function chrono_own_stored_values_export_snapshot(PDO $pdo,int $owner,DateTimeImmutable $loaded): array
{
    if (!$pdo->inTransaction() || $owner<1) { throw new LogicException('An authorized own stored-value snapshot is required.'); }
    $columns=chrono_own_stored_values_columns();$select=[];$oversized=[];
    foreach ($columns as $column) {
        // All identifiers and bounds are fixed source constants. Oversized old
        // values are not loaded into PHP, truncated or emitted as null facts.
        $bounded='COALESCE(length(CAST(z.'.$column.' AS BLOB)),0)<='.CHRONO_OWN_STORED_VALUES_MAX_FIELD_BYTES;
        $select[]='CASE WHEN '.$bounded.' THEN z.'.$column.' ELSE NULL END AS '.$column;
        $select[]='typeof(z.'.$column.') AS '.$column.'__storage';$oversized[]='NOT ('.$bounded.')';
    }
    $select[]='CASE WHEN '.implode(' OR ',$oversized).' THEN 1 ELSE 0 END AS too_large';
    $statement=$pdo->prepare('SELECT '.implode(',',$select).' FROM zeiterfassung z WHERE z.user_id=? ORDER BY z.id DESC LIMIT '.(CHRONO_OWN_STORED_VALUES_MAX_RECORDS+1));
    $output=fopen('php://memory','w+b');
    if ($output===false) { throw new RuntimeException('Stored-value export unavailable.'); }
    $loaded=$loaded->setTimezone(new DateTimeZone('Europe/Berlin'));$written=0;$count=0;
    $flags=JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT;
    try {
        $metadata=['schema'=>'chrono-own-current-stored-values-v1','scope'=>'authenticated_own_current_rows',
            'loaded_at'=>$loaded->format(DateTimeInterface::ATOM),'ordering'=>'id_descending','columns'=>$columns,
            'calculated_time_report'=>false,'database_backup'=>false,'audit_history'=>false,
            'limits'=>['records'=>CHRONO_OWN_STORED_VALUES_MAX_RECORDS,'field_bytes'=>CHRONO_OWN_STORED_VALUES_MAX_FIELD_BYTES,
                'document_bytes'=>CHRONO_OWN_STORED_VALUES_MAX_DOCUMENT_BYTES]];
        chrono_own_stored_values_write($output,'{"metadata":'.json_encode($metadata,$flags).',"records":[',$written);
        $statement->bindValue(1,$owner,PDO::PARAM_INT);$statement->execute();
        while (($row=$statement->fetch(PDO::FETCH_ASSOC))!==false) {
            if ($count>=CHRONO_OWN_STORED_VALUES_MAX_RECORDS || !in_array($row['too_large'],[0,'0'],true)) {
                throw new LengthException('Stored-value export exceeds its fixed row or field limit.');
            }
            $values=[];
            foreach ($columns as $column) { $values[$column]=chrono_own_stored_value($row[$column.'__storage'],$row[$column]); }
            chrono_own_stored_values_write($output,($count===0?'':',').json_encode($values,$flags),$written);$count++;
        }
        chrono_own_stored_values_write($output,'],"record_count":'.$count.',"complete":true}', $written);
        if (!rewind($output) || !is_string($content=stream_get_contents($output))) { throw new RuntimeException('Stored-value export unavailable.'); }
        return ['content'=>$content,'filename'=>'Eigene_gespeicherte_Werte_'.$loaded->format('Y-m-d').'.json'];
    } finally { $statement->closeCursor();fclose($output); }
}

/** Existing Native actor/password-version and fixed own values share one owned read transaction. */
function chrono_native_own_stored_values_export(PDO $pdo,int $actor,#[SensitiveParameter] mixed $version): array
{
    if ($pdo->inTransaction()) { throw new LogicException('Own stored-value export requires an idle connection.'); }
    if (!$pdo->beginTransaction()) { throw new RuntimeException('Own stored-value export unavailable.'); }
    try {
        $current=att_one($pdo,'SELECT role,password FROM users WHERE id=?',[$actor]);
        if ($actor<1 || $current===null || !is_string($version) || preg_match('/\A[a-f0-9]{64}\z/',$version)!==1
            || !is_string($current['password']) || !hash_equals($version,hash('sha256',$current['password']))) {
            throw new AttendanceError('Eigene gespeicherte Werte nicht verfügbar. Bitte neu anmelden.',401);
        }
        if (!in_array($current['role'],['user','supervisor','admin'],true)) { throw new AttendanceError('Eigene gespeicherte Werte nicht verfügbar.',403); }
        unset($current);
        $result=chrono_own_stored_values_export_snapshot($pdo,$actor,new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')));
        if (!$pdo->commit()) { throw new RuntimeException('Own stored-value export unavailable.'); }
        return $result;
    } catch (Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); }throw $error; }
}
