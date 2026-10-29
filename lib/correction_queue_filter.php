<?php

// Definitions only. Query selection never authorizes or changes a command.
require_once __DIR__.'/correction_selection.php';

/** Optional exact request ID in the existing scoped queue; invalid never widens. */
function correction_queue_request_filter(array $query): array
{
    if (!array_key_exists('request_id', $query) || $query['request_id'] === '') {
        return ['id' => null, 'value' => '', 'invalid' => false];
    }
    try {
        if (!is_string($query['request_id'])) {
            throw new InvalidArgumentException();
        }
        $id = correction_selection_id($query['request_id']);
        return ['id' => $id, 'value' => (string)$id, 'invalid' => false];
    } catch (InvalidArgumentException) {
        return ['id' => null, 'value' => '', 'invalid' => true];
    }
}
