<?php
require_once __DIR__.'/config.php';
if (!isset($_SESSION['user_id'])) { abort_request(401,'Nicht angemeldet.'); }
abort_request(409,'Stornierungen bitte über einen begründeten Korrekturantrag vornehmen.');
