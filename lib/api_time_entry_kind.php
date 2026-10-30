<?php
// Definitions only: no bootstrap, identity, database reads, output or writes.
require_once __DIR__.'/booking.php';

/** Exact raw opt-in, independent of normalized bags and max_input_vars. */
function api_time_entry_kind_query(string $queryString): ?string
{
    $seen = false;$invalid = false;$value = null;
    $separators = preg_quote(ini_get('arg_separator.input') ?: '&', '/');
    foreach (preg_split('/['.$separators.']/', $queryString) as $pair) {
        [$rawName, $rawValue] = array_pad(explode('=', $pair, 2), 2, '');
        $name = urldecode($rawName);
        $root = explode("\0", $name, 2)[0];
        if ($root === 'entry_kind') {
            $invalid = $invalid || $seen || $name !== $root;
            $seen = true;$value = urldecode($rawValue);
        } elseif (preg_match('/\Aentry_kind\[/', $root) === 1) {
            $seen = true;$invalid = true;
        }
    }
    if (! $seen) { return null; }
    if ($invalid || ! in_array($value, ['work', 'absence'], true)) {
        throw new InvalidArgumentException('Invalid entry_kind. Use work or absence.');
    }

    return $value;
}

/** Use the existing classifier, not another absence/approval/validity rule. */
function api_time_entry_is_absence(mixed $kind, mixed $description): int
{
    return booking_is_absence(['absence_kind' => $kind, 'beschreibung' => $description]) ? 1 : 0;
}

/** Fixed SQL only, inside the caller's existing authenticated own read. */
function api_time_entry_kind_predicate(PDO $db, string $kind): string
{
    if (! in_array($kind, ['work', 'absence'], true)) {
        throw new InvalidArgumentException('Invalid entry_kind. Use work or absence.');
    }
    if (! $db->createFunction('chrono_api_is_absence', 'api_time_entry_is_absence', 2)) {
        throw new RuntimeException('Could not register API entry-kind filtering.');
    }

    return 'chrono_api_is_absence(absence_kind, beschreibung) = '.($kind === 'absence' ? '1' : '0');
}
