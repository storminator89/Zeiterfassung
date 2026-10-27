# FW-07 / #78 – gemeinsamer Abwesenheitskern und inaktiver Webdienst

**Kleine mechanische Extraktion und lokale Webadaption, kein Cutover und kein
vollständiges #78.** Das bestehende Formular in `attendance.php` sendet weiter
unverändert an `save.php`; dessen Abwesenheitszweig ruft weiter
`practice_absence` auf. Keine Route, Controlleranbindung oder UIänderung.

## Ein bestehender Fachkern

`practice_absence_range` enthält die bisherige Datums-/Typvalidierung genau
einmal. Die native Hülle prüft weiterhin **vor `BEGIN IMMEDIATE`**, lässt BEGIN
außerhalb des `try` und behält COMMIT/ROLLBACK sowie Fehlerweitergabe.
`practice_absence_apply_locked` verlangt eine aktive Transaktion und wiederholt
denselben deterministischen Parser unter Lock. Die Prüfung erkennt eine aktive
Transaktion, nicht deren Lockmodus; **IMMEDIATE auf genau diesem PDO bleibt
Pflicht des Aufrufers**. Kein neuer Account-, Rollen- oder Anspruchscheck.

Der bestehende Tagesloop mit SQL und allen Hilfsaufrufen ist unverändert:

- Exakte Berliner `Y-m-d`-Kalendertage, Ende nicht vor Start, Datumsdifferenz
  höchstens 366 Tage; beide Endtage eingeschlossen, also gegebenenfalls
  **367 Kalendertage**. Arten exakt `Urlaub` und `Krankheit`.
- `planned_minutes` wählt das historische Modell nach `valid_from DESC,id DESC`
  und berücksichtigt vorhandene `Feiertage`. Nur Nullminutentage werden
  übersprungen. Ein im Modell belegter Samstag/Sonntag bleibt erlaubt; keine
  neue pauschale Wochenendsperre oder Feiertagsquelle.
- Der ganze lokale Tag wird mit unverändertem `booking_assert_no_overlap`
  geprüft: halboffene Grenzen, offene/übernachtige Arbeit sowie heutige und
  klassifizierte Altgutschriften. Ein Konflikt rollt den gesamten Zeitraum
  zurück, auch schon vorher in diesem Aufruf geschriebene Tage.
- Je Tag ein INSERT in `zeiterfassung`: `absence_kind`, gespeicherter
  `planned_minutes`-Snapshot, Pause 0, leerer Standort und das bisherige
  ISO-/Offset-Kompatibilitätsintervall ab Tagesbeginn. Keine Stempelintervalle
  oder `attendance_events`. Historische Modelländerungen verändern das Soll,
  nicht gespeicherte Gutschriften. Bestehende DST-Kompatibilitätsberechnung,
  Berichtsformeln und Feiertagsabdeckung werden nicht still korrigiert.

Der optionale interne Runner `(reason, operation)` ist nur vertrauenswürdige
Composition. Der Kern liefert exakt **`Abwesenheit: {Art}`**. Die native
Fallbackclosure setzt wie zuvor `$GLOBALS['audit_reason']` vor dem Tagesloop,
lässt ihn auch bei Nulltagen/Fehlern gesetzt und verändert keinen Akteur.
Der Erfolgswert wird nun im Kern konstruiert, aber weiterhin erst **nach
erfolgreichem COMMIT** zurückgegeben. Sonst bleibt der Antwortvertrag identisch:

```php
['success' => true, 'message' => 'N Abwesenheitstage nach Arbeitszeitmodell erfasst.']
```

`N=0` ist weiterhin ein Erfolg ohne Mutation. Datum/Typ liefern die bisherigen
`AttendanceError` mit Status 400 und Text, Überlappungen den bisherigen Status
409. Technische Fehler werden unverändert weitergereicht. Ein späterer
HTTPtransport muss das bestehende `attendance_json`-Mapping bewahren, nicht
pauschal Laravel-422 erzeugen. Keine Event-ID, Retryablage oder automatische
Wiederholung: eine doppelte Gutschrift wird wie bisher als Überlappung
verweigert, nicht als erfolgreiche idempotente Wiederholung beantwortet.

## Derselbe Writer und eigene vertrauenswürdige Webidentität

`AttendanceWriter::absence(user,start,end,kind)` prüft mit demselben Parser vor
der Transaktion und delegiert ausschließlich über das bestehende private
`executeLocked` an den Kern. Constructor, Admission, IMMEDIATE-Hülle,
Commit-/Rollback-/Discardregeln und Globalwiederherstellung bleiben bytegleich.
Der feste Runner verwendet den vorhandenen scoped `AuditContext::run` mit
**`web:{ID}`** und dem Kerngrund. Projektion und Triggeraudit bleiben atomar;
kein zweites Journal oder Transaktionssystem. Äußere Kontexte und vorhandene
Auditglobals einschließlich `null` werden bei Erfolg und Fehler restauriert.

`LocalWebAbsences::create(string start,string end,string kind): array` und
`LegacyLocalWebAbsences` sind lazy/scoped gebunden. Auflösung öffnet keine DB
oder Authsession. Nur der feste `web`-Guard liefert `LegacyUser`, positive ID,
`auth_source=local` und bekannte Rolle (`user`, `supervisor`, `admin`); ungültige
Identität wird vor Writerauflösung verweigert. **Jede Rolle bucht nur für sich**.
Es gibt keinen Zielkonto-, Akteur-, Rollen-, Quellen- oder Auditparameter aus
einem Command. Die späteren ursprünglichen Formfelder lassen sich unverändert
auf `urlaubStart`, `urlaubEnde`, `beschreibung` abbilden; das Paket aktiviert
diesen Transport nicht.

## Weiterhin zwingende Betriebsgates und Grenzen

- Vor späterer HTTPnutzung #76 **`RefreshLocalAccount` → `auth:web` →
  `auth.session`**, Websession und CSRF. Kein neuer lockzeitiger Account-/
  Rollen-/Authquellenrefresh und keine stärkere atomare Authassurance als
  beim bisherigen nativen Handler.
- Frischer dedizierter explizit zugelassener Operation-Writer nach #74/#75-
  Offlinebackup, Vorprüfung und Adoption. Guard und Writer müssen denselben
  zugelassenen Bestand meinen. Kein Singleton-PDO, Reconnect oder Raw-Parallel-
  Writer. Ohne ausdrückliche Writerbindung wird verweigert.
- Keine konfigurierte `chrono`-Verbindung, produktive PDOfactory, reale DB,
  Zugangsdaten, Schema-/Host-/Securityänderung oder Runtimeaktivierung.
  API-/Terminaladapter, HTTP-/Middleware-/UI-/CSVabnahme und Cutover bleiben
  getrennte Schritte. Keine neue Urlaubsfreigabe, Anspruchs-, Payroll- oder
  Zeitkontopolitik. #35/#36/#57 und #78 bleiben offen.

## Tatsächlich geprüft

```sh
php framework/tools/rehearse-web-absences.php
```

Ein parameterloser grober synthetischer CLI-Ablauf verwendet den vorhandenen
V2-Fixturebuilder und echte Migrationen, zunächst Memory, danach ausschließlich
neue private synthetische Original-/Backup-/Arbeitskopien mit expliziter
Offlineadoption. Er prüft inaktive Auth-/Admissiongrenzen und native Auditspur;
Gutschriften mit Nulltag, gespeichertem Feiertag und belegtem Samstag;
unveränderte eigene Auditidentität, doppelte/teilweise überlappende Zeiträume
mit Gesamtrollback, Modellhistorie ohne Neubewertung gespeicherter Gutschriften
und einen verbindungslokalen Fehler nach dem letzten Auditinsert. Dieser rollt
beide Gutschriften, Journal und Sequenzen zurück und restauriert beide
Auditkontexte. Original-/Backuphash bleiben identisch; Default und `chrono`
werden nicht konfiguriert oder geöffnet. Guard-`setUser` ist nur synthetische
Composition, kein echter Login oder Middlewarebeweis.

Am 27.10.2026 unter PHP 8.5.12 / Laravel 13.38.0 bestanden, dazu notwendige
Syntax-/Composer-/Plattformprüfung und `git diff --check`. Statischer Vergleich
bestätigt Parser und Tagesloop, unveränderte übrige Practicehelfer, Writerhülle
und bestehende Providerbindungen sowie bytegleiches natives Formular/Save und
alle Routen. Keine Vollsuite, Feinunit-/DSTmatrix, erneuter Writerstresstest,
lokalen HTTP-/Browserfunktionstests, Port 18083, gemeinsame Fixtures oder neuen
CI-Läufe. Keine vollständige oder operative #78-Abnahme behauptet.
