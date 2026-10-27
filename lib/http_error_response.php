<?php
// Definitions only: no configuration, session, storage or authentication work.
function abort_request(int $status, string $message): never {
    http_response_code($status);
    if (defined('CHRONO_API_JSON_ERRORS') && CHRONO_API_JSON_ERRORS === true) {
        header('Content-Type: application/json; charset=UTF-8');
        exit(json_encode(['success' => false, 'message' => $message], JSON_INVALID_UTF8_SUBSTITUTE));
    }
    header('Content-Type: text/plain; charset=UTF-8');
    exit($message);
}
