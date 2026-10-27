# FW-07 / #78 – gemeinsamer Korrekturkern

**Begrenzte mechanische Extraktion, kein abgeschlossener Korrektur-Laraveldienst
und kein Cutover.** `lib/corrections.php` kapselt den bisherigen Schreibblock aus
`adjustments.php` als `correction_apply_locked(PDO, user, role, command): void`.
Die native Seite verwendet genau diesen Kern; ein späterer Laraveladapter kann
ihn im vorhandenen [#75-Writer](sqlite-write-proof.md) wiederverwenden, statt
Freigaberegeln oder SQL erneut zu implementieren. Heute gibt es dafür bewusst
keine Containerbindung, Route, Controller-, Guard- oder Writeraktivierung.

## Erhaltener Bestand

- `correction_time()` ist unverändert verschoben. Der gesamte request-/review-
  Block ist bis auf Einrückung und die lokalen Namen `$_POST` → `$command`,
  `$conn` → `$db` identisch. Keine neue Normalisierung, Uhr, Zufalls-ID oder
  neue Fehler-/Antwortform; erfolgreicher Fachaufruf hat weiterhin keinen
  eigenen Rückgabewert.
- Die Seite behält exakt den bisherigen `BEGIN IMMEDIATE` außerhalb des `try`,
  `COMMIT`, `ROLLBACK`, Redirect und das deutsche Fehlermapping. Listen,
  Formulare, CSRF und das komplette Markup bleiben unverändert. Es gibt
  keine zweite Transaktion, automatische Wiederholung oder Auditablage.
- Bestehende Eigentümer-/Kontoprüfung, direkte `supervisor_id`-Freigabe,
  Adminfreigabe und Ausschluss der Eigenfreigabe bleiben erhalten. Nur
  `pending` kann entschieden werden; die bestehenden DB-Trigger bleiben
  maßgeblich für endgültige Entscheidungen und append-only Originalhistorie.
- Der gespeicherte Originalsnapshot und dessen exakter `json_encode`-Vergleich,
  laufende Sitzung, Serverzeit und bestehende Buchungs-/Abwesenheitsprüfung
  einschließlich halboffener Überlappungen bleiben unverändert. Freigabe kann
  bestehende Projektionen ändern, fehlende anlegen oder Projektionen stornieren;
  Originalintervalle, Antragssnapshot und Audit bleiben erhalten wie zuvor.
  #35/#36 und weitere Fachwünsche werden hier nicht still korrigiert.

## Vertrauens- und Transaktionsgrenze

Dies ist ein **interner locked core**, keine öffentlich aufrufbare
Berechtigungsgrenze. Er liest keine Session, HTTPdaten, Header oder Dateien und
öffnet keine Verbindung. Der vertrauenswürdige Aufrufer muss den aktuell
authentifizierten Benutzer und seine serverseitige Rolle separat liefern sowie
auf genau diesem PDO eine aktive **IMMEDIATE**-Transaktion besitzen. Ohne aktive
PDO-Transaktion verweigert der neue Helper vor jeder Fachoperation. Der Guard
prüft nur das Vorhandensein der Transaktion, nicht ihren SQLite-Lockmodus;
IMMEDIATE bleibt Pflicht des Aufrufers.

`command` enthält ausschließlich die bisherigen Formfachfelder. Mitgesendete
`user_id`, `role`, `source` oder Auditfelder werden nicht ausgewertet. In der
nativen Seite kommen Identität/Rolle weiterhin aus der von `config.php` vor dem
Handler frisch geprüften Serversession. Es wird **keine stärkere atomare
Auth-/Rollenauffrischung unter Lock** behauptet oder zusätzlich eingeführt.
Die tatsächliche direkte Teamzuordnung und der Antrag werden wie bisher im
selben Writer-Lock gelesen.

Die vorhandenen Audit-UDFs und `$GLOBALS['audit_reason']` bleiben bewusst
unverändert: `Korrekturantrag: {Grund}` bzw. `Korrektur #{id}: {Entscheidungsgrund}`.
Der Akteur wird nicht aus dem Command übernommen. Ein späterer Laravelaufruf
muss zuerst den zugelassenen frischen #74/#75-Writer, #76-Guard/Middleware und
passenden scoped `AuditContext` bereitstellen; die Framework-UDFs lesen nicht
automatisch diese nativen Globals. Dieser Schritt implementiert oder umgeht
diese notwendige spätere Adapterarbeit nicht. Keine produktive PDO-Factory,
Schema-/Host-/Schlüsseländerung oder neue Berechtigung.

## Grobe Abnahme

```sh
php framework/tools/rehearse-corrections.php
```

Ein parameterloser CLI-Lebenszyklus baut ausschließlich einen neuen anonymen
SQLite-`:memory:`-Bestand über den vorhandenen V2-Fixturebuilder und die echten
Migrationen auf. Keine Appkonfiguration, reale Datei/DB, Zugangsdaten, HTTPserver,
Port 18083, gemeinsame Fixture, lokale Browserfunktionstests oder neuen CI-Läufe.
Geprüft: eigener Antrag und fremde direkte Teamfreigabe; Eigen-/Außenteamablehnung
und finale Entscheidung; fremde Ablehnung ohne Projektionsänderung; Nachtrag
und Storno mit erhaltenem Originalintervall; erzwungener letzter Schreibfehler
rollt Projektion, Entscheidung, Audit und Sequenzen gemeinsam zurück. Die
vorherigen Audit-/Sessionglobals werden im Werkzeug wiederhergestellt.

Am 27.10.2026 unter PHP 8.5.12 bestanden, ebenso notwendige PHP-Syntaxprüfung,
Composer-Validierung/Plattformanforderungen und `git diff --check`.
Der statische Quellenvergleich bestätigte die mechanische Äquivalenz und das
bytegleiche native Listen-/UIende; die Laravel-Routenliste bleibt nur `/up`.
Dies ist **keine** reale Browser-/Middleware-/Laravelwriter-/API-/Terminalabnahme
und kein Abschluss von #78. Abwesenheits-/Modellschreibdienste sowie die echte
Korrekturanwendungsgrenze und deren spätere Cutoverfreigabe bleiben offen.
