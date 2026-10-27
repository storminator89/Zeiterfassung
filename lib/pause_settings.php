<?php

/** Validate the complete paired form payload before any stored rule is changed. */
function validatePauseSettings($hoursThresholds, $minimumPauses): array
{
    // An empty submission is not a request to clear all rules: the legacy
    // bootstrap would recreate defaults on the next request anyway.
    if (!is_array($hoursThresholds) || !is_array($minimumPauses)
        || !array_is_list($hoursThresholds) || !array_is_list($minimumPauses)
        || count($hoursThresholds) === 0 || count($hoursThresholds) !== count($minimumPauses)) {
        throw new InvalidArgumentException('Bitte mindestens eine vollständige Pausenregel angeben.');
    }

    $integer = static function ($value): int {
        if ((!is_string($value) && !is_int($value))
            || !preg_match('/^\+?[0-9]+(?:\.0+)?$/D', (string)$value)) {
            throw new InvalidArgumentException('Stundengrenzen und Mindestpausen müssen nichtnegative ganze Zahlen sein.');
        }
        // Normalize leading zeroes and integer decimal notation without float
        // rounding, then reject values outside the supported integer range.
        $value = explode('.', ltrim((string)$value, '+'))[0];
        $value = ltrim($value, '0');
        $value = filter_var($value === '' ? '0' : $value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($value === false) {
            throw new InvalidArgumentException('Stundengrenze oder Mindestpause ist zu groß.');
        }
        return $value;
    };

    $rules = [];
    $seenThresholds = [];
    foreach ($hoursThresholds as $index => $hoursThreshold) {
        $hoursThreshold = $integer($hoursThreshold);
        $minimumPause = $integer($minimumPauses[$index]);
        if (isset($seenThresholds[$hoursThreshold])) {
            throw new InvalidArgumentException('Jede Stundengrenze darf nur einmal vorkommen.');
        }
        $seenThresholds[$hoursThreshold] = true;
        $rules[] = [$hoursThreshold, $minimumPause];
    }
    return $rules;
}
