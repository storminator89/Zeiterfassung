<?php
// Server-owned terminal transport policy, never selected by request input.
define('CHRONO_API_JSON_ERRORS', true);
$chronoTerminalOutputLevel = ob_get_level();
try {
    ini_set('display_errors', '0');
    if (!ob_start()) { throw new RuntimeException('Terminal response buffer unavailable.'); }

// BEGIN native terminal application (kept at global scope).
require_once __DIR__.'/config.php';
require_once __DIR__.'/lib/terminal.php';
attendance_json(function() use ($conn) {
    attendance_require_enabled($conn);
    if ($_SERVER['REQUEST_METHOD']!=='POST') { throw new AttendanceError('POST erforderlich.',405); }
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>8192) { throw new AttendanceError('Anfrage zu groß.',413); }
    $body=file_get_contents('php://input',false,null,0,8193);
    if (strlen($body)>8192) { throw new AttendanceError('Anfrage zu groß.',413); }
    $input=json_decode($body,true);
    if (!is_array($input)) { throw new AttendanceError('JSON erforderlich.',400); }
    return terminal_handle($conn,$input,$body);
});
// END native terminal application.

    ob_end_flush();
} catch (Throwable $error) {
    // Keep failure unconfirmed; never expose or newly log request/secret details.
    while (ob_get_level() > $chronoTerminalOutputLevel) { ob_end_clean(); }
    http_response_code(503);
    if (function_exists('chrono_native_error_correlation')) { chrono_native_error_correlation(503); }
    header('Content-Type: application/json; charset=UTF-8');
    exit(json_encode(['success'=>false,'message'=>'Buchung nicht bestätigt. Verbindung prüfen und denselben Vorgang erneut versuchen.']));
}
