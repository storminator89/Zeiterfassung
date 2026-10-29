# Native Admin-Details: exakte IDs und gebundene Antworten

Separater UI-/JSON-Folgefehler nach serverseitigem #31 auf Main
`4805305adf2df85cd61521fb695674ef3cd30bfa`, Baum
`d9c8235a3ed2d8dcdbe2456db7b24c5d206f723e`.

Die bisherige numerische `editUser(...)`-Ausgabe und JSON-Zahlen verlieren in
JavaScript oberhalb 2^53 Präzision. Dadurch konnte ein benachbarter Datensatz oder
eine benachbarte Zuordnung vorausgewählt werden. Der Aufruf übergibt jetzt einen
HTML-escaped JSON-String. Der unverändert gebundene private Admin-SELECT ergänzt
nur `id_string`, `department_id_string`, `supervisor_id_string` durch CAST AS TEXT
in derselben Zeile; alle ursprünglichen Rohfelder und Typen, Passwortentfernung,
Autorisierung und HTTP-Fehler bleiben erhalten. Kein neuer öffentlicher APIvertrag.

Die vier reinen ID-Helfer stammen bytegleich aus der bestehenden geprüften
Laravel-Vorarbeit. Exakte positive Ziel-ID bis SQLite-Maximum muss zur Anfrage
passen. Alle drei Identitäten werden vor Befüllung geprüft. Sichere alte Zahlen
bleiben kompatibel, unsichere Zahlen ohne bestätigende Textmetadaten sperren.
NULL und gespeicherte alte 0-/negative/Stringreferenzen werden nicht durch einen
Nachbarn ersetzt oder als gültig attestiert. Fehlende Optionen werden mit
textContent und unverändertem Wert ergänzt; die bestehende serverseitige #31-
Validierung bleibt maßgeblich. Keine automatische Reparatur oder Normalisierung.

Während des Ladens ist das Formular leer; sichtbare Editfelder einschließlich
Passwort und Speichern sind gesperrt. Erst nach bestätigten IDs und vollständiger
Befüllung werden diese Felder aktiviert; Fehler/Cancel sperren sie wieder.
Readonly Tages-Soll und Hidden-CSRF-/Actionfelder bleiben unverändert. Beim
eigentlichen Submit bleibt das Passwort aktiv und unverändert serialisierbar. Nur die neueste
Antwort für das aktuelle offene Dialogziel darf befüllen. Cancel/Close machen
Antworten ungültig; ein verspätetes altes Close darf einen neu geöffneten Dialog
nicht leeren. Benutzerwechsel und Cancel leeren das Passwort. Submit ist nur mit
bestätigter unveränderter Ziel-ID erlaubt. Der bestehende POST/CSRF-/Passwort-/
Writer-/SQL-/Auditpfad bleibt unverändert; kein Retry, keine Erfolgs- oder
Commitverlustgarantie, keine neuen Assets, Quellen oder Runtimefreigaben.

## Begrenzter Nachweis

Isolierte erfundene SQLite-Memoryzeilen → tatsächlicher Endpoint-Read/Passwort-
Unset und PHP-JSON → tatsächlicher Detail-JS mit synthetischem DOM/Transport:
große benachbarte IDs, SQLite-Maximum, NULL, 0/-1, defekte Metadaten, sichere alte
Antworten, Race, Cancel, spätes Close, Passwortleeren und Zielbindung. Query-only
Read und ursprüngliche Rohwerte/-typen bleiben gleich. PHP-/JS-Syntax, bytegleiche
Helfer, vollständige Dateiinverse und fremde Blobs/Modi werden separat belegt.

Keine echten Konten, Schlüssel oder Credentials, kein Bootstrap, Server, Browser,
Login, HTTP-/CSRF-/Writer-/Mutations-/V5-/Produktivnachweis. Keine neue Testsuite
oder CI-Auslösung. #79 und echte UI-/Betriebsabnahme sind damit nicht geschlossen.
