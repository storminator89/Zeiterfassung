<?php
require_once __DIR__.'/config.php';
require_admin();
abort_request(409,'Eine Teilwiederherstellung würde die Änderungshistorie zerstören. Vollständiges privates Datenverzeichnis offline wiederherstellen; siehe docs/TERMINAL.md.');
