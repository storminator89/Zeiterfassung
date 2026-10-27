# FW-07 / #78 – gemeinsamer Arbeitsmodellkern und inaktiver Webdienst

**Begrenzte Extraktion und lokale Webadaption, kein Cutover und kein Abschluss
von #78.** Für Arbeitsmodelle gab es zuvor keinen aufrufbaren Schreibhelper:
`work_models.php` enthielt die einzige Validierung und INSERT-Anweisung.
`practice_work_model_apply_locked` in `lib/practice.php` enthält nun genau diesen
Kern; native Seite und inaktiver Laraveldienst verwenden ihn gemeinsam.
Kein Eloquent-CRUD, zweiter SQL-/Regelbestand oder neuer Transaktionsmanager.

## Native Reihenfolge und Fachvertrag

- Die Seite behält ihren bisherigen **Nichtadmin-403 vor `BEGIN IMMEDIATE`**,
  `BEGIN` außerhalb des `try`, danach den Kernaufruf, `COMMIT`, Redirect,
  `ROLLBACK` und bisheriges deutsches Fehlermapping. Listen, Formfelder, CSRF
  und das gesamte Markup bleiben bytegleich. Keine Validierung ist aus dem
  bisherigen `try` oder über dessen `BEGIN` verschoben.
- Nur die transportfreien Validierungs-/Schreibzeilen sind verschoben:
  Ziel-ID-Cast, `practice_date`, getrimmter Grund, `day1` bis `day7` mit
  unverändertem `FILTER_VALIDATE_INT` und **0 bis 1440 einschließlich**,
  danach Existenz des Zielkontos und Grundlänge **5 bis 1000 Bytes**.
  Reihenfolge, Fehlermeldungen/-status, `json_encode` und Server-`time()`
  bleiben unverändert. Administratoren dürfen weiterhin jedes existierende
  Konto wählen; keine neue Team-, Kontotyp-, Zukunfts- oder Duplikatsperre.
- Hinzu kommen nur interne fail-closed Guards **vor** dieser Validierung:
  aktive PDO-Transaktion und vertrauenswürdige Rolle exakt `admin`.
  Die native Seite hat diese Rolle bereits vor `BEGIN` geprüft. Die
  Transaktionsprüfung erkennt eine aktive Transaktion, nicht deren Lockmodus;
  **IMMEDIATE auf genau diesem PDO bleibt Pflicht des Aufrufers**.
- Weiterhin ausschließlich ein INSERT mit `created_by` aus der vertrauenswürdigen
  Identität. Modellhistorie/Trigger bleiben append-only, neuestes
  `valid_from`, danach höchste ID gewinnt. Kein UPDATE/DELETE, keine zusätzliche
  Initialmodell-Erzeugung und keine Schema- oder Datenmigration.
- `planned_minutes`, `planned_hours`, Feiertage und alle Berichts-/Abwesenheits-
  helper bleiben unverändert. Rückdatierte Modelle ändern das aktuelle Soll,
  **nicht** gespeicherte Gutschriften/`planned_minutes`, Projektionen oder
  Originale. Kein stilles Neubewerten historischer Buchungen und kein neuer
  Zeitkonto-/Payrollvertrag.

## Ein Audit- und Writerpfad

Der optionale interne Runner `(reason, operation)` ist ausschließlich
vertrauenswürdige Composition. Der Kern konstruiert den Grund genau an der
früheren Stelle als **`Arbeitszeitmodell: {getrimmter Grund}`**. Ohne Runner setzt
er wie zuvor `$GLOBALS['audit_reason']`, führt denselben INSERT aus und lässt
den Grund gesetzt; der native Auditakteur bleibt unangetastet.

`AttendanceWriter::workModel` ergänzt nur eine benannte Delegation an den
Kern innerhalb des bereits vorhandenen privaten `executeLocked`. Dessen
Transaktionshülle, Constructor, Admission, Guards, Commit-/Rollback-/Discard-
und Globalwiederherstellung bleiben bytegleich. Keine zweite Transaktion,
Savepoints, Retry, Reconnect oder beliebige öffentliche Callback-Write-API.
Der feste Runner verwendet den bestehenden scoped `AuditContext::run` mit
**`web:{Admin-ID}`** und dem vom Kern gelieferten Grund. Trigger schreiben
Modell und Audit in derselben Transaktion; keine zweite Auditablage. Äußere
Kontexte und vorhandene Auditglobals einschließlich `null` werden restauriert.

## Inaktiver Laravelvertrag und Betriebsgates

`LocalWebWorkModels::apply(command)` und `LegacyLocalWebWorkModels` sind
lazy/scoped gebunden; Auflösung öffnet keine DB oder Authsession. Der feste
`web`-Guard muss `LegacyUser`, positive ID, `auth_source=local` und bekannte Rolle
liefern. Nichtadmins werden vor Writerauflösung mit `AuthorizationException`
verweigert; der Kern prüft die vertrauenswürdige Adminrolle erneut. Dies ist
kein neues HTTP-Fehlerformat: es gibt **keine Route oder Controlleranbindung**.
`command.user_id` ist weiterhin das **Zielkonto**, niemals der Akteur.
Commandfelder für Akteur, Rolle, Quelle oder Audit beeinflussen diese nicht.

Vor späterer HTTPnutzung bleiben #76 **`RefreshLocalAccount` → `auth:web` →
`auth.session`** und Websession/CSRF zwingend. Keine neue atomare Rollenauffrischung
unter Lock wird behauptet. Guard und frischer explizit zugelassener
Operation-Writer müssen denselben Bestand meinen; #74/#75-Offlinebackup,
Vorprüfung/Adoption und dedizierter unveränderter PDO bleiben Pflicht. Keine
produktive PDOfactory, konfigurierte `chrono`-Verbindung, API-/Terminalfreigabe,
Host-/Security-/Schlüsseländerung oder Runtimeaktivierung. Abwesenheitsdienst,
echte Web/API/Terminal-/CSV-/UIabläufe und Cutover bleiben gesonderte Schritte.

## Tatsächlich geprüft

```sh
php framework/tools/rehearse-web-work-models.php
```

Ein parameterloser grober synthetischer CLI-Ablauf verwendet den vorhandenen
V2-Fixturebuilder und echte Migrationen, zunächst anonymen Speicher und dann
ausschließlich neue private synthetische Original-/Backup-/Arbeitskopien mit
expliziter Offlineadoption. Er prüft Auth-/Admin-/Admissionverweigerung,
native Auditfallbackspur, repräsentative Formvalidierung, append-only
Modellhistorie und gleiche Gültigkeit mit höchster ID, exakte scoped
Auditidentität sowie rückwirkendes Berichtssoll bei unveränderter gespeicherter
Abwesenheit. Ein verbindungslokaler Fehler nach dem Modell-Auditinsert rollt
Modell, Journal und Sequenzen zurück und restauriert beide Auditkontexte.
Original-/Backuphash bleiben identisch; `chrono` und Defaultverbindungen werden
nicht konfiguriert oder geöffnet. Guard-`setUser` ist nur synthetische
Composition, keine echte Anmeldung oder Middlewareabnahme.

Am 27.10.2026 unter PHP 8.5.12 / Laravel 13.38.0 bestanden, dazu notwendige
Syntax-/Composer-/Plattformprüfung und `git diff --check`. Statischer
Rückvergleich bestätigt den unveränderten nativen Validierungs-/INSERTblock
nach Runner-Unwrapping, den bytegleichen Seitenrest und die bytegleiche
gemeinsame Writerhülle. Deshalb kein erneuter Writerstresstest oder Vollsuite.
Routen weiterhin ausschließlich `/up`. Keine realen Daten/Zugangsdaten,
HTTPserver, lokalen Browserfunktionstests, Port 18083, gemeinsamen Fixtures
oder neuen CI-Läufe. Kein operativer oder vollständiger #78-Abnahmenachweis.
