<?php

function valid_language($value): string {
    return is_string($value) && in_array($value, ['de', 'en', 'zh'], true) ? $value : 'de';
}

// Browser detection is only an initial preference; never replace a stored choice.
function initial_language($storedLanguage, $acceptLanguage): string {
    if ($storedLanguage !== null) {
        return valid_language($storedLanguage);
    }

    $browserLanguage = is_string($acceptLanguage) ? strtolower(substr($acceptLanguage, 0, 2)) : '';
    return in_array($browserLanguage, ['de', 'en'], true) ? $browserLanguage : 'de';
}
