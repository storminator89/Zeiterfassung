# Historien-Löschsperre in der nativen Adminliste — Teilumfang #31

Die native Benutzerliste zeigt bei vorhandener Kontohistorie nun einen sichtbaren
Hinweis statt des nicht ausführbaren Löschformulars. „Bearbeiten“ bleibt
verfügbar. DE, EN und traditionelles Chinesisch erhalten denselben Hinweis.

Eine korrelierte `EXISTS`-Spalte der bisherigen Listenabfrage prüft dieselbe
Bedingung wie der bestehende POST-Schutz: mindestens ein `audit_log`-Eintrag mit
derselben `user_id`, unabhängig von Entität oder Operation. Auch Kontobaselines
und reine Kontohistorie zählen; eine Zeiterfassung ist nicht erforderlich.
Regulär migrierte oder neu angelegte Konten können daher sämtlich gesperrt sein.
Ausgegeben wird nur die feste, escapete Erklärung, keine Historieninhalte,
Anzahl, Gründe oder Zeitpunkte.

Die Anzeige ist eine Momentaufnahme, keine zusätzliche Berechtigungsregel.
Das Backend bleibt maßgeblich und prüft beim POST weiterhin unter demselben
Writer-Lock. Bei später hinzugekommener Historie oder einem manuell gesendeten
POST greift weiterhin die bisherige Sperre. Fehlende Historie in der Anzeige
verspricht keine Löschberechtigung: insbesondere der letzte Administrator bleibt
zusätzlich geschützt. Keine neue Kontosperr-/Zugangsentzugsfunktion und kein
Versprechen, dass eine Passwortänderung bei jeder Authentifizierungsart genügt.

Aktuelle Adminprüfung, CSRF, POST-Handler, Last-Admin-Regel, Transaktionen,
Schema, Audit, Rollen, Benutzer-/Teamscope und bestehende Bearbeitungsfelder
bleiben unverändert. Suche, Sortierung, zehn Benutzer pro Seite und
Gesamtzählung bleiben dieselben. Kein neuer Endpunkt, Export oder globaler
Historienpayload. Der vorhandene `users.*`-Read wird nicht um eine neue
personenbezogene Quelle erweitert; hinzu kommt nur ein 0/1-Existenzwert.

## Lokale Nachweise und Grenzen

Kandidat auf `70437d8a` (lokaler Snapshotbaum `a1b9d85999ed8fcfb71d66afa9747709dd26b8dc`): PHP 8.5.12-Syntax,
`git diff --check`, 15 grobe Prüfungen der tatsächlichen SQL-Abfrage in einer
anonymisierten In-Memory-Datenbank sowie des isolierten Markupfragments und
Quellinvarianten. Abgedeckt sind fehlende Historie, verschiedene Audit-Entitäten,
mehrere Historienzeilen ohne doppelte Benutzer, fremde/0-Eigentümer, Suche,
zweite Seite und wiederholte Darstellung. Separates statisches Review ohne
Blocker.

Keine echte Datenbank, Konten, Keys, Appinitialisierung, HTTP, Anmeldung,
Session-/CSRF-Ausführung, Browser-, Konkurrenz- oder Lastabnahme. Kein lokaler
Appstart, keine Remoteaktion, neue CI oder Veröffentlichung. #31 wird durch
diesen kleinen Anzeigeslice nicht insgesamt geschlossen.

## Reankerung auf aktuellen Main

Der bereits vorbereitete Kandidat wurde ohne Code-Neubau auf den lokal gegen
den vollständigen Remote-Baum verifizierten Main `70437d8a` übernommen.
Alle ursprünglichen Codeänderungen und bisherigen Main-Inhalte sind erhalten;
Syntax, Diffcheck und die unveränderten groben anonymisierten Nachweise wurden
auf dieser Basis erneut ausgeführt. Keine Remoteaktion oder neue CI.
Der offene Merge-Denial von PR #239 wird nicht erneut versucht oder umgangen.
