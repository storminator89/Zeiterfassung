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

// One additional coarse journey uses the exact API entry/exit boundary, replacing
// only its application region with invented failures/data. Never load config.
function isolated_api_boundary(string $application, int $status, string $expected, string $method = 'GET', int $levels = 1): void {
    $source = file_get_contents(__DIR__ . '/../api.php');
    $begin = '// BEGIN native API application (kept at global scope).';
    $end = '// END native API application.';
    ensure_error_response(substr_count($source, $begin) === 1 && substr_count($source, $end) === 1, 'one bounded application region');
    [$prefix, $applicationAndSuffix] = explode($begin, $source, 2);
    [, $suffix] = explode($end, $applicationAndSuffix, 2);
    $prefix = substr($prefix, strlen("<?php\n"));
    $suffix = substr($suffix, 0, strrpos($suffix, '?>'));
    $script = '$_SERVER = ["REQUEST_METHOD" => ' . var_export($method, true) . '];'
        . 'ob_start(); echo "outer:"; http_response_code(200);'
        . 'register_shutdown_function(static function () { fwrite(STDERR, "status:" . http_response_code() . ";levels:" . ob_get_level()); });'
        . $prefix . $application . $suffix;
    $process = proc_open([PHP_BINARY, '-n', '-d', 'error_reporting=E_ALL', '-d', 'display_errors=1', '-r', $script],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    ensure_error_response(is_resource($process), 'boundary subprocess available');
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]);
    $diagnostics = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    ensure_error_response($exit === 0 && $body === 'outer:' . $expected
        && $diagnostics === 'status:' . $status . ';levels:' . $levels,
        'owned boundary preserves outer buffer, status, exact body and clean termination');
}

$failure = '{"success":false,"message":"Internal server error"}';
isolated_api_boundary('echo "PRIVATE-PARTIAL"; throw new Error("PRIVATE-BOOTSTRAP-DETAIL");', 500, $failure);
isolated_api_boundary('preg_match("/date/", ["invented"]);', 500, $failure);
$helper = realpath(__DIR__ . '/../lib/http_error_response.php');
isolated_api_boundary('require ' . var_export($helper, true) . '; abort_request(429, "Too many login attempts. Try again later."); echo "UNREACHABLE";',
    429, '{"success":false,"message":"Too many login attempts. Try again later."}', 'GET', 2);
isolated_api_boundary('echo "{\"success\":true,\"data\":[]}";', 200, '{"success":true,"data":[]}');
isolated_api_boundary('throw new Error("MUST-NOT-BOOTSTRAP");', 200, '', 'OPTIONS');

// Exercise both exact production success encoders and their existing Exception
// catch bodies with invented unencodable rows, without executing their queries.
$apiSource = file_get_contents(__DIR__ . '/../api.php');
foreach ([
    ["        echo json_encode(['success' => true, 'data' => \$users]", '$users = [["username" => "bad\\xff"]];'],
    ["        echo json_encode([\n            'success' => true,\n            'data' => \$entries", '$entries = [["beschreibung" => "bad\\xff"]]; $totalCount = 1; $pagination = ["limit" => 100, "offset" => null];'],
] as [$encoderStart, $inventedRows]) {
    $start = strpos($apiSource, $encoderStart);
    $finish = strpos($apiSource, "\n}\n", $start);
    ensure_error_response($start !== false && $finish !== false, 'exact existing encoder/catch region');
    $encoderAndCatch = substr($apiSource, $start, $finish - $start);
    isolated_api_boundary($inventedRows . 'try {' . $encoderAndCatch, 500, $failure);
}

// One terminal-only bootstrap-free group: preserve the original terminal inner
// transport, never read a request body, open a database or invoke signed rules.
function isolated_terminal_boundary(string $application, int $status, string $expected, int $levels): void {
    $source = file_get_contents(__DIR__ . '/../terminal_api.php');
    $begin = '// BEGIN native terminal application (kept at global scope).';
    $end = '// END native terminal application.';
    ensure_error_response(substr_count($source, $begin) === 1 && substr_count($source, $end) === 1, 'one terminal application region');
    [$prefix, $applicationAndSuffix] = explode($begin, $source, 2);
    [, $suffix] = explode($end, $applicationAndSuffix, 2);
    $prefix = substr($prefix, strlen("<?php\n"));
    $script = '$_SERVER = ["REQUEST_METHOD" => "OPTIONS"];'
        . 'ob_start(); echo "outer:"; http_response_code(200);'
        . 'register_shutdown_function(static function () { fwrite(STDERR, "status:" . http_response_code() . ";levels:" . ob_get_level()); });'
        . $prefix . $application . $suffix;
    $process = proc_open([PHP_BINARY, '-n', '-d', 'error_reporting=E_ALL', '-d', 'display_errors=1', '-r', $script],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    ensure_error_response(is_resource($process), 'terminal boundary subprocess available');
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]);
    $diagnostics = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    ensure_error_response($exit === 0 && $body === 'outer:' . $expected
        && $diagnostics === 'status:' . $status . ';levels:' . $levels,
        'terminal boundary preserves outer buffer, exact status/body and inner exit without OPTIONS shortcut');
}

$terminalUnconfirmed = json_encode(['success'=>false,'message'=>'Buchung nicht bestätigt. Verbindung prüfen und denselben Vorgang erneut versuchen.']);
isolated_terminal_boundary('echo "PRIVATE-PARTIAL"; throw new Error("PRIVATE-BOOTSTRAP-DETAIL");', 503, $terminalUnconfirmed, 1);
isolated_terminal_boundary('require ' . var_export($helper, true) . '; abort_request(503, "Database unavailable."); echo "UNREACHABLE";',
    503, '{"success":false,"message":"Database unavailable."}', 2);
// Load only these two exact definitions as source, not attendance/bootstrap files.
$attendanceSource = file_get_contents(__DIR__ . '/../lib/attendance.php');
$classStart = strpos($attendanceSource, 'class AttendanceError extends RuntimeException {');
$classEnd = strpos($attendanceSource, 'function att_one(', $classStart);
$jsonStart = strpos($attendanceSource, 'function attendance_json(callable $action): never {');
ensure_error_response($classStart !== false && $classEnd !== false && $jsonStart !== false, 'exact terminal inner transport definitions');
$innerTransport = substr($attendanceSource, $classStart, $classEnd - $classStart) . substr($attendanceSource, $jsonStart);
isolated_terminal_boundary($innerTransport . 'attendance_json(static function () { throw new AttendanceError("POST erforderlich.", 405); }); echo "UNREACHABLE";',
    405, '{"success":false,"message":"POST erforderlich."}', 2);
isolated_terminal_boundary($innerTransport . 'attendance_json(static fn() => ["success" => true, "time" => "invented"]); echo "UNREACHABLE";',
    200, '{"success":true,"time":"invented"}', 2);

echo json_encode([
    'passed' => true,
    'groups' => ['non-API exact text and request inputs cannot opt in', 'native controlled-error JSON, original status and termination', 'entrypoint-only unexpected-failure and encoding boundary; no bootstrap', 'terminal-only bootstrap503, inner transport and unchanged OPTIONS path; no signed rules'],
    'runtime' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'http_headers_cors_retry_after_bootstrap_auth_db_browser_deployment_tested' => false,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
