<?php

/** Bounded display only after a known review rejection; not permission or command data. */
function correction_rejected_review_draft(array $form): array
{
    if (($form['action'] ?? null) !== 'review'
        || !is_string($form['id'] ?? null)
        || preg_match('/\A[1-9][0-9]*\z/D', $form['id']) !== 1
        || !is_string($form['review_reason'] ?? null)
        || strlen($form['review_reason']) > 4096) {
        return [];
    }
    $id = filter_var($form['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        return [];
    }

    // The caller may display this exact text only in the same still-visible
    // reviewable request's existing form. Never retain/select the decision.
    return ['id' => $id, 'review_reason' => $form['review_reason']];
}
