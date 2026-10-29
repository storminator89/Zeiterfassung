<?php
// Definitions only: same-response display after a known refusal, never a command or source validation.
function holiday_refill_rejected_draft(array $form): array
{
    if (($form['refill_holidays'] ?? null) !== '1'
        || array_diff(array_keys($form), ['refill_holidays','holiday_year','holiday_json','confirm_bw_source','_token','csrf_token']) !== []) {
        return [];
    }
    $draft = [];
    // Preserve bounded original text, including an invalid year, never cast, trim or truncate.
    if (is_string($form['holiday_year'] ?? null)
        && preg_match('/\A[0-9]{1,8}\z/', $form['holiday_year']) === 1) {
        $draft['year'] = $form['holiday_year'];
    }
    if (is_string($form['holiday_json'] ?? null) && strlen($form['holiday_json']) <= 131072
        && preg_match('//u', $form['holiday_json']) === 1
        && preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $form['holiday_json']) !== 1) {
        $draft['json'] = $form['holiday_json'];
    }
    // Never copy confirmation, CSRF fields, credentials or unrelated form data.
    return $draft;
}
