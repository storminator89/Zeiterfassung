# FW-07 / #78 – inaktiver lokaler Web-Korrekturdienst

**Kleiner Folgeschritt des [gemeinsamen Korrekturkerns](correction-core.md),
kein Cutover und kein vollständiges #78.** `LocalWebCorrections::apply(command)`
verbindet nur vertrauenswürdige lokale Webidentität mit dem bestehenden Kern.
`LegacyLocalWebCorrections` und seine Containerbindung sind lazy/scoped;
Registrierung/Auflösung öffnen keine DB oder Authsession und binden keinen Writer.
Es gibt weiterhin keine Korrekturroute, keinen Controller, keine produktive
PDO-Factory oder konfigurierte `chrono`-Verbindung. Die native Korrekturseite
und sämtliche Fach-SQL bleiben unverändert auf dem bereits extrahierten Kern.

## Fachregeln und Audit nur einmal

- Der optionale fünfte Parameter von `correction_apply_locked` ist ausschließlich
  ein **vertrauenswürdiger interner** Auditrunner `(reason, operation)`. An den
  zwei bisherigen Auditgrund-Stellen ruft der Kern ihn mit dem bereits dort
  validierten Grund und dem unveränderten folgenden Mutationsblock auf.
  Der Command kann weder Runner noch Akteur, Rolle, Quelle oder Auditgrund
  überschreiben. Die Fachgründe konstruiert ausschließlich der Kern.
- Ohne Runner setzt die native Fallbackclosure exakt wie zuvor
  `$GLOBALS['audit_reason']` und führt denselben Block aus. Sie restauriert
  den Grund bewusst **nicht** neu und ändert keinen Akteur. Zeitpunkte,
  Snapshotencoding, Fehler, Transaktionsbesitz und alle Regeln bleiben gleich.
- `AttendanceWriter::correction` liefert dagegen einen festen Runner mit dem
  bestehenden scoped `AuditContext::run`: Akteur exakt **`web:{id}`**,
  Grund exakt `Korrekturantrag: {Grund}` bzw.
  `Korrektur #{id}: {Entscheidungsgrund}` aus dem Kern. Kein Audit liest dafür
  native Globals oder Sessionfelder. Ein vorhandener äußerer Kontext wird
  bei Erfolg und Fehler durch den bestehenden Context wiederhergestellt.
- Keine kopierte Antrag-/Freigabe-/Storno-SQL, Berechtigungsregel oder
  Auditablage. Die direkte Team-/Vieraugenprüfung bleibt im Kern unter Lock;
  weder eine allgemeine Lesepolicy noch ein Admin-Bypass ersetzt sie.

## Ein gemeinsamer Writer, keine zweite Transaktionsverwaltung

Im unverändert benannten `AttendanceWriter` ist die bisherige #75-Hülle nur in
`private executeLocked(body, auditScope?)` verschoben: gleicher dedizierter PDO,
stock `SQLiteConnection`, genau ein IMMEDIATE-Versuch, aktive/idle Prüfungen,
COMMIT, Fehlerrollback und Discardregel sowie Wiederherstellung vorhandener
Auditglobals einschließlich `null`. Constructor, Admission und Guards sind
unverändert; es gibt keine öffentliche beliebige `write(callback)`-API.

Die bestehende Stempeloperation liefert ihre bisherige äußere
`AuditContext::run`-Closure als `auditScope`, sodass auch Kontext-/Fehler-/Rollback-
Reihenfolge unverändert bleibt. Ihr Akteur bleibt weiterhin
`{source}:user:{id}`, **nicht** der Korrekturakteur. Die Korrekturoperation
verwendet keinen pauschalen Ersatzgrund, sondern ausschließlich den Runner an
den beiden Kernstellen. Unerwartete Mutation ohne Kontext würde durch die
bestehenden UDFs verweigert. Kein Raw-BEGIN im Kern, zweite Transaktion,
Savepoint, Reconnect, Queue oder automatischer Retry.

## Auth- und Betriebsvoraussetzungen

- Nur Laravels fester `web`-Guard liefert `LegacyUser`, positive ID,
  `auth_source=local` und bekannte Rolle. Erst danach wird der Writer aufgelöst;
  ohne ausdrücklich zugelassenes Operation-Scope-Binding wird verweigert.
  Der Dienst nimmt lediglich die bisherigen Formfachfelder an; er validiert
  keine alternative HTTPantwort und normalisiert keine Booleans oder Zeiten neu.
- Vor einer späteren HTTPnutzung bleibt zwingend #76
  **`RefreshLocalAccount` → `auth:web` → `auth.session`** plus Websession/CSRF.
  Genau diese Kette stellt aktuelle Rolle, lokale Quelle und Passwortwiderruf
  her. Es gibt keine neue Account-/Rollenauffrischung unter Lock und keine
  stärkere atomare Authassurance als beim bisherigen nativen Handler.
- Die spätere vertrauenswürdige Composition muss #74/#75-Offlinebackup,
  Adoption und frischen dedizierten unveränderten SQLite-PDO erfüllen;
  Guard/Writer müssen denselben zugelassenen Bestand meinen. Writer/PDO nicht
  über Operationscopes aufbewahren. Keine neue Produktionseinbindung,
  DB-/Schema-/Host-/Schlüsseländerung oder neue Berechtigung.
- API und Terminal verwenden diesen lokalen Webdienst nicht. Ihre getrennten
  Guard-/Grant- und Transportverträge sowie spätere Runtime-/UIumschaltung
  bleiben eigene freizugebende Schritte; #78 bleibt offen.

## Tatsächlich geprüft

```sh
php framework/tools/rehearse-web-corrections.php
php framework/tools/prove-sqlite-writes.php
php framework/tools/rehearse-corrections.php
```

Ein neuer grober synthetischer Dateiablauf: inaktive/authentifizierte
Admissiongrenze; eigener Antrag und fremde direkte Supervisorfreigabe mit
exaktem scoped Audit; injizierter letzter Schreibfehler rollt Projektion,
Entscheidung, Audit und Sequenzen zurück und restauriert beide Auditkontexte.
Nur das Werkzeug bindet frische zugelassene Writer auf neuen privaten
synthetischen V2-Dateien; Original-/Backuphash bleiben identisch. Guard-
`setUser` ist ausschließlich synthetische Composition, kein echter Login.
Wegen der geteilten Hülle wurde der **unveränderte bestehende #75-Grobbeweis**
mit Konkurrenz/Busy-Commit/Replay/Rollback/PDOwechsel einmal ausgeführt;
der bestehende native Korrektur-Groblauf prüft die Fallbackspur.

Am 27.10.2026 unter PHP 8.5.12 / Laravel 13.38.0 bestanden, ebenso notwendige
PHP-Syntax-/Composer-/Plattformprüfung und `git diff --check`. Statischer
Vergleich: Kern nach Runner-Unwrapping exakt gleich; Writerhülle nach benannten
Seamsubstitutionen exakt gleich; Admission/Guards, native Seite und bisherige
Fachhelfer bytegleich. Routen weiterhin ausschließlich `/up`. Keine echten
Daten/Schlüssel, HTTPserver, lokalen Browserfunktionstests, Port 18083,
gemeinsamen Fixtures oder neuen CI-Läufe. Keine vollständige Web-/API-/Terminal-,
Middleware-, CSV-/UI- oder operative Cutoverabnahme.
