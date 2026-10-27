<?php
require_once __DIR__.'/attendance.php';

function correction_time(string $text): int {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?[+-]\d{2}:\d{2}$/D',$text)) { throw new AttendanceError('Zeit mit Zeitzone angeben, z. B. 2026-10-25T09:00+01:00.',400); }
    try { $d=new DateTimeImmutable($text); } catch (Throwable $e) { throw new AttendanceError('Ungültige Zeit.',400); }
    $errors=DateTimeImmutable::getLastErrors();if ($errors && ($errors['warning_count']||$errors['error_count'])) { throw new AttendanceError('Ungültiges Datum.',400); }
    return $d->getTimestamp();
}
/**
 * Shared correction core; no HTTP, session, connection opening or transaction.
 * The trusted caller supplies the authenticated actor and refreshed server role,
 * registers the audit functions, and owns one active IMMEDIATE transaction on
 * this exact PDO. Command actor/role/source/audit fields are never consulted.
 * Keep native validation, audit globals and exceptions unchanged. A
 * framework caller must supply its admitted writer and matching scoped audit
 * context through the trusted runner; command data can never supply it.
 * The native fallback leaves audit_reason set exactly as before. This function
 * does not grant permission or activate a writer.
 */
function correction_apply_locked(PDO $db, int $user, string $role, array $command, ?Closure $auditRunner=null): void {
    if (!$db->inTransaction()) { throw new LogicException('Correction caller must own an active IMMEDIATE transaction on this PDO.'); }
    $auditRunner ??= static function (string $reason, Closure $operation): void {
        $GLOBALS['audit_reason']=$reason;
        $operation();
    };
    if (($command['action']??'')==='request') {
        $record=(int)($command['record_id']??0); $r=$record?att_one($db,'SELECT * FROM zeiterfassung WHERE id=? AND user_id=?',[$record,$user]):null;
        if ($record && !$r) { throw new AttendanceError('Nur eigene Arbeitszeiten korrigieren.'); }
        if ($record && att_one($db,'SELECT id FROM work_sessions WHERE record_id=? AND ended_at IS NULL',[$record])) { throw new AttendanceError('Laufende Arbeitszeit zuerst ausstempeln.'); }
        $cancel=($command['cancel']??'')==='1';
        if ($cancel && !$r) { throw new AttendanceError('Stornierung erfordert eine bestehende Buchung.'); }
        $start=$cancel?strtotime($r['startzeit']):correction_time((string)($command['start']??''));$end=$cancel?max($start+1,strtotime($r['endzeit']??'now')):correction_time((string)($command['end']??''));$pause=$cancel?0:filter_var($command['pause']??'',FILTER_VALIDATE_INT);$reason=trim((string)($command['reason']??''));
        // Same rejection set and left-to-right precedence; only fixed feedback differs.
        if (!$cancel) {
            if ($end<=$start) { throw new AttendanceError('Das Ende muss nach dem Beginn liegen. Bei einer Nachtschicht bitte Folgedatum und Zeitzonenoffset prüfen.',400); }
            if ($end-$start>7*86400) { throw new AttendanceError('Zeitraum, Pausen und Begründung prüfen.',400); }
            if ($pause===false||$pause<0) { throw new AttendanceError('Die tatsächliche Pause muss eine nicht negative ganze Minutenzahl sein.',400); }
            if ($pause>floor(($end-$start)/60)) { throw new AttendanceError('Die tatsächliche Pause überschreitet die Dauer in ganzen Minuten zwischen Beginn und Ende. Bitte Zeitangaben und Pause prüfen.',400); }
        }
        if (strlen($reason)<5||strlen($reason)>1000) { throw new AttendanceError('Zeitraum, Pausen und Begründung prüfen.',400); }
        if (!$cancel) { booking_validate_correction($r,$start,$end,$pause,time()); }
        $auditRunner('Korrekturantrag: '.$reason, static function () use ($db,$user,$record,$start,$end,$pause,$reason,$r,$cancel): void {
            att_exec($db,'INSERT INTO time_adjustments(user_id,record_id,proposed_start,proposed_end,proposed_pause,reason,original_json,created_at,cancel) VALUES(?,?,?,?,?,?,?,?,?)',[$user,$record?:null,$start,$end,$pause,$reason,$r?json_encode($r):null,time(),(int)$cancel]);
        });
    } elseif (($command['action']??'')==='review') {
        $a=att_one($db,'SELECT * FROM time_adjustments WHERE id=? AND status=?',[(int)($command['id']??0),'pending']);
        if (!$a || (int)$a['user_id']===$user || !($role==='admin'||($role==='supervisor'&&att_one($db,'SELECT id FROM users WHERE id=? AND supervisor_id=?',[$a['user_id'],$user])))) { throw new AttendanceError('Keine Freigabeberechtigung.',403); }
        if (!att_one($db,'SELECT id FROM users WHERE id=?',[$a['user_id']])) { throw new AttendanceError('Konto existiert nicht mehr.'); }
        $decision=$command['decision']??'';$reason=trim((string)($command['review_reason']??''));
        if (!in_array($decision,['approved','rejected'],true)||strlen($reason)<3||strlen($reason)>1000) { throw new AttendanceError('Entscheidung und Begründung erforderlich.',400); }
        $auditRunner('Korrektur #'.$a['id'].': '.$reason, static function () use ($db,$user,$a,$decision,$reason): void {
            if ($decision==='approved') {
                $old=$a['record_id']?att_one($db,'SELECT * FROM zeiterfassung WHERE id=? AND user_id=?',[$a['record_id'],$a['user_id']]):null;
                if ($a['record_id'] && (!$old || json_encode($old)!==$a['original_json'])) { throw new AttendanceError('Datensatz wurde inzwischen geändert. Neuen Antrag stellen.'); }
                if ($old && att_one($db,'SELECT id FROM work_sessions WHERE record_id=? AND ended_at IS NULL',[$a['record_id']])) { throw new AttendanceError('Laufende Arbeitszeit zuerst ausstempeln.'); }
                if (!$a['cancel']) {
                    booking_validate_correction($old,(int)$a['proposed_start'],(int)$a['proposed_end'],(int)$a['proposed_pause'],time());
                    $proposal=['startzeit'=>attendance_local((int)$a['proposed_start']),'endzeit'=>attendance_local((int)$a['proposed_end']),'absence_kind'=>$old['absence_kind']??null,'beschreibung'=>$old['beschreibung']??''];
                    [$from,$to]=booking_span($proposal);
                    booking_assert_no_overlap($db,(int)$a['user_id'],$from,$to,(int)($a['record_id']??0));
                }
                if ($a['cancel']) {
                    att_exec($db,'DELETE FROM zeiterfassung WHERE id=?',[$a['record_id']]);
                    att_exec($db,'UPDATE break_intervals SET ended_at=? WHERE ended_at IS NULL AND session_id IN (SELECT id FROM work_sessions WHERE record_id=?)',[time(),$a['record_id']]);
                    att_exec($db,'UPDATE work_sessions SET ended_at=? WHERE record_id=? AND ended_at IS NULL',[time(),$a['record_id']]);
                } elseif ($old) {
                    att_exec($db,'UPDATE zeiterfassung SET startzeit=?,endzeit=?,pause=?,planned_minutes=? WHERE id=?',[attendance_local((int)$a['proposed_start']),attendance_local((int)$a['proposed_end']),$a['proposed_pause'],$old['absence_kind']?(int)floor(($a['proposed_end']-$a['proposed_start'])/60)-$a['proposed_pause']:null,$a['record_id']]);
                    att_exec($db,'UPDATE break_intervals SET ended_at=? WHERE ended_at IS NULL AND session_id IN (SELECT id FROM work_sessions WHERE record_id=?)',[$a['proposed_end'],$a['record_id']]);
                    att_exec($db,'UPDATE work_sessions SET ended_at=? WHERE record_id=? AND ended_at IS NULL',[$a['proposed_end'],$a['record_id']]);
                    if (!att_one($db,'SELECT id FROM work_sessions WHERE record_id=?',[$a['record_id']])) { att_exec($db,'INSERT INTO work_sessions(user_id,record_id,started_at,ended_at) VALUES(?,?,?,?)',[$a['user_id'],$a['record_id'],$a['proposed_start'],$a['proposed_end']]); }
                } else {
                    att_exec($db,'INSERT INTO zeiterfassung(startzeit,endzeit,pause,beschreibung,standort,user_id) VALUES(?,?,?,?,?,?)',[attendance_local((int)$a['proposed_start']),attendance_local((int)$a['proposed_end']),$a['proposed_pause'],'Korrektur freigegeben','office',$a['user_id']]);
                    $id=(int)$db->lastInsertId();
                    att_exec($db,'INSERT INTO work_sessions(user_id,record_id,started_at,ended_at) VALUES(?,?,?,?)',[$a['user_id'],$id,$a['proposed_start'],$a['proposed_end']]);
                    att_exec($db,'UPDATE time_adjustments SET record_id=? WHERE id=?',[$id,$a['id']]);
                }
            }
            att_exec($db,'UPDATE time_adjustments SET status=?,reviewer_id=?,reviewed_at=?,review_reason=? WHERE id=?',[$decision,$user,time(),$reason,$a['id']]);
        });
    } else { throw new AttendanceError('Unbekannte Aktion.',400); }
}
