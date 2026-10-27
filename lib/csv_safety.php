<?php

// Exact pure body extracted from the native csv_safe wrapper; no bootstrap.
function csv_safe_text($value): string {
    $value = (string)($value ?? '');
    return preg_match('/^[\s]*[=+@\-]|^[\t\r\n]/u', $value) ? "'" . $value : $value;
}
