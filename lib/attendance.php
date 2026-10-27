<?php
require_once __DIR__.'/booking.php';
require_once __DIR__.'/reporting.php';
// UTC event times are authoritative; zeiterfassung is the legacy reporting projection.
class AttendanceError extends RuntimeException {
    public function __construct(string $message, public int $status = 409) { parent::__construct($message); }
}
function att_one(PDO $db, string $sql, array $args = []): ?array {
    $s = $db->prepare($sql); $s->execute($args); $row = $s->fetch(PDO::FETCH_ASSOC); $s->closeCursor(); return $row ?: null;
}
function att_exec(PDO $db, string $sql, array $args = []): void { $s=$db->prepare($sql); $s->execute($args); $s->closeCursor(); }
function attendance_migrate(PDO $db): void {
    $db->exec('PRAGMA busy_timeout=5000');
    $db->exec('CREATE TABLE IF NOT EXISTS app_migrations (version INTEGER PRIMARY KEY)');
    if (att_one($db, 'SELECT version FROM app_migrations WHERE version=1')) { return; }
    $db->exec('BEGIN IMMEDIATE');
    try {
        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS terminal_settings (id INTEGER PRIMARY KEY CHECK(id=1), enabled INTEGER NOT NULL DEFAULT 0);
INSERT OR IGNORE INTO terminal_settings(id,enabled) VALUES(1,0);
CREATE TABLE IF NOT EXISTS terminal_sites (id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE);
INSERT OR IGNORE INTO terminal_sites(id,name) VALUES(1,'Praxis');
CREATE TABLE IF NOT EXISTS terminals (id INTEGER PRIMARY KEY, site_id INTEGER NOT NULL, name TEXT NOT NULL, generation TEXT NOT NULL, pairing_hash TEXT, pairing_expires INTEGER, enabled INTEGER NOT NULL DEFAULT 0, last_seen INTEGER);
CREATE TABLE IF NOT EXISTS employee_credentials (user_id INTEGER PRIMARY KEY, site_id INTEGER NOT NULL, employee_code TEXT NOT NULL UNIQUE, pin_hash TEXT NOT NULL, card_hash TEXT UNIQUE, enabled INTEGER NOT NULL DEFAULT 1);
CREATE TABLE IF NOT EXISTS terminal_nonces (terminal_id INTEGER NOT NULL, nonce TEXT NOT NULL, expires INTEGER NOT NULL, PRIMARY KEY(terminal_id,nonce));
CREATE TABLE IF NOT EXISTS terminal_limits (bucket TEXT PRIMARY KEY, starts INTEGER NOT NULL, attempts INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS terminal_grants (token_hash TEXT PRIMARY KEY, terminal_id INTEGER NOT NULL, user_id INTEGER NOT NULL, credential_version TEXT NOT NULL, expires INTEGER NOT NULL, used_event TEXT);
CREATE TABLE IF NOT EXISTS work_sessions (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, record_id INTEGER NOT NULL UNIQUE, started_at INTEGER NOT NULL, ended_at INTEGER);
CREATE UNIQUE INDEX IF NOT EXISTS one_open_session ON work_sessions(user_id) WHERE ended_at IS NULL;
CREATE TABLE IF NOT EXISTS break_intervals (id INTEGER PRIMARY KEY, session_id INTEGER NOT NULL, started_at INTEGER NOT NULL, ended_at INTEGER);
CREATE UNIQUE INDEX IF NOT EXISTS one_open_break ON break_intervals(session_id) WHERE ended_at IS NULL;
CREATE TABLE IF NOT EXISTS attendance_events (id INTEGER PRIMARY KEY, source TEXT NOT NULL, event_id TEXT NOT NULL, user_id INTEGER NOT NULL, session_id INTEGER NOT NULL, kind TEXT NOT NULL, received_at INTEGER NOT NULL, payload_hash TEXT NOT NULL, response TEXT NOT NULL, UNIQUE(source,event_id));
CREATE TABLE IF NOT EXISTS time_adjustments (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, record_id INTEGER, proposed_start INTEGER NOT NULL, proposed_end INTEGER NOT NULL, proposed_pause INTEGER NOT NULL, reason TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'pending', reviewer_id INTEGER, reviewed_at INTEGER, review_reason TEXT, original_json TEXT, created_at INTEGER NOT NULL);
INSERT OR IGNORE INTO app_migrations(version) VALUES(1);
SQL);
        $db->exec('COMMIT');
    } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
}
function attendance_enabled(PDO $db): bool {
    return getenv('APP_TERMINAL_ENABLED') === '1' && (int)(att_one($db,'SELECT enabled FROM terminal_settings WHERE id=1')['enabled'] ?? 0) === 1;
}
function attendance_require_enabled(PDO $db): void {
    if (!attendance_enabled($db)) { throw new AttendanceError('Terminal-Modul ist deaktiviert.',404); }
}
function attendance_local(int $time): string { return (new DateTimeImmutable('@'.$time))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('Y-m-d\TH:i:sP'); }
function attendance_status(PDO $db, int $user): array {
    $session=att_one($db,'SELECT * FROM work_sessions WHERE user_id=? AND ended_at IS NULL',[$user]);
    if (!$session) { $old=att_one($db,'SELECT id FROM zeiterfassung WHERE user_id=? AND endzeit IS NULL',[$user]); if ($old) { return ['state'=>'working','record_id'=>(int)$old['id']]; } }
    $break=$session ? att_one($db,'SELECT * FROM break_intervals WHERE session_id=? AND ended_at IS NULL',[$session['id']]) : null;
    return ['state'=>$session ? ($break ? 'paused':'working'):'absent', 'record_id'=>$session ? (int)$session['record_id']:null];
}
function attendance_assert_unmanaged(PDO $db, array $ids): void {
    foreach ($ids as $id) {
        if (att_one($db,'SELECT id FROM work_sessions WHERE record_id=?',[(int)$id])) {
            throw new AttendanceError('Erfasste Arbeitszeiten bitte über einen Korrekturantrag ändern.');
        }
    }
}
/**
 * Caller owns the active transaction. A trusted optional runner receives only
 * the derived actor/reason and this fixed mutation continuation, after native
 * validation and replay decisions. Native fallback preserves audit globals.
 */
function attendance_transition_locked(PDO $db, int $user, string $source, string $eventId, string $action, bool $confirm=false, ?int $expectedRecord=null, ?Closure $auditRunner=null, bool $requireExpectedRecord=false): array {
    if (!preg_match('/^[A-Za-z0-9_-]{16,80}$/D',$eventId)) { throw new AttendanceError('Ungültige Ereignis-ID.',400); }
    if (!in_array($action,['start','pause_start','pause_end','end'],true)) { throw new AttendanceError('Ungültige Aktion.',400); }
    if (!att_one($db,'SELECT id FROM users WHERE id=?',[$user])) { throw new AttendanceError('Konto nicht verfügbar.',403); }
    $hash=hash('sha256',json_encode([$user,$action,$confirm,$expectedRecord]));
    $old=att_one($db,'SELECT * FROM attendance_events WHERE source=? AND event_id=?',[$source,$eventId]);
    if ($old) {
        if (!hash_equals($old['payload_hash'],$hash)) { throw new AttendanceError('Ereignis-ID bereits anders verwendet.'); }
        return json_decode($old['response'],true);
    }
    // Fixed Web callers require a binding only for NEW non-start events.
    // Existing committed legacy payloads above still replay with their original hash.
    if ($requireExpectedRecord && $action !== 'start' && $expectedRecord === null) {
        throw new AttendanceError('Sitzungsbindung fehlt. Keine neue Buchung ausgeführt; Status neu laden und die gewünschte Aktion anschließend bewusst wählen.');
    }
    $auditRunner ??= static function (string $actor, string $reason, Closure $operation): array {
        $GLOBALS['audit_actor']=$actor;
        $GLOBALS['audit_reason']=$reason;
        return $operation();
    };
    return $auditRunner($source.':user:'.$user,'Stempelereignis '.$action.' / '.$eventId,static function () use ($db,$user,$source,$eventId,$action,$confirm,$expectedRecord,$hash): array {
    $now=time();
    $s=att_one($db,'SELECT * FROM work_sessions WHERE user_id=? AND ended_at IS NULL',[$user]);
    if (!$s && $action!=='start') {
        $rows=$db->prepare('SELECT * FROM zeiterfassung WHERE user_id=? AND endzeit IS NULL');$rows->execute([$user]);$open=$rows->fetchAll(PDO::FETCH_ASSOC);$rows->closeCursor();
        if (count($open)>1) { throw new AttendanceError('Mehrere offene Altbuchungen: zuerst über Korrekturanträge bereinigen.'); }
        if ($open) {
            $oldStart=booking_time($open[0]['startzeit']);
            if ($oldStart===false || $oldStart>$now) { throw new AttendanceError('Ungültige Altbuchung: Korrekturantrag erforderlich.'); }
            att_exec($db,'INSERT INTO work_sessions(user_id,record_id,started_at) VALUES(?,?,?)',[$user,$open[0]['id'],$oldStart]);
            $s=att_one($db,'SELECT * FROM work_sessions WHERE id=?',[(int)$db->lastInsertId()]);
        }
    }
    if ($action==='start') {
        if ($s || att_one($db,'SELECT id FROM zeiterfassung WHERE user_id=? AND endzeit IS NULL',[$user])) { throw new AttendanceError('Bereits eine offene Arbeitszeit vorhanden.'); }
        booking_assert_no_overlap($db,$user,$now,$now+1);
        att_exec($db,'INSERT INTO zeiterfassung(startzeit,pause,beschreibung,standort,user_id) VALUES(?,0,?,?,?)',[attendance_local($now),'','office',$user]);
        $record=(int)$db->lastInsertId();
        att_exec($db,'INSERT INTO work_sessions(user_id,record_id,started_at) VALUES(?,?,?)',[$user,$record,$now]);
        $s=att_one($db,'SELECT * FROM work_sessions WHERE id=?',[(int)$db->lastInsertId()]);
    } else {
        if (!$s) { throw new AttendanceError('Keine offene Arbeitszeit vorhanden.'); }
        if ($expectedRecord !== null && (int)$s['record_id'] !== $expectedRecord) { throw new AttendanceError('Andere Arbeitszeit aktiv.'); }
        $lastEvent=(int)(att_one($db,'SELECT MAX(received_at) AS last FROM attendance_events WHERE session_id=?',[$s['id']])['last']??0);
        $lastBreak=(int)(att_one($db,'SELECT MAX(MAX(started_at,COALESCE(ended_at,started_at))) AS last FROM break_intervals WHERE session_id=?',[$s['id']])['last']??0);
        if ($now < max((int)$s['started_at'],$lastEvent,$lastBreak)) { throw new AttendanceError('Serverzeit prüfen.'); }
        $break=att_one($db,'SELECT * FROM break_intervals WHERE session_id=? AND ended_at IS NULL',[$s['id']]);
        if ($break && $now < (int)$break['started_at']) { throw new AttendanceError('Serverzeit prüfen.'); }
        if ($action==='pause_start') {
            if ($break) { throw new AttendanceError('Pause läuft bereits.'); }
            att_exec($db,'INSERT INTO break_intervals(session_id,started_at) VALUES(?,?)',[$s['id'],$now]);
        } elseif ($action==='pause_end') {
            if (!$break) { throw new AttendanceError('Keine Pause aktiv.'); }
            att_exec($db,'UPDATE break_intervals SET ended_at=? WHERE id=?',[$now,$break['id']]);
        } else {
            if ($break && !$confirm) { throw new AttendanceError('Aktive Pause und Arbeitszeit gemeinsam beenden? Bitte bestätigen.'); }
            if ($break) { att_exec($db,'UPDATE break_intervals SET ended_at=? WHERE id=?',[$now,$break['id']]); }
            att_exec($db,'UPDATE work_sessions SET ended_at=? WHERE id=?',[$now,$s['id']]);
            att_exec($db,'UPDATE zeiterfassung SET endzeit=? WHERE id=?',[attendance_local($now),$s['record_id']]);
        }
        $seconds=(int)(att_one($db,'SELECT COALESCE(SUM(ended_at-started_at),0) AS total FROM break_intervals WHERE session_id=? AND ended_at IS NOT NULL',[$s['id']])['total']);
        att_exec($db,'UPDATE zeiterfassung SET pause=? WHERE id=?',[(int)floor($seconds/60),$s['record_id']]);
    }
    $response=['success'=>true,'message'=>'Buchung bestätigt.','time'=>gmdate('c',$now),'local_time'=>attendance_local($now),'event_id'=>$eventId,'record_id'=>(int)$s['record_id']] + attendance_status($db,$user);
    if ($action==='end') {
        $actual=(int)att_one($db,'SELECT pause FROM zeiterfassung WHERE id=?',[$s['record_id']])['pause'];
        $required=(int)(att_one($db,'SELECT MAX(minimum_pause) AS minutes FROM pause_settings WHERE hours_threshold*3600 < CAST(? AS INTEGER)',[max(0,$now-(int)$s['started_at']-$seconds)])['minutes']??0);
        if ($actual<$required) {
            $response['pause_warning']='Erfasste Pause: '.$actual.' Minuten; konfigurierte Mindestpause: '.$required.' Minuten. Bitte prüfen.';
            $response['message'].=' '.$response['pause_warning'];
        }
        // A pre-booked next-day absence must never make the real end fail or
        // invent a shortened interval. Derive evidence from effective rows;
        // corrections resolve that evidence without rewriting original punches.
        $rows=$db->prepare('SELECT * FROM zeiterfassung WHERE user_id=?');
        $rows->execute([$user]);$records=$rows->fetchAll(PDO::FETCH_ASSOC);$rows->closeCursor();
        $overlaps=[];
        foreach (report_absence_conflicts($records) as $conflict) {
            if ($conflict['work_id']===(int)$s['record_id']) { $overlaps[]=$conflict['absence_id']; }
        }
        if ($overlaps!==[]) {
            $response['overlap_record_ids']=array_values(array_unique($overlaps));
            $response['overlap_warning']='Arbeitsende gespeichert. Arbeitszeit überschneidet sich mit einer ganztägigen Abwesenheit. Betroffene Summen sind bis zur manuellen Klärung nicht abschließend. Bitte Korrekturanträge prüfen.';
            $response['message'].=' '.$response['overlap_warning'];
        }
    }
    att_exec($db,'INSERT INTO attendance_events(source,event_id,user_id,session_id,kind,received_at,payload_hash,response) VALUES(?,?,?,?,?,?,?,?)',[$source,$eventId,$user,$s['id'],$action,$now,$hash,json_encode($response)]);
    return $response;
    });
}
function attendance_transition(PDO $db, int $user, string $source, string $eventId, string $action, bool $confirm=false, ?int $record=null, bool $requireExpectedRecord=false): array {
    $db->exec('BEGIN IMMEDIATE');
    try { $r=attendance_transition_locked($db,$user,$source,$eventId,$action,$confirm,$record,null,$requireExpectedRecord); $db->exec('COMMIT'); return $r; }
    catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
}
/** Raw Web form only. Omission is legacy replay, never a new unbound non-start mutation. */
function attendance_expected_record(array $form): ?int {
    if (!array_key_exists('expected_record',$form)) { return null; }
    $raw=$form['expected_record'];
    $maximum=(string)PHP_INT_MAX;
    if (!is_string($raw) || preg_match('/\A[1-9][0-9]*\z/',$raw)!==1
        || strlen($raw)>strlen($maximum)
        || (strlen($raw)===strlen($maximum) && strcmp($raw,$maximum)>0)) {
        throw new AttendanceError('Ungültige Sitzungsbindung. Bitte den Status neu laden.',400);
    }
    return (int)$raw;
}
function attendance_json(callable $action): never {
    header('Content-Type: application/json; charset=UTF-8');
    try { echo json_encode($action(),JSON_THROW_ON_ERROR); }
    catch (AttendanceError $e) { http_response_code($e->status); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    catch (Throwable $e) { error_log('Attendance failure: '.$e->getMessage()); http_response_code(503); echo json_encode(['success'=>false,'message'=>'Buchung nicht bestätigt. Verbindung prüfen und denselben Vorgang erneut versuchen.']); }
    exit;
}
