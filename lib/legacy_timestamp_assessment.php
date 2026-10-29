<?php
// Definitions only: exact diagnostic extracted from LegacyTimeQuality; no chosen instant.
function legacy_timestamp_assessment(mixed $value): array
{
    $result = ['category' => 'unsupported_timestamp_shape', 'offset_free' => false];
    if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})(Z|[+-]\d{2}:\d{2})?$/D', $value, $parts)) {
        return $result;
    }
    $result['offset_free'] = ! isset($parts[7]) || $parts[7] === '';
    if (! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
        || (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 59
        || (! $result['offset_free'] && $parts[7] !== 'Z' && ((int) substr($parts[7], 1, 2) > 23 || (int) substr($parts[7], 4, 2) > 59))) {
        $result['category'] = 'invalid_timestamp';

        return $result;
    }
    $result['category'] = null;
    if (! $result['offset_free']) {
        // Explicit numeric offsets/Z already identify an instant. Do not
        // require them to equal Berlin's contemporary offset.
        return $result;
    }

    $wallText = substr($value, 0, 10).' '.substr($value, 11, 8);
    $wall = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $wallText, new DateTimeZone('UTC'));
    if ($wall === false) {
        $result['category'] = 'invalid_timestamp';

        return $result;
    }
    $zone = new DateTimeZone('Europe/Berlin');
    $epoch = $wall->getTimestamp();
    $offsets = array_unique(array_column($zone->getTransitions($epoch - 172800, $epoch + 172800), 'offset'));
    $candidates = 0;
    foreach ($offsets as $offset) {
        if ((new DateTimeImmutable('@'.($epoch - $offset)))->setTimezone($zone)->format('Y-m-d H:i:s') === $wallText) {
            $candidates++;
        }
    }
    $result['category'] = match (true) {
        $candidates === 0 => 'nonexistent_berlin_local_time',
        $candidates > 1 => 'ambiguous_berlin_local_time',
        default => null,
    };

    return $result;
}
