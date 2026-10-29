<?php
// Definitions only: the native bootstrap owns authentication/CSRF and this fixed
// operation owns only an idle SQLite connection's IMMEDIATE transaction.
require_once __DIR__.'/attendance.php';
require_once __DIR__.'/holiday_refill.php';

final class NativeHolidayRefillError extends RuntimeException
{
    public function __construct(public readonly int $status, string $message) { parent::__construct($message); }
}

/** Display hint only. Current authority is repeated while owning the writer lock. */
function native_holiday_refill_available(mixed $source, mixed $role, mixed $version): bool
{
    return $source==='local' && $role==='admin' && is_string($version)
        && preg_match('/\A[a-f0-9]{64}\z/', $version)===1;
}

/** Fixed command, trusted session identity only; no caller callback, URL or staged rows. */
function native_holiday_refill(PDO $db, int $user, string $role, #[SensitiveParameter] mixed $version, array $form): array
{
    if (($form['refill_holidays']??null)!=='1'
        || array_diff(array_keys($form), ['csrf_token','refill_holidays','holiday_year','confirm_bw_source','holiday_json'])!==[]) {
        throw new InvalidArgumentException('Nur die einzelne Feiertagsergänzung ohne weitere Verwaltungsfelder senden.');
    }
    $stage=chrono_holiday_refill_stage($form['holiday_year']??null,$form['confirm_bw_source']??null,$form['holiday_json']??null);
    $unconfirmed='Feiertagsergänzung nicht bestätigt. Neu laden und gespeichertes Jahr prüfen; nicht automatisch wiederholen.';
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite'
        || $db->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION || $db->inTransaction()) {
        throw new NativeHolidayRefillError(503,$unconfirmed);
    }
    $saved=[];
    foreach (['audit_actor','audit_reason'] as $key) { $saved[$key]=[array_key_exists($key,$GLOBALS),$GLOBALS[$key]??null]; }
    $owned=false;$commitAttempted=false;
    try {
        if ($db->exec('BEGIN IMMEDIATE')===false) { throw new RuntimeException(); }
        $owned=true;
        if (!$db->inTransaction()) { throw new RuntimeException(); }
        $actor=att_one($db,'SELECT role,auth_source,password FROM users WHERE id=?',[$user]);
        if ($user<1 || !is_string($version) || preg_match('/\A[a-f0-9]{64}\z/',$version)!==1
            || !$actor || !is_string($actor['password']) || $actor['password']===''
            || !hash_equals($version,hash('sha256',$actor['password'])) || $actor['role']!==$role) {
            throw new NativeHolidayRefillError(401,'Sitzung nicht mehr gültig. Bitte neu anmelden.');
        }
        if (!native_holiday_refill_available($actor['auth_source'],$actor['role'],$version)) {
            throw new NativeHolidayRefillError(403,'Nur aktuelle lokale Verwaltung darf Feiertage ergänzen.');
        }
        $GLOBALS['audit_actor']='web:'.$user;
        $GLOBALS['audit_reason']='Feiertage ergänzen BW '.$stage['year'].'; Antwort SHA256 '.$stage['source_sha256'];
        // The unchanged core reparses raw input and checks the entire year before INSERT.
        $result=chrono_holiday_refill_locked($db,$form['holiday_year']??null,$form['confirm_bw_source']??null,$form['holiday_json']??null);
        $commitAttempted=true;
        if ($db->exec('COMMIT')===false || $db->inTransaction()) { throw new RuntimeException(); }
        return $result;
    } catch (Throwable $error) {
        $rolledBack=false;
        if ($owned && $db->inTransaction()) {
            try { $rolledBack=$db->exec('ROLLBACK')!==false && !$db->inTransaction(); }
            catch (Throwable) { /* Unknown outcome stays unknown; do not retry. */ }
        }
        if (!$commitAttempted && $rolledBack
            && ($error instanceof InvalidArgumentException || $error instanceof DomainException || $error instanceof NativeHolidayRefillError)) {
            throw $error;
        }
        throw new NativeHolidayRefillError(503,$unconfirmed);
    } finally {
        foreach ($saved as $key=>[$existed,$value]) { if ($existed) { $GLOBALS[$key]=$value; } else { unset($GLOBALS[$key]); } }
    }
}

/** Only a one-use own bounded postcommit receipt may reach the view. */
function native_holiday_refill_receipt(mixed $receipt, int $user): ?array
{
    if (!is_array($receipt) || ($receipt['actor']??null)!==$user || !is_array($receipt['result']??null)) { return null; }
    $value=$receipt['result'];$result=[];
    foreach (['year'=>9999,'inserted'=>366,'unchanged'=>366,'conditional_omitted'=>366] as $key=>$max) {
        if (!is_int($value[$key]??null) || $value[$key]<($key==='year'?1:0) || $value[$key]>$max) { return null; }
        $result[$key]=$value[$key];
    }
    return $result;
}
