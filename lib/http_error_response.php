<?php
// Definitions only: no configuration, session, storage or authentication work.
/** Best-effort correlation of this controlled 5xx only, never caller-supplied data. */
function chrono_native_error_correlation(int $status): void {
    if ($status < 500 || $status > 599 || PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') { return; }
    try {
        if (headers_sent()) { return; }
        $id = bin2hex(random_bytes(16));
        header('X-Request-ID: '.$id);
        @error_log('Native controlled response failed. request_id='.$id.' status='.$status);
    } catch (Throwable) {
        // Entropy/header/log transport failure must not replace the original response.
    }
}

function abort_request(int $status, string $message): never {
    http_response_code($status);
    chrono_native_error_correlation($status);
    if (defined('CHRONO_API_JSON_ERRORS') && CHRONO_API_JSON_ERRORS === true) {
        header('Content-Type: application/json; charset=UTF-8');
        exit(json_encode(['success' => false, 'message' => $message], JSON_INVALID_UTF8_SUBSTITUTE));
    }
    header('Content-Type: text/plain; charset=UTF-8');
    exit($message);
}
