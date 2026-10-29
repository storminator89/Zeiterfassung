<?php

/** Shared native/Laravel admin form validation only; never normalize or write. */
function admin_validate_user_fields(mixed $username, mixed $email, mixed $regularHours, mixed $carryIn): void
{
    if (!is_string($username) || $username === '' || $username !== trim($username)
        || strlen($username) > 100 || preg_match('//u', $username) !== 1
        || preg_match('/[\x00-\x1f\x7f]/', $username)) {
        throw new InvalidArgumentException('Benutzername muss 1 bis 100 gültige UTF-8-Bytes ohne Steuerzeichen oder äußere Leerzeichen enthalten.');
    }
    if (!is_string($email) || strlen($email) > 254
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new InvalidArgumentException('Eine gültige E-Mail-Adresse mit höchstens 254 Bytes ist erforderlich.');
    }
    $regular = admin_user_numeric_hours($regularHours);
    if ($regular < 0 || $regular > 24) {
        throw new InvalidArgumentException('Das Tages-Soll muss zwischen 0 und 24 Stunden liegen.');
    }
    $carry = admin_user_numeric_hours($carryIn);
    // A signed opening balance is not a daily target. No arbitrary business
    // maximum: keep seconds/minutes conversions inside PHP's integer range.
    if (abs($carry) > intdiv(PHP_INT_MAX, 3600) - 1) {
        throw new InvalidArgumentException('Das Anfangsguthaben liegt außerhalb des verarbeitbaren Stundenbereichs.');
    }
}

/** Finite HTML-number strings only, including decimals/exponents, no coercion. */
function admin_user_numeric_hours(mixed $value): float
{
    if (!is_string($value) || strlen($value) > 64
        || preg_match('/\A[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\z/D', $value) !== 1
        || !is_finite((float)$value)) {
        throw new InvalidArgumentException('Arbeitsstunden und Anfangsguthaben müssen endliche Zahlen sein; ohne Guthaben bitte 0 angeben.');
    }
    return (float)$value;
}
