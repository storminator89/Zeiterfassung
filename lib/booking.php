<?php
/** Stored offset-free legacy timestamps have always meant Berlin local time. */
function booking_time(string $value): int {
    if ($value==='') { throw new AttendanceError('Leerer Buchungszeitpunkt.',400); }
    try { $date=new DateTimeImmutable($value,new DateTimeZone('Europe/Berlin')); }
    catch (Throwable $e) { throw new AttendanceError('Ungültiger Buchungszeitpunkt.',400); }
    $errors=DateTimeImmutable::getLastErrors();
    if ($errors && ($errors['warning_count'] || $errors['error_count'])) { throw new AttendanceError('Ungültiger Buchungszeitpunkt.',400); }
    return $date->getTimestamp();
}
function booking_local_date(int $time): DateTimeImmutable {
    return (new DateTimeImmutable('@'.$time))->setTimezone(new DateTimeZone('Europe/Berlin'));
}
function booking_is_absence(array $record): bool {
    return !empty($record['absence_kind']) || in_array($record['beschreibung']??'', ['Urlaub','Krankheit','Feiertag'], true);
}
/** Credits occupy the entire local calendar day, not their compatibility time interval. */
function booking_span(array $record): array {
    $start=booking_time($record['startzeit']);
    if (booking_is_absence($record)) {
        $day=booking_local_date($start)->setTime(0,0);
        return [$day->getTimestamp(),$day->modify('+1 day')->getTimestamp()];
    }
    return [$start,empty($record['endzeit'])?PHP_INT_MAX:booking_time($record['endzeit'])];
}
function booking_assert_no_overlap(PDO $db,int $user,int $start,int $end,int $except=0): void {
    $stmt=$db->prepare('SELECT * FROM zeiterfassung WHERE user_id=? AND id<>?');
    $stmt->execute([$user,$except]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);$stmt->closeCursor();
    foreach ($rows as $row) {
        [$from,$to]=booking_span($row);
        if ($from<$end && $to>$start) { throw new AttendanceError('Zeitraum überschneidet sich mit einer anderen Buchung oder ganztägigen Abwesenheit.'); }
    }
}
function booking_validate_correction(?array $old,int $start,int $end,int $pause,int $now): void {
    if ($end<=$start || $end-$start>7*86400 || $pause<0 || $pause>intdiv($end-$start,60)) {
        throw new AttendanceError('Zeitraum und tatsächliche Pause prüfen.',400);
    }
    if ($old && booking_is_absence($old)) {
        if (booking_local_date($start)->format('H:i:s')!=='00:00:00' || $pause!==0 || $end-$start>1440*60 || ($end-$start)%60!==0) {
            throw new AttendanceError('Gutschriften ab 00:00 Uhr Ortszeit, mit ganzen Sollminuten und ohne Pause korrigieren (höchstens 1440 Minuten).',400);
        }
    } elseif ($end>$now) {
        throw new AttendanceError('Arbeitszeit kann erst nach ihrem tatsächlichen Ende nachgetragen werden.',400);
    }
}
