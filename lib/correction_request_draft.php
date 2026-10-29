<?php

/** Bounded display-only fields after a known request rejection; never command or storage data. */
function correction_rejected_request_draft(array $form): array
{
    if (($form['action'] ?? null) !== 'request') {
        return [];
    }
    $draft = [];
    foreach (['start' => 128, 'end' => 128, 'pause' => 128, 'reason' => 4096] as $field => $limit) {
        // Keep exact strings, especially explicit offsets. Do not coerce,
        // trim or silently truncate malformed/oversized submitted values.
        if (is_string($form[$field] ?? null) && strlen($form[$field]) <= $limit) {
            $draft[$field] = $form[$field];
        }
    }
    $draft['cancel'] = ($form['cancel'] ?? null) === '1';

    return $draft;
}
