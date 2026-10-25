<?php
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
