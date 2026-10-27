<?php

/** Shared fixed SQL only. Native callers retain their existing floatval/execute path. */
function settings_carry_in_prepare(PDO $conn): PDOStatement
{
    $updateSql = "UPDATE users SET ueberstunden = :ueberstunden WHERE id = :id";
    return $conn->prepare($updateSql);
}

/**
 * Laravel raw-form contract only; NEVER invoked by the native Settings handler.
 * No whitespace/comma/coercion, business rounding, magnitude policy or data repair.
 * REAL remains binary64, not exact decimal accounting. Keep the original text for binding.
 * @return array{text: string, value: float}
 */
function settings_carry_in_value(mixed $raw): array
{
    if (!is_string($raw) || !preg_match('/\A[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\z/', $raw)) {
        throw new InvalidArgumentException('Bitte eine gültige Zahl mit optionalem Vorzeichen, Dezimalpunkt und Exponent eingeben; keine Leerzeichen oder Dezimalkommas.');
    }
    $value = (float)$raw;
    if (!is_finite($value)) {
        throw new InvalidArgumentException('Der Überstunden-Übertrag muss eine endliche Zahl sein.');
    }
    $mantissa = preg_split('/[eE]/', $raw, 2)[0];
    if ($value == 0.0 && strpbrk($mantissa, '123456789') !== false) {
        throw new InvalidArgumentException('Der Wert ist für das vorhandene Zahlenformat zu klein und würde zu null werden.');
    }
    $decimal = settings_carry_in_decimal($raw);
    if ($decimal !== settings_carry_in_decimal(settings_carry_in_float_text($value))
        || ($decimal['exponent'] >= 0 && $decimal !== settings_carry_in_decimal(sprintf('%.0F', $value)))) {
        throw new InvalidArgumentException('Der Wert kann im vorhandenen REAL-Zahlenformat nicht ohne zusätzliche Stellenverluste dargestellt werden. Bitte weniger Stellen verwenden.');
    }

    return ['text' => $raw, 'value' => $value];
}

/** Numerical decimal normalization without expanding exponents or using float arithmetic. */
function settings_carry_in_decimal(string $raw): array
{
    if (!preg_match('/\A([+-]?)(?:([0-9]+)(?:\.([0-9]*))?|\.([0-9]+))(?:[eE]([+-]?[0-9]+))?\z/', $raw, $parts, PREG_UNMATCHED_AS_NULL)) {
        throw new InvalidArgumentException('Der Zahlenwert ist nicht darstellbar.');
    }
    $fraction = $parts[3] ?? $parts[4] ?? '';
    $digits = ltrim(($parts[2] ?? '').$fraction, '0');
    if ($digits === '') {
        return ['negative' => false, 'digits' => '0', 'exponent' => 0];
    }
    $tail = strlen($digits) - strlen(rtrim($digits, '0'));
    $digits = rtrim($digits, '0');
    $rawExponent = $parts[5] ?? '0';
    $negativeExponent = str_starts_with($rawExponent, '-');
    $exponentDigits = ltrim(ltrim($rawExponent, '+-'), '0');
    $exponent = $exponentDigits === '' ? 0 : filter_var(($negativeExponent ? '-' : '').$exponentDigits, FILTER_VALIDATE_INT);
    if ($exponent === false) {
        throw new InvalidArgumentException('Der Exponent ist im vorhandenen Zahlenformat nicht darstellbar.');
    }
    $shift = $tail - strlen($fraction);
    if (($shift > 0 && $exponent > PHP_INT_MAX - $shift)
        || ($shift < 0 && $exponent < PHP_INT_MIN - $shift)) {
        throw new InvalidArgumentException('Der Exponent ist im vorhandenen Zahlenformat nicht darstellbar.');
    }

    return ['negative' => $parts[1] === '-', 'digits' => $digits, 'exponent' => $exponent + $shift];
}

/** Exact integers or shortest fractional roundtrip; locale/precision-INI independent. */
function settings_carry_in_float_text(float $value): string
{
    if (!is_finite($value)) {
        throw new InvalidArgumentException('Der Zahlenwert muss endlich sein.');
    }
    if ($value == 0.0) {
        return '0'; // Signed zero denotes the same numerical balance.
    }
    if (fmod($value, 1.0) === 0.0) {
        return sprintf('%.0F', $value); // A shortest decimal can name a different integer.
    }
    for ($precision = 1; $precision <= 17; $precision++) {
        $text = sprintf('%.'.$precision.'H', $value);
        if ((float)$text === $value) {
            return $text;
        }
    }
    throw new LogicException('A binary64 roundtrip representation is required.');
}

/** Preserve existing raw data; finite floats need full display precision before a later POST. */
function settings_carry_in_display(string|int|float|null $value): string|int|null
{
    return is_float($value) ? (is_finite($value) ? settings_carry_in_float_text($value) : (string)$value) : $value;
}
