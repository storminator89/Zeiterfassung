<?php
// Definitions only: known-refusal display, never source validation or a write/retry capability.
require_once __DIR__.'/native_holiday_refill.php';
require_once __DIR__.'/holiday_refill_draft.php';

/** Null keeps mixed/unrecognized Native commands on the original abort path. */
function native_holiday_refill_refusal_draft(PDO $db,int $user,string $role,#[SensitiveParameter] mixed $version,array $form): ?array
{
    if (($form['refill_holidays']??null)!=='1'
        || array_diff(array_keys($form),['csrf_token','refill_holidays','holiday_year','confirm_bw_source','holiday_json'])!==[]) {
        return null;
    }
    $unconfirmed='Feiertagsergänzung nicht bestätigt. Neu laden und gespeichertes Jahr prüfen; nicht automatisch wiederholen.';
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite'
        || $db->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION || $db->inTransaction()) {
        throw new NativeHolidayRefillError(503,$unconfirmed);
    }
    $owned=false;$commitAttempted=false;
    try {
        if (!$db->beginTransaction()) { throw new RuntimeException(); }
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
        // The existing display-only helper never retains confirmation, CSRF or other fields.
        $draft=holiday_refill_rejected_draft($form);
        $commitAttempted=true;
        if (!$db->commit() || $db->inTransaction()) { throw new RuntimeException(); }
        return $draft;
    } catch (Throwable $error) {
        $rolledBack=false;
        if ($owned && $db->inTransaction()) {
            try { $rolledBack=$db->rollBack() && !$db->inTransaction(); }
            catch (Throwable) { /* Unconfirmed read cleanup is not an authorized display. */ }
        }
        if (!$commitAttempted && $rolledBack && $error instanceof NativeHolidayRefillError) { throw $error; }
        throw new NativeHolidayRefillError(503,$unconfirmed);
    }
}
