<?php
require_once __DIR__.'/config.php'; require_admin();
require_once __DIR__.'/lib/terminal.php'; require_once __DIR__.'/lib/terminal_ui.php';
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $action=$_POST['action']??'';
        $conn->exec('BEGIN IMMEDIATE');
        if ($action==='toggle') {
            $enabled=isset($_POST['enabled']) ? 1:0;
            if ($enabled && getenv('APP_TERMINAL_ENABLED')!=='1') { throw new AttendanceError('Zuerst APP_TERMINAL_ENABLED=1 auf dem Server setzen.'); }
            if (!$enabled && att_one($conn,'SELECT id FROM work_sessions WHERE ended_at IS NULL LIMIT 1')) { throw new AttendanceError('Zuerst offene Arbeitszeiten beenden.'); }
            if ($enabled && !attendance_enabled($conn) && att_one($conn,'SELECT id FROM zeiterfassung WHERE endzeit IS NULL LIMIT 1')) { throw new AttendanceError('Zuerst vorhandene offene Arbeitszeiten im bisherigen System beenden.'); }
            att_exec($conn,'UPDATE terminal_settings SET enabled=? WHERE id=1',[$enabled]);
        } elseif ($action==='site') {
            $name=trim((string)($_POST['name']??'')); if ($name===''||strlen($name)>100) { throw new AttendanceError('Standortnamen prüfen.',400); }
            att_exec($conn,'INSERT INTO terminal_sites(name) VALUES(?)',[$name]);
        } elseif ($action==='device') {
            attendance_require_enabled($conn);
            $name=trim((string)($_POST['name']??'')); $site=(int)($_POST['site_id']??0);
            if ($name===''||strlen($name)>100||!att_one($conn,'SELECT id FROM terminal_sites WHERE id=?',[$site])) { throw new AttendanceError('Name und Standort prüfen.',400); }
            $code=bin2hex(random_bytes(24));
            att_exec($conn,'INSERT INTO terminals(site_id,name,generation,pairing_hash,pairing_expires) VALUES(?,?,?,?,?)',[$site,$name,bin2hex(random_bytes(16)),hash('sha256',$code),time()+600]);
            $_SESSION['terminal_pairing']=['id'=>(int)$conn->lastInsertId(),'code'=>$code];
        } elseif ($action==='revoke_device') {
            att_exec($conn,'UPDATE terminals SET enabled=0,generation=?,pairing_hash=NULL,pairing_expires=NULL WHERE id=?',[bin2hex(random_bytes(16)),(int)($_POST['id']??0)]);
        } elseif ($action==='credential') {
            $user=(int)($_POST['user_id']??0); $site=(int)($_POST['site_id']??0); $code=trim((string)($_POST['employee_code']??'')); $pin=(string)($_POST['pin']??''); $card=trim((string)($_POST['card']??''));
            if (!att_one($conn,'SELECT id FROM users WHERE id=?',[$user])||!att_one($conn,'SELECT id FROM terminal_sites WHERE id=?',[$site])||!preg_match('/^[a-zA-Z0-9_-]{2,32}$/D',$code)||!preg_match('/^[0-9]{8}$/D',$pin)||strlen($card)>128) { throw new AttendanceError('Person, Standort, Personalcode und achtstellige PIN prüfen.',400); }
            att_exec($conn,'INSERT INTO employee_credentials(user_id,site_id,employee_code,pin_hash,card_hash,enabled) VALUES(?,?,?,?,?,1) ON CONFLICT(user_id) DO UPDATE SET site_id=excluded.site_id,employee_code=excluded.employee_code,pin_hash=excluded.pin_hash,card_hash=excluded.card_hash,enabled=1',[$user,$site,$code,password_hash($pin,PASSWORD_DEFAULT),$card===''?null:terminal_card($card)]);
        } elseif ($action==='revoke_credential') {
            att_exec($conn,'UPDATE employee_credentials SET enabled=0 WHERE user_id=?',[(int)($_POST['user_id']??0)]);
        } else { throw new AttendanceError('Unbekannte Aktion.',400); }
        $conn->exec('COMMIT'); header('Location: terminals.php?saved=1'); exit;
    } catch (Throwable $e) { $conn->exec('ROLLBACK'); $error=$e instanceof AttendanceError ? $e->getMessage():'Änderung nicht gespeichert. Personalcode, Karte oder Name möglicherweise schon vergeben.'; }
}
$sites=$conn->query('SELECT * FROM terminal_sites ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$users=$conn->query('SELECT id,username FROM users ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);
$devices=$conn->query('SELECT t.*,s.name AS site FROM terminals t JOIN terminal_sites s ON s.id=t.site_id ORDER BY t.id DESC')->fetchAll(PDO::FETCH_ASSOC);
$credentials=$conn->query('SELECT c.user_id,c.employee_code,c.enabled,c.card_hash,u.username,s.name AS site FROM employee_credentials c JOIN users u ON u.id=c.user_id JOIN terminal_sites s ON s.id=c.site_id')->fetchAll(PDO::FETCH_ASSOC);
$pair=$_SESSION['terminal_pairing']??null; unset($_SESSION['terminal_pairing']);
terminal_page('Praxis-Terminals');
?>
<p class="muted">Geräte koppeln, persönliche Zugänge verwalten und tatsächliche Pausen erfassen.</p>
<?php if ($error): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="notice">Änderung gespeichert.</div><?php endif; ?>
<?php if ($pair): ?><div class="notice"><strong>Gerät <?= h($pair['id']) ?> koppeln · zehn Minuten gültig</strong><p>Den folgenden Einmalcode am Gerätedienst eingeben. Er wird nur jetzt angezeigt.</p><code><?= h($pair['code']) ?></code></div><?php endif; ?>
<div class="card"><h2>Optionaler Terminal-Betrieb</h2><p>Bei Aktivierung verwenden Web und Terminal dieselben Buchungen. Pausen werden tatsächlich erfasst; es erfolgt kein automatischer Mindestpausenabzug. Nachträgliche Änderungen laufen über Korrekturanträge.</p><p>Serverfreigabe: <strong><?= getenv('APP_TERMINAL_ENABLED')==='1'?'vorhanden':'aus – APP_TERMINAL_ENABLED=1 erforderlich' ?></strong></p><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><label><input type="checkbox" name="enabled" <?= attendance_enabled($conn)?'checked':'' ?>> Modul aktivieren</label><button>Speichern</button></form></div>
<div class="grid"><section class="card"><h2>Neues Gerät</h2><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="device"><label>Gerätename</label><input name="name" maxlength="100" placeholder="Personaleingang" required><label>Standort</label><select name="site_id"><?php foreach($sites as $site): ?><option value="<?= h($site['id']) ?>"><?= h($site['name']) ?></option><?php endforeach; ?></select><div class="actions"><button>Einmalcode erzeugen</button></div></form><p class="muted">Kopplung im lokalen Gerätedienst, niemals durch Hinterlegen eines Admin-Passworts am Terminal.</p></section>
<section class="card"><h2>Persönlicher Terminal-Zugang</h2><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="credential"><label>Beschäftigte Person</label><select name="user_id"><?php foreach($users as $u): ?><option value="<?= h($u['id']) ?>"><?= h($u['username']) ?></option><?php endforeach; ?></select><label>Standort</label><select name="site_id"><?php foreach($sites as $site): ?><option value="<?= h($site['id']) ?>"><?= h($site['name']) ?></option><?php endforeach; ?></select><label>Personalcode</label><input name="employee_code" pattern="[a-zA-Z0-9_-]{2,32}" required autocomplete="off"><label>Neue PIN · 8 Ziffern</label><input name="pin" type="password" inputmode="numeric" pattern="[0-9]{8}" autocomplete="new-password" required><label>NFC-Kennung · optional</label><input name="card" maxlength="128" autocomplete="off"><small>Ein USB-Leser kann die Kennung hier als Tastatureingabe einlesen. Die PIN bleibt erforderlich. Speichern ersetzt den bisherigen Zugang einschließlich Karte.</small><div class="actions"><button>Zugang speichern / ersetzen</button></div></form></section></div>
<section class="card"><h2>Geräte</h2><div class="table-wrap"><table><thead><tr><th>Gerät</th><th>Standort</th><th>Status</th><th>Letzter Kontakt</th><th></th></tr></thead><tbody><?php foreach($devices as $d): ?><tr><td><?= h($d['name']) ?> #<?= h($d['id']) ?></td><td><?= h($d['site']) ?></td><td><?= $d['enabled']?'Aktiv':($d['pairing_hash']&&$d['pairing_expires']>time()?'Kopplung offen':'Gesperrt / abgelaufen') ?></td><td><?= h($d['last_seen']?attendance_local((int)$d['last_seen']):'—') ?></td><td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="revoke_device"><input type="hidden" name="id" value="<?= h($d['id']) ?>"><button class="danger">Sperren</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="card"><h2>Terminal-Zugänge</h2><div class="table-wrap"><table><thead><tr><th>Person</th><th>Personalcode</th><th>Standort</th><th>Zugang</th><th></th></tr></thead><tbody><?php foreach($credentials as $c): ?><tr><td><?= h($c['username']) ?></td><td><?= h($c['employee_code']) ?></td><td><?= h($c['site']) ?></td><td><?= $c['enabled']?($c['card_hash']?'PIN + Karte':'PIN'):'Gesperrt' ?></td><td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="revoke_credential"><input type="hidden" name="user_id" value="<?= h($c['user_id']) ?>"><button class="danger">Zugang sperren</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="card"><h2>Standort ergänzen</h2><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="site"><label>Name</label><input name="name" maxlength="100" required><div class="actions"><button>Standort anlegen</button></div></form></section>
<?php terminal_page_end(); ?>
