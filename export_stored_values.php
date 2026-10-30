<?php
require_once __DIR__.'/security.php';
require_once __DIR__.'/config.php';
require_once __DIR__.'/lib/own_stored_values_export.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD']??'')!=='GET') {
    http_response_code(405);header('Allow: GET');header('Content-Type: text/plain; charset=UTF-8');echo 'Nur GET ist verfügbar.';exit;
}
if ((string)($_SERVER['QUERY_STRING']??'')!=='') {
    http_response_code(400);header('Content-Type: text/plain; charset=UTF-8');echo 'Dieser eigene Rohwertnachweis unterstützt keine Filter oder Zielauswahl.';exit;
}
try {
    $result=chrono_native_own_stored_values_export($conn,(int)($_SESSION['user_id']??0),$_SESSION['auth_version']??null);
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$result['filename'].'"');echo $result['content'];
} catch (AttendanceError $error) {
    http_response_code(in_array($error->status,[401,403],true)?$error->status:503);header('Content-Type: text/plain; charset=UTF-8');echo 'Eigene gespeicherte Rohwerte nicht verfügbar. Bitte den Anmeldestand prüfen.';
} catch (LengthException) {
    http_response_code(413);header('Content-Type: text/plain; charset=UTF-8');echo 'Der eigene Rohwertnachweis überschreitet eine feste Grenze (5000 Einträge, 64 KiB pro Feld, 8 MiB Dokument). Es wurde kein Teildownload erstellt. Für größere Bestände die Verwaltung um einen gesondert autorisierten Offline-Nachweis bitten.';
} catch (Throwable) {
    http_response_code(503);header('Content-Type: text/plain; charset=UTF-8');echo 'Eigene gespeicherte Rohwerte konnten nicht vollständig bereitgestellt werden. Es wurde kein Teildownload erstellt.';
}
