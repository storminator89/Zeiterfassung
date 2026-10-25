<?php
require_once __DIR__.'/config.php';
if (!isset($_SESSION['user_id'])) { abort_request(401,'Nicht angemeldet.'); }
if ($_SERVER['REQUEST_METHOD']!=='POST') { abort_request(405,'POST required.'); }
$user=(int)$_SESSION['user_id'];
if (isset($_POST['id'])||isset($_POST['update'])||isset($_POST['delete'])) { abort_request(409,'Änderungen und Stornierungen bitte über einen begründeten Korrekturantrag vornehmen.'); }
if (isset($_POST['action'])) {
    attendance_json(fn()=>attendance_transition($conn,$user,'web:'.$user,$_POST['event_id']??bin2hex(random_bytes(16)),(string)$_POST['action'],($_POST['confirm_end_pause']??'')==='1'));
}
if (isset($_POST['urlaubStart'],$_POST['urlaubEnde'],$_POST['beschreibung'])) {
    attendance_json(fn()=>practice_absence($conn,$user,(string)$_POST['urlaubStart'],(string)$_POST['urlaubEnde'],(string)$_POST['beschreibung']));
}
abort_request(400,'Unbekannte Aktion.');
