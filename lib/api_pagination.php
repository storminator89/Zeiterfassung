<?php

// Definitions only: no bootstrap, IO, connection, authentication or state changes.
const CHRONO_API_DEFAULT_LIMIT = 100;
const CHRONO_API_MAX_LIMIT = 1000;
const CHRONO_API_MAX_OFFSET = 2147483647;

/** Validate before casting: no numeric coercion, truncation or integer overflow. */
function api_pagination_integer(mixed $value, string $field, int $maximum): int
{
    $message = 'Invalid '.$field.'. Use an integer from 0 to '.$maximum.'.';
    if (is_int($value)) {
        if ($value < 0 || $value > $maximum) {
            throw new InvalidArgumentException($message);
        }
        return $value;
    }
    if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
        throw new InvalidArgumentException($message);
    }
    $digits = ltrim($value, '0');
    $digits = $digits === '' ? '0' : $digits;
    $bound = (string) $maximum;
    if (strlen($digits) > strlen($bound)
        || (strlen($digits) === strlen($bound) && strcmp($digits, $bound) > 0)) {
        throw new InvalidArgumentException($message);
    }
    return (int) $digits;
}

/** Omitted offset skips zero; null metadata still distinguishes omission. */
function api_pagination(array $query): array
{
    return [
        'limit' => array_key_exists('limit', $query)
            ? api_pagination_integer($query['limit'], 'limit', CHRONO_API_MAX_LIMIT)
            : CHRONO_API_DEFAULT_LIMIT,
        'offset' => array_key_exists('offset', $query)
            ? api_pagination_integer($query['offset'], 'offset', CHRONO_API_MAX_OFFSET)
            : null,
    ];
}
