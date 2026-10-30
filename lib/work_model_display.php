<?php

// Presentation only: no model selection, accounting, connection or writer.
/** @return list<?int> Seven Monday-to-Sunday cells; unavailable is never zero. */
function work_model_display_minutes(mixed $json): array
{
    $unknown = array_fill(0, 7, null);
    if (!is_string($json) || strlen($json) > 65536) {
        return $unknown;
    }
    // Match source lexemes, not decoded floats or coerced scalar values. The
    // native writers use whole numbers; the initial trigger may emit 480.0.
    // Keep the existing own-API representation grammar, without changing it.
    $space = '[ \\t\\r\\n]*';
    $token = '((?:0|[1-9][0-9]*)(?:\\.0+)?)';
    $pattern = '~\\A'.$space.'\\['.$space.$token.str_repeat($space.','.$space.$token, 6).$space.'\\]'.$space.'\\z~';
    if (preg_match($pattern, $json, $lexemes) !== 1) {
        return $unknown;
    }
    $minutes = [];
    foreach (array_slice($lexemes, 1) as $lexeme) {
        $whole = explode('.', $lexeme, 2)[0];
        if (strlen($whole) > 4 || (strlen($whole) === 4 && strcmp($whole, '1440') > 0)) {
            return $unknown;
        }
        $minutes[] = (int) $whole;
    }

    return $minutes;
}
