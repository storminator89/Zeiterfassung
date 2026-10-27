<?php
// Two coarse CLI-only groups; load only the definitions-only abort helper.
// No security/config bootstrap, HTTP, login/token, session, file DB or server.
function ensure_error_response(bool $condition, string $group): void {
    if (!$condition) { throw new RuntimeException($group); }
}

function isolated_abort_response(?string $optIn, int $status, string $message, string $accept = 'application/json'): string {
    $helper = realpath(__DIR__ . '/../lib/http_error_response.php');
    $script = ($optIn ?? '')
        . '$_SERVER = ["HTTP_ACCEPT" => ' . var_export($accept, true) . ', "HTTP_X_REQUESTED_WITH" => "XMLHttpRequest", "SCRIPT_NAME" => "/api.php"];'
        . '$_GET = ["format" => "json", "CHRONO_API_JSON_ERRORS" => "true"];'
        . 'require ' . var_export($helper, true) . ';'
        . 'http_response_code(200);'
        . 'register_shutdown_function(static function () { fwrite(STDERR, "status:" . http_response_code()); });'
        . 'abort_request(' . $status . ', ' . var_export($message, true) . ');'
        . 'echo "UNREACHABLE";';
    $process = proc_open([PHP_BINARY, '-n', '-d', 'error_reporting=E_ALL', '-d', 'display_errors=stderr', '-r', $script],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    ensure_error_response(is_resource($process), 'subprocess available');
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]);
    $diagnostics = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    ensure_error_response($exit === 0 && $diagnostics === 'status:' . $status, 'unchanged status, clean termination and no PHP warnings');
    return $body;
}

// Default and non-boolean opt-ins remain exact text, despite caller inputs.
$plain = "Nicht angemeldet.\nReload <page> / \"again\".\xff";
foreach ([null, "define('CHRONO_API_JSON_ERRORS', false);", "define('CHRONO_API_JSON_ERRORS', 1);", "define('CHRONO_API_JSON_ERRORS', 'true');"] as $optIn) {
    ensure_error_response(isolated_abort_response($optIn, 403, $plain) === $plain, 'non-API text bytes and strict server-only opt-in');
}

// Representative controlled API statuses all keep the existing error envelope.
foreach ([
    400 => 'Username and password required.',
    401 => 'Invalid or expired token.',
    403 => 'Administrator access required.',
    409 => 'Use an approved correction request to cancel a record.',
    429 => 'Too many login attempts. Try again later.',
    503 => 'Database unavailable.',
] as $status => $message) {
    $body = isolated_abort_response("define('CHRONO_API_JSON_ERRORS', true);", $status, $message, 'text/plain');
    ensure_error_response($body === json_encode(['success' => false, 'message' => $message])
        && json_decode($body, true, 512, JSON_THROW_ON_ERROR) === ['success' => false, 'message' => $message], 'native JSON envelope and status');
}
$encoded = isolated_abort_response("define('CHRONO_API_JSON_ERRORS', true);", 503, "Ungültig \"/\"\n\xff");
ensure_error_response(json_decode($encoded, true, 512, JSON_THROW_ON_ERROR) === ['success' => false, 'message' => "Ungültig \"/\"\n\u{FFFD}"], 'JSON-safe message encoding');

echo json_encode([
    'passed' => true,
    'groups' => ['non-API exact text and request inputs cannot opt in', 'native controlled-error JSON, original status and termination'],
    'runtime' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'http_headers_cors_retry_after_bootstrap_auth_db_browser_deployment_tested' => false,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
