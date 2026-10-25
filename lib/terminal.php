<?php
function terminal_key(array $device): string { return hash_hmac('sha256',$device['id'].':'.$device['generation'],app_secret('terminal-devices')); }
function terminal_card(string $card): string { return hash_hmac('sha256',strtoupper(trim($card)),app_secret('terminal-cards')); }
function terminal_version(array $c): string { return hash('sha256',json_encode([$c['user_id'],$c['site_id'],$c['pin_hash'],$c['card_hash'],$c['enabled']])); }
function terminal_limit(PDO $db, string $key, int $limit): void {
    $now=time(); $key=hash('sha256',$key);
    att_exec($db,'DELETE FROM terminal_limits WHERE starts<?',[$now-600]);
    att_exec($db,'INSERT INTO terminal_limits(bucket,starts,attempts) VALUES(?,?,1) ON CONFLICT(bucket) DO UPDATE SET attempts=attempts+1',[$key,$now]);
    if ((int)att_one($db,'SELECT attempts FROM terminal_limits WHERE bucket=?',[$key])['attempts'] > $limit) { throw new AttendanceError('Zu viele Versuche. Bitte zehn Minuten warten.',429); }
}
function terminal_auth(PDO $db, string $body): array {
    $id=$_SERVER['HTTP_X_TERMINAL_ID'] ?? ''; $stamp=$_SERVER['HTTP_X_TERMINAL_TIME'] ?? ''; $nonce=$_SERVER['HTTP_X_TERMINAL_NONCE'] ?? ''; $signature=$_SERVER['HTTP_X_TERMINAL_SIGNATURE'] ?? '';
    if (!ctype_digit($id) || !ctype_digit($stamp) || abs(time()-(int)$stamp)>90 || !preg_match('/^[a-f0-9]{32}$/D',$nonce)) { throw new AttendanceError('Geräteanmeldung fehlgeschlagen.',401); }
    $device=att_one($db,'SELECT * FROM terminals WHERE id=? AND enabled=1',[(int)$id]);
    if (!$device || !hash_equals(hash_hmac('sha256',"terminal-v1\n$id\n$stamp\n$nonce\n$body",terminal_key($device)),$signature)) { throw new AttendanceError('Geräteanmeldung fehlgeschlagen.',401); }
    att_exec($db,'DELETE FROM terminal_nonces WHERE expires<?',[time()]);
    try { att_exec($db,'INSERT INTO terminal_nonces(terminal_id,nonce,expires) VALUES(?,?,?)',[$id,$nonce,time()+180]); }
    catch (PDOException $e) { if ($e->getCode()==='23000') { throw new AttendanceError('Wiederholte Gerätesignatur.',409); } throw $e; }
    return $device;
}
function terminal_handle(PDO $db, array $input, string $body): array {
    attendance_require_enabled($db);
    if (($input['op'] ?? '')==='pair') {
        terminal_limit($db,'pair:'.($_SERVER['REMOTE_ADDR']??''),20);
        $db->exec('BEGIN IMMEDIATE');
        try {
            attendance_require_enabled($db);
            $device=att_one($db,'SELECT * FROM terminals WHERE id=?',[(int)($input['device_id']??0)]);
            $code=$input['code']??'';
            if (!$device || !is_string($code) || !$device['pairing_hash'] || $device['pairing_expires']<time() || !hash_equals($device['pairing_hash'],hash('sha256',$code))) { throw new AttendanceError('Kopplungscode ungültig oder abgelaufen.',401); }
            att_exec($db,'UPDATE terminals SET pairing_hash=NULL,pairing_expires=NULL,enabled=1,last_seen=? WHERE id=?',[time(),$device['id']]);
            $db->exec('COMMIT');
            return ['success'=>true,'device_id'=>(int)$device['id'],'key'=>terminal_key($device),'name'=>$device['name']];
        } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
    }
    $device=terminal_auth($db,$body);
    $op=$input['op']??'';
    if ($op==='identify') {
        terminal_limit($db,'identify-device:'.$device['id'],60);
        $code=$input['employee_code']??''; $card=$input['card']??''; $pin=$input['pin']??'';
        if (!is_string($code)||!is_string($card)||!is_string($pin)||strlen($code)>64||strlen($card)>128||!preg_match('/^[0-9]{8}$/D',$pin)) { throw new AttendanceError('Anmeldung fehlgeschlagen.',401); }
        $c=$card!=='' ? att_one($db,'SELECT * FROM employee_credentials WHERE card_hash=?',[terminal_card($card)]) : att_one($db,'SELECT * FROM employee_credentials WHERE employee_code=?',[$code]);
        terminal_limit($db,'identify-person:'.($c['user_id']??hash('sha256',$code.$card)),10);
        if (!$c || !$c['enabled'] || (int)$c['site_id']!==(int)$device['site_id'] || !password_verify($pin,$c['pin_hash'])) { throw new AttendanceError('Anmeldung fehlgeschlagen.',401); }
        $user=att_one($db,'SELECT id,username FROM users WHERE id=?',[$c['user_id']]);
        if (!$user) { throw new AttendanceError('Anmeldung fehlgeschlagen.',401); }
        $token=bin2hex(random_bytes(32));
        att_exec($db,'DELETE FROM terminal_grants WHERE expires<?',[time()-86400]);
        att_exec($db,'INSERT INTO terminal_grants(token_hash,terminal_id,user_id,credential_version,expires) VALUES(?,?,?,?,?)',[hash('sha256',$token),$device['id'],$user['id'],terminal_version($c),time()+10]);
        return ['success'=>true,'grant'=>$token,'expires_in'=>10,'name'=>$user['username']] + attendance_status($db,(int)$user['id']);
    }
    $db->exec('BEGIN IMMEDIATE');
    try {
        attendance_require_enabled($db);
        $current=att_one($db,'SELECT * FROM terminals WHERE id=? AND enabled=1',[$device['id']]);
        if (!$current || !hash_equals($current['generation'],$device['generation'])) { throw new AttendanceError('Gerät gesperrt.',403); }
        if ($op==='heartbeat') {
            att_exec($db,'UPDATE terminals SET last_seen=? WHERE id=?',[time(),$device['id']]); $r=['success'=>true,'time'=>gmdate('c')];
        } elseif ($op==='event') {
            $grant=$input['grant']??''; $id=$input['event_id']??'';
            if (!is_string($grant)||!is_string($id)||!is_string($input['action']??null)) { throw new AttendanceError('Ungültige Anfrage.',400); }
            $g=att_one($db,'SELECT * FROM terminal_grants WHERE token_hash=? AND terminal_id=?',[hash('sha256',$grant),$device['id']]);
            if (!$g || ($g['used_event']===null && $g['expires']<time()) || ($g['used_event']!==null && $g['used_event']!==$id)) { throw new AttendanceError('Bitte erneut anmelden.',401); }
            $c=att_one($db,'SELECT * FROM employee_credentials WHERE user_id=?',[$g['user_id']]);
            if (!$c || !$c['enabled'] || (int)$c['site_id']!==(int)$current['site_id'] || !hash_equals($g['credential_version'],terminal_version($c))) { throw new AttendanceError('Berechtigung wurde geändert.',403); }
            $r=attendance_transition_locked($db,(int)$g['user_id'],'terminal:'.$device['id'],$id,$input['action'],($input['confirm_end_pause']??false)===true);
            att_exec($db,'UPDATE terminal_grants SET used_event=? WHERE token_hash=?',[$id,$g['token_hash']]);
            att_exec($db,'UPDATE terminals SET last_seen=? WHERE id=?',[time(),$device['id']]);
        } else { throw new AttendanceError('Unbekannte Aktion.',400); }
        $db->exec('COMMIT'); return $r;
    } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
}
