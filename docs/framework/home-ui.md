# FW-08 / #79 – bestehende Startseite: Arbeitszeiten, Statistik und Abwesenheit

**Opt-in Fortsetzung der vorhandenen Oberfläche, kein Default-/Produktivcutover.**
Login, Navigation, Stempeln, Korrekturen und Abmeldung bleiben unverändert.
Die bestehenden Karten und das Abwesenheitsformular werden mit denselben
Read-/Abwesenheitsdiensten verbunden; das HTMLfragment übernimmt die bestehende
Arbeitszeittabelle. Keine neue Dashboardoberfläche, kein SQL, Sollmodell,
Zeitkonto oder Berechtigungssystem im Controller beziehungsweise JavaScript.

## Gemeinsame Daten statt neu erfundener Berechnungen

`ChronoReads::homeSummary()` liefert ausschließlich eigene Anzeigedaten innerhalb
der vorhandenen authentifizierten DEFERRED-Lesetransaktion. Die gemeinsame
[Homeprojektion](home-read-projection.md) besitzt die nativen Formeln, darunter:

- Arbeitstage sind die **positiven Solltage im gesamten aktuellen Berliner
  Kalendermonat**, nicht die Anzahl gestempelter Tage.
- Gesamtüberstunden sind der gespeicherte Übertrag plus die Differenz zwischen
  abgeschlossenen Istminuten und historischem Soll **aller vorhandenen
  Berichtsstarttage**, nicht lediglich des aktuellen Jahres. Keine neue
  kontinuierliche Zeitkontenberechnung für Tage ohne Datensatz.
- Tages-Soll und hinterlegte Wochenfeiertage kommen aus denselben Helfern und
  dem zugelassenen Bestand. Kein Kalenderdownload, Holiday-INSERT oder heimliches
  Nachladen aus `functions.php`; fehlende Kalenderabdeckung bleibt ein Gate.
- Genau wie nativ werden die Statistikkarten beim vollständigen Seitenaufruf
  berechnet. Nach einer Buchung werden Status/Tabelle aktualisiert; Karten
  werden nicht im Browser neu berechnet. Die geerbte Einschränkung zur
  Aktualitätsmarkierung ist unten ausdrücklich beschrieben.

`LocalWebHomeController::records` verwendet `reportRows(chronological: true)`.
Der bisherige parameterlose Read bleibt für die nativen Korrekturoptionen
lexikalisch sortiert; nur Home nutzt den vorhandenen `report_register_start_order`
und `chrono_report_start_epoch(startzeit) DESC,id DESC` **vor** zehn Rohzeilen
und Berliner Gruppierung. Sichtbare Details bleiben seitenlokal; der vollständige
eigene Snapshot wird zusätzlich an `report_time_record_groups` übergeben.
Dadurch umfassen Summen alle Tageszeilen, und off-page/übernachtige
Arbeitszeit-Abwesenheitskonflikte gehen nicht verloren. Teilansichten zeigen
sichtbare/alle Zeilen und den vorhandenen nativen Hinweis.

Die aktuelle native Diagnose aus #16/#35 wird sichtbar übernommen: ungültige
Startwerte erhalten eigene opaque DOM-Gruppenschlüssel, ungültige Zeilen zeigen
den unveränderten Fehler und keine erfundene Nullsumme. Konflikte behalten
Einzelwerte, kennzeichnen betroffene Tage aber als „Klärung offen“.
`homeSummary()` optiert ausdrücklich in beide nativen Diagnoseausgaben ein.
Bei ungültigen eigenen Zeilen oder Konflikten sind die kumulierten
Überstundenfelder nicht numerisch (`null`/„Unvollständig“ beziehungsweise
„Klärung offen“), ohne Erfolgs-/Fehlerfarbe. Datenqualitäts- und
Vier-Augen-Korrekturhinweis stehen im bestehenden Layout.

Der native Webstatus liefert `report_finality` (all/week/month/year) aus dem
vollständigen eigenen Kontext im selben Snapshot wie der Status. Die erhaltene
native `attendance.js` nutzt die vorhandenen `data-report-*`-Markierungen,
invalidiert Werte bei `overlap_warning`, unbestätigtem Ende, ungültiger
Finalitätsmetadaten oder fehlgeschlagenem leisem Refresh und stellt keine
gemerkten Beträge wieder her. Erfolgreiche konfliktfreie Buchungen und
Abwesenheiten invalidieren Karten im geerbten Client dagegen **nicht generell**;
auch ein fehlgeschlagener unmittelbarer `refresh(true)` setzt nicht in jedem
Pfad eine Aktualitätsmarkierung. Diese bestehende Einschränkung ist in diesem
Migrationspaket nicht behoben; aktuelle Zahlen erfordern bewusstes Neuladen.
Formularzustand wird nicht durch Auto-Reload verworfen. Ein neuerer
Statuskonflikt unterdrückt schon beim ersten Rendern einen älteren Summenwert.
Die rohe öffentliche API-Statusantwort bleibt davon getrennt und unverändert.

Blade escaped alle dynamischen Gruppen-/Detailwerte. Offene Zeilen erhalten
keine fiktive Endzeit. Gutschriften zeigen gespeicherte Sollminuten, Datum und
„Keine Stempelzeit“ statt vorgetäuschter Stempelungen. Bestehende Klassen,
Spalten, Icons, Aufklappzeilen, Pagingbuttons und `adjustments.php?record_id=…`
bleiben erhalten. Der bestehende CSVbutton bleibt sichtbar deaktiviert und
explizit als noch nicht verbunden gekennzeichnet.

## Ursprüngliches Formular und dünner Transport

Nur der getrennte `POST save.php`-Handler kann `LocalWebAbsences::create` aufrufen.
`GET get_time_records.php` ist ein reiner HTML-Lesepfad; Method Override zu GET
kann keinen Schreibhandler erreichen. Die Felder `urlaubStart`, `urlaubEnde`,
`beschreibung` kommen ausschließlich aus dem rohen POSTbag, mit nativen
`isset`-/Stringcasts. Keine Laravel-422-Validierung, Trim-/Leere-Umdeutung oder
Akteur-/Zielkonto-/Rollen-/Quellen-/Auditparameter aus dem Request.

Der Dienst und einzige frische zugelassene Writer besitzen Validierung,
Tagesüberlappung, historischen Sollsnapshot, atomare Projektion und Triggeraudit.
Die Antwort ist unverändert `success,message`; `AttendanceError` liefert den
bestehenden Status/Text, technische Fehler das generische native 503-JSON.
Alte Direktänderungs-/Löschfelder liefern weiter 409. Der ungenutzte
`save.php`-Stempelalias ist ausdrücklich nicht Teil dieses Kandidaten und
liefert 410, nicht versehentlich eine Abwesenheit. Sonstige unbekannte Formulare
liefern wie nativ 400. Keine neue Idempotenz-/Auto-Retryregel für Abwesenheiten.

Die bestehenden Assets `main.js` und `attendance.js` bleiben bytegleich:
`main.js` ist bereits der schreibfreie Tabellen-/Präsentationsclient;
`attendance.js` sendet das vorhandene Formular, sperrt Doppel-Submit, übernimmt
die bestätigte Meldung und aktualisiert Tabelle/Status. Nur die kleine vorhandene
Preview-Startbindung aktiviert das anfangs gesperrte Abwesenheitsfieldset nach
erfolgreicher Initialisierung dieses Clients. Wenn der Pending-Startgate blockiert,
bleibt es gesperrt. Fremde/fehlerhafte Pendingdaten werden weder verworfen noch
automatisch wiederholt. Das löst nicht die umfassenden #57-Grenzen.

## Aktivierungsgrenze

Keine Defaultregistrierung: die normale App besitzt weiterhin nur `/up`.
Der explizite lokale Registrar verlangt die bereits freigegebene persistente
Laravel-Sessionblockierung und dieselbe Middlewarefolge: Websession/CSRF,
`RefreshLocalAccount`, `auth:web`, `auth.session`. Async-Fragment-/POST-Authfehler
sind 401, nicht erfolgreiche Login-HTMLfragmente. CSRF bleibt obligatorisch.
Guard, Lesesnapshot und frischer dedizierter Operationwriter verwenden denselben
privat offline adoptierten synthetischen Bestand. Sessioncookies behalten
Secure/HttpOnly/Lax und eine eindeutige Fixturekennung.

Die neuen `Interactions`-Anzeigeflags sind vertrauenswürdige Composition und
standardmäßig `false`, keine Berechtigung und keine Requestparameter. Nur die
vorhandene parameterlose Fixturecomposition setzt sie. Der Router erlaubt
zusätzlich ausschließlich beide genannten Pfade und das vorhandene `main.js`;
kein Repository-Docroot oder Legacyfallback. Bestehende noch nicht angebundene
Navigationsziele bleiben ehrlich 410. Keine Produktivdaten/-schlüssel,
Host-/Securityänderung oder Berührung von Port 18083.

## Abnahme dieses Pakets

Notwendige PHP-/JavaScript-Syntax-, Composer-/Plattform- und Diffprüfung
bestanden. Vier synthetische ganze Bladeansichten (Home/Tabelle, de/en) wurden
kompiliert; dabei keine DBverbindung geöffnet und keine native Session verändert.
Die normale Routenliste blieb `/up`; der separat registrierte GETfragment-/
POSTformularpfad besitzt die bestehende CSRF-/Refresh-/Guard-/Authsessionfolge
und verlangt persistente Laravel-Sessionblockierung. Das ist kein HTTPbeweis.
Der einzelne isolierte echte Browserablauf steht noch aus. Geplant ist nur Home → eine nicht überlappende eigene Abwesenheit →
sichtbare Tabellen-Gutschrift/Tageszeit → Seitenreload/Statistik → Logout.
Read-only Vorher/Nachherbelege prüfen gespeicherte Sollminuten, eigenen Auditakteur,
keine Stempelereignisse/-intervalle und unveränderte Original-/Backuphashes.
Keine Mikro-/Rollen-/DST-/Stresstestmatrix oder neue Vollsuite. Noch keine
Funktions-, Browser- oder vollständige #79-Freigabe behauptet.


## Aktuelle Main-Integration, weiterhin WIP-Draft

Fortsetzung der existierenden PRs #110/#111 auf Main `165ce380`, keine zweite
Oberfläche. Alle nativen Rootdateien/Assets, Authprovider, Defaultregistrierung,
Konfiguration und privaten Daten bleiben unverändert. Die ebenfalls vorhandene
Korrekturansicht behält vorerst ihre frühere 200-Options-/Antragslisten-Anbindung;
neue native Korrekturauswahl-/Queuepakete sind damit nicht automatisch migriert.

Für diesen Stand wurden nur notwendige Syntax/Composer/Plattformprüfungen und
fünf synthetische vollständige Bladeansichten mit nativen reinen Arrayhelfern
geprüft. Kein Guard/Login, keine Credential-/CSRF-Injektion, keine DBverbindung,
HTTPanfrage, Fixturevorbereitung oder Browser-/Serveraktivierung. Die vorhandenen
Memory-Rehearsal-Erwartungen wurden dem zusätzlichen Diagnosevertrag angepasst,
aber die auth-/datenbankgestützten Rehearsals nicht ausgeführt.

Die frühere echte Home-/Abwesenheitsabnahme bleibt **BLOCKIERT / unakzeptiert**:
Secure-Credential-Eingabe wurde verweigert; die eine BrowserAuth-Anfrage endete nur
mit „aborted by user“, ihr terminaler Managerstatus ist unbekannt. Kein Retry,
Loginersatz, Sessioninjektion oder anderer Umweg ist Teil dieser Integration.
Beide PRs bleiben Draft und nicht merge-ready, selbst bei grüner CI. Native
Bestandsseiten beweisen keine Laravel-UI-Abnahme; keine Route-/Produktivumschaltung.
