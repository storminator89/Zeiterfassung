<?php

namespace App\Chrono;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Explicit own-CSV presentation range, not identity, accounting or a query scope. */
final readonly class CsvExportPeriod
{
    private function __construct(public string $from, public string $to) {}

    public static function fromQueryString(string $queryString): ?self
    {
        // Inspect only this new contract's raw fields, independent of max_input_vars.
        // parse_str on the whole string could truncate a late period=1 and silently
        // turn a requested partial download into the legacy complete own export.
        $query = [];
        $marked = false;
        $invalid = false;
        $separators = preg_quote(ini_get('arg_separator.input') ?: '&', '/');
        foreach (preg_split('/['.$separators.']/', $queryString) as $pair) {
            [$rawName, $rawValue] = array_pad(explode('=', $pair, 2), 2, '');
            $name = urldecode($rawName);
            $root = explode("\0", $name, 2)[0];
            if (!in_array($root, ['period', 'from', 'to'], true)) {
                if (preg_match('/\A(period|from|to)\[/', $root, $match) === 1) {
                    $marked = $marked || $match[1] === 'period';
                    $invalid = true; // Array-shaped fields are not this scalar contract.
                }
                continue;
            }
            $marked = $marked || $root === 'period';
            $invalid = $invalid || $name !== $root || array_key_exists($root, $query);
            $query[$root] = urldecode($rawValue);
        }
        // Without a marker, old from/to/search/page/subject queries stay ignored.
        if (!$marked) {
            return null;
        }
        if ($invalid || ($query['period'] ?? null) !== '1') {
            throw new InvalidArgumentException('Invalid CSV period.');
        }
        $zone = new DateTimeZone('Europe/Berlin');
        foreach (['from', 'to'] as $field) {
            $raw = $query[$field] ?? null;
            if (!is_string($raw) || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $raw) !== 1
                || str_starts_with($raw, '0000-')) {
                throw new InvalidArgumentException('Invalid CSV period.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $zone);
            if ($date === false || $date->format('Y-m-d') !== $raw) {
                throw new InvalidArgumentException('Invalid CSV period.');
            }
        }
        if ($query['from'] > $query['to']) {
            throw new InvalidArgumentException('Invalid CSV period.');
        }

        return new self($query['from'], $query['to']);
    }

    /** Called only AFTER the unchanged full-own-snapshot conflict/quality gates. */
    public function select(array $validatedOwnRows): array
    {
        $selected = [];
        foreach ($validatedOwnRows as $row) {
            // Same Berlin start-day convention as the current UI; no clipping,
            // day-splitting, recalculation or automatic omission of invalid rows.
            $day = \booking_local_date(\booking_time($row['startzeit']))->format('Y-m-d');
            if ($day >= $this->from && $day <= $this->to) {
                $selected[] = $row;
            }
        }

        return $selected;
    }
}
