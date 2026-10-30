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

/** Optional own-only intersection; neither a subject selector nor a permission. */
function correction_queue_own_filter(array $query): array
{
    if (!array_key_exists('own_requests', $query) || $query['own_requests'] === '') {
        return ['enabled' => false, 'invalid' => false];
    }

    return $query['own_requests'] === '1'
        ? ['enabled' => true, 'invalid' => false]
        : ['enabled' => false, 'invalid' => true];
}
