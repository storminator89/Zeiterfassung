<?php

/** Bounded same-response display text after a known withdrawal refusal, never a command. */
function correction_rejected_withdrawal_draft(array $form): array
{
    if (($form['action'] ?? null) !== 'withdraw'
        || !is_string($form['id'] ?? null)
        || preg_match('/\A[1-9][0-9]*\z/D', $form['id']) !== 1
        || !is_string($form['withdraw_reason'] ?? null)
        || strlen($form['withdraw_reason']) > 4096) {
        return [];
    }
    $id = filter_var($form['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        return [];
    }

    // The caller must still match this marker to its fresh visible own pending
    // request. Preserve exact text; do not trim, coerce, truncate or replay it.
    return ['id' => $id, 'withdraw_reason' => $form['withdraw_reason']];
}
