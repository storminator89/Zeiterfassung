<?php
// Definitions only. Existing callers own authorization, IMMEDIATE and audit context.
require_once __DIR__.'/attendance.php';
require_once __DIR__.'/correction_selection.php';

const CHRONO_CORRECTION_METADATA_V1 = '_chrono_metadata_request_v1';

/** Exact, closed proposal. Reuse the start contract, never translate stored codes. */
function correction_metadata_v1_values(mixed $proposal): array {
    if (!$proposal instanceof stdClass || array_keys(get_object_vars($proposal))!==['version','location','note']
        || $proposal->version!==1) { throw new AttendanceError('Metadaten-Antrag nicht auswertbar.',409); }
    return attendance_start_metadata(['location'=>$proposal->location,'note'=>$proposal->note]);
}

/** Legacy snapshots return null. Reserved unknown/malformed formats never become time requests. */
function correction_metadata_v1_parse(mixed $raw): ?array {
    if (!is_string($raw) || $raw==='') { return null; }
    try { $object=json_decode($raw,false,8,JSON_THROW_ON_ERROR); }
    catch (JsonException) { return null; }
    if (!$object instanceof stdClass) { return null; }
    $reserved=[];
    foreach (array_keys(get_object_vars($object)) as $key) {
        if (str_starts_with($key,'_chrono_metadata_request')) { $reserved[]=$key; }
    }
    if (!$reserved) { return null; }
    if (strlen($raw)>65536 || $reserved!==[CHRONO_CORRECTION_METADATA_V1]
        || json_encode($object,JSON_THROW_ON_ERROR)!==$raw) {
        throw new AttendanceError('Metadaten-Antrag nicht auswertbar.',409);
    }
    $metadata=correction_metadata_v1_values($object->{CHRONO_CORRECTION_METADATA_V1});
    // Remove exactly the one reserved field, no sorting, normalization or other discard.
    unset($object->{CHRONO_CORRECTION_METADATA_V1});
    return ['original_json'=>json_encode($object,JSON_THROW_ON_ERROR),'metadata'=>$metadata];
}

/** Completed work only. Do not change absence semantics or any original time value. */
function correction_metadata_v1_record(array $record): array {
    foreach (array_keys($record) as $key) {
        if (str_starts_with((string)$key,'_chrono_metadata_request')) {
            throw new AttendanceError('Reserviertes Metadatenfeld in der Buchung.',409);
        }
    }
    if (booking_is_absence($record) || report_record_calculation($record)['status']!=='completed') {
        throw new AttendanceError('Ort und Notiz nur für abgeschlossene Arbeitsbuchungen beantragen.',400);
    }
    return [booking_time($record['startzeit']),booking_time($record['endzeit']),(int)$record['pause']];
}

function correction_metadata_v1_request_locked(PDO $db,int $user,array $command,Closure $auditRunner): void {
    if (!$db->inTransaction()) { throw new LogicException('Metadata caller must own IMMEDIATE.'); }
    try { $id=correction_selection_id($command['record_id']??null); }
    catch (InvalidArgumentException) { throw new AttendanceError('Bestehende eigene Arbeitsbuchung auswählen.',400); }
    if (($command['metadata_confirm']??null)!=='1' || ($command['cancel']??'')==='1') {
        throw new AttendanceError('Ort/Notiz ausdrücklich ersetzen; keine Stornierung oder Zeitänderung.',400);
    }
    if (!array_key_exists('location',$command) || !array_key_exists('note',$command)) {
        throw new AttendanceError('Arbeitsort und Notiz vollständig angeben.',400);
    }
    $metadata=attendance_start_metadata(['location'=>$command['location'],'note'=>$command['note']]);
    $reason=$command['reason']??null;
    if (!is_string($reason) || strlen(trim($reason))<5 || strlen(trim($reason))>1000) {
        throw new AttendanceError('Begründung mit 5 bis 1000 Bytes erforderlich.',400);
    }
    $reason=trim($reason);
    $old=att_one($db,'SELECT * FROM zeiterfassung WHERE id=? AND user_id=?',[$id,$user]);
    if ($user<1 || !$old) { throw new AttendanceError('Nur eigene Arbeitsbuchungen beantragen.',403); }
    [$start,$end,$pause]=correction_metadata_v1_record($old);
    if (att_one($db,'SELECT id FROM work_sessions WHERE record_id=? AND ended_at IS NULL',[$id])) {
        throw new AttendanceError('Laufende Arbeitszeit zuerst ausstempeln.',409);
    }
    if ($old['standort']===$metadata['location'] && $old['beschreibung']===$metadata['note']) {
        throw new AttendanceError('Ort und Notiz sind unverändert.',400);
    }
    $snapshot=$old;
    $snapshot[CHRONO_CORRECTION_METADATA_V1]=['version'=>1]+$metadata;
    try { $raw=json_encode($snapshot,JSON_THROW_ON_ERROR); }
    catch (JsonException) { throw new AttendanceError('Ursprüngliche Buchung nicht auswertbar.',400); }
    if (strlen($raw)>65536) { throw new AttendanceError('Ursprüngliche Buchung zu groß für diesen Antrag.',400); }
    $auditRunner('Ort/Notiz-Antrag: '.$reason,static function () use ($db,$user,$id,$start,$end,$pause,$reason,$raw): void {
        att_exec($db,'INSERT INTO time_adjustments(user_id,record_id,proposed_start,proposed_end,proposed_pause,reason,original_json,created_at,cancel) VALUES(?,?,?,?,?,?,?,?,0)',[$user,$id,$start,$end,$pause,$reason,$raw,time()]);
    });
}

/** Called only after the existing foreign reviewer, exact snapshot and active-session checks. */
function correction_metadata_v1_apply_locked(PDO $db,array $request,array $old,array $metadata): void {
    if (!$db->inTransaction()) { throw new LogicException('Metadata caller must own IMMEDIATE.'); }
    [$start,$end,$pause]=correction_metadata_v1_record($old);
    foreach (['proposed_start'=>$start,'proposed_end'=>$end,'proposed_pause'=>$pause] as $field=>$expected) {
        $value=$request[$field]??null;
        if ((!is_int($value)&&!is_string($value)) || (string)$value!==(string)$expected) {
            throw new AttendanceError('Metadaten-Antrag enthält abweichende Zeitfelder.',409);
        }
    }
    if (!in_array($request['cancel']??null,[0,'0'],true) || (int)($request['record_id']??0)!==(int)$old['id']
        || (int)($request['user_id']??0)!==(int)$old['user_id']) {
        throw new AttendanceError('Metadaten-Antrag nicht auswertbar.',409);
    }
    $metadata=attendance_start_metadata($metadata); // Same-lock validation, even for stored proposals.
    att_exec($db,'UPDATE zeiterfassung SET beschreibung=?,standort=? WHERE id=? AND user_id=?',[$metadata['note'],$metadata['location'],$old['id'],$old['user_id']]);
}

/** Selected escaped-by-caller display only; never expose arbitrary snapshot fields. */
function correction_metadata_v1_preview(array $request): ?array {
    try { $parsed=correction_metadata_v1_parse($request['original_json']??null); }
    catch (Throwable) { return ['available'=>false]; }
    if ($parsed===null) { return null; }
    $old=json_decode($parsed['original_json'],true,8,JSON_THROW_ON_ERROR);
    if (!is_string($old['standort']??null) || !is_string($old['beschreibung']??null)) { return ['available'=>false]; }
    return ['available'=>true,'before_location'=>$old['standort'],'before_note'=>$old['beschreibung'],
        'after_location'=>$parsed['metadata']['location'],'after_note'=>$parsed['metadata']['note']];
}

/** Same-response display only after a known rejection; never retain the confirmation. */
function correction_metadata_v1_rejected_draft(array $form): array {
    if (($form['action']??null)!=='request_metadata_v1') { return []; }
    $draft=[];
    foreach (['location'=>128,'note'=>1000,'reason'=>4096] as $field=>$limit) {
        if (is_string($form[$field]??null) && strlen($form[$field])<=$limit
            && preg_match('//u',$form[$field])===1) { $draft[$field]=$form[$field]; }
    }
    return $draft;
}
