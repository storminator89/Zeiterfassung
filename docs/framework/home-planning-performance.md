# #56 – kurzlebige Home-Planungsdaten

Begrenzte Optimierung der bereits vorhandenen eigenen Home-Leseprojektion,
**keine UI-Änderung, kein Cutover und keine neue Runtime-/V5-Freigabe**.

`home_summary` nutzt nur dann einen Planresolver, wenn derselbe übergebene PDO
bereits in einer Transaktion ist. Der Laravel-Read besitzt diese autorisierte
`DEFERRED`-Lesetransaktion bereits und lädt darin die aktuelle Identität und Rolle
neu. Die Optimierung öffnet keine Verbindung/Transaktion und liest keine fremden
Modelle, Auth-/Sessiondaten oder Kalenderquelle. Native Aufrufer ohne Transaktion
behalten ihren bisherigen Einzelabrufpfad.

## Unveränderte Fachsemantik

- `planned_minutes` und der Resolver verwenden dieselbe ausgegliederte bisherige
  Sollformel (`planned_minutes_value`), keine zweite Berechnungsregel.
- Ein gespeicherter Feiertag hat weiterhin Vorrang und ergibt null Sollminuten.
- Das wirksame Modell bleibt das letzte mit `valid_from <= Tag`, bei gleichem
  Beginn gewinnt die höchste ID. Ältere Modelle vor dem Berichtszeitraum bleiben
  berücksichtigt; zukünftige Modelle greifen erst ab ihrem Beginn.
- Ohne wirksames Modell bleibt der bisherige Werktags-Fallback aus
  `users.regelarbeitszeit` mit Rundung und Begrenzung auf 0–1440 Minuten bestehen.
  Modell-Samstage/-Sonntage bleiben dagegen positive Arbeitstage.
- Der ganze aktuelle Berliner Monat und alle gelieferten Bericht-Starttage
  werden abgedeckt. Das ist weiterhin **kein** kontinuierliches Zeitkonto:
  fehlende Tage erzeugen keinen Abzug, gespeicherte Gutschriften werden nicht neu
  berechnet. Berichtdiagnosen und die bestehende Nichtabschließbarkeit bleiben
  außerhalb der Optimierung unverändert.

Der Resolver lädt Feiertage im benötigten Zeitraum, nur die Modellhistorie des
bereits festgelegten Nutzers bis zum letzten benötigten Tag und dessen bisherigen
Fallback. Die Modellwahl erfolgt per binärer Suche. Er wird synchron nur für
diese eine Home-Aufbereitung verwendet und danach verworfen: kein statischer,
Session-, Request-übergreifender oder Autorisierungscache und keine neuen
DTO-Felder. Außerhalb seines Datumsbereichs verweigert er einen Abruf. Die
Wochenfeiertagsliste verwendet unverändert ihren bisherigen separaten Read.

## Begrenzter synthetischer Nachweis

Ausführen: `php framework/tools/rehearse-home-planning.php` (PHP 8.5 und PDO SQLite).
Das Tool verwendet ausschließlich eine anonyme `:memory:`-Datenbank mit den drei
kanonischen Tabellen-DDLs und erfundenen Daten; kein Framework-Bootstrap,
App-Migration, HTTP, Guard, Session oder verwendbares Kennwort.

Der Nachweis vergleicht jeden Plan mit dem nativen Einzelabruf (2.244 Vergleiche)
und vollständige Home-Felder in sechs Randfällen sowie fünf skalierten Fällen
mit 30, 365, 3.650 und 10.000 Berichtstagen. Zusätzlich: Grenzen/Datumsfehler,
Caller-Transaktionsbesitz, unveränderte Quellzeilen/Globals/Zeitzone und frische
Daten in der nächsten Operation. Drei SELECT-Ausführungen laden die Planungsdaten;
mit unverändertem Wochenfeiertags-Read benötigt `home_summary` konstant vier.
Die Assertions messen keine Produktionslatenz und ersetzen **keine** legitime
Laravel-Login-, HTTP-/UI- oder produktive Lastabnahme. Kein neuer CI-Lauf.

## #56-Nachtrag: vorhandene Kalenderstatistiken verwenden denselben Resolver

Isolierter Quellschritt auf Main `67a22db805f9e73a7a50d31e3f53316d7a15b649`,
Baum `4293e4cf603e757a960bf5669190e59654bf482d` nach #68/#258. Der bestehende
`LegacyChronoReads::calendarWeek()`-Read besitzt bereits dieselbe frisch
autorisierte Lesetransaktion. Seine fünf Planungswerte (Wochen-/Monats-/Jahressoll,
formatiertes Tages-Soll und erfasste-Tage-Saldo mit Übertrag) führten bislang
trotzdem für jeden angefragten Tag wieder die bisherigen einzelnen SQL-Reads aus.

Vor der Änderung wurde dieser **Teilpfad**, nicht die ganze Kalenderseite, an
erfundenen kanonischen SQLite-Memorytabellen gemessen. Ein grober Vorher-/Nachher-
Durchlauf unter PHP 8.5.12 / SQLite 3.46.1 bestätigt exakt gleiche Zahlen und
formatierte Werte, einschließlich der bisherigen Fließkomma-Akkumulationsfolge:

| Erfasste Tage | Konfiguration | SELECT-Ausführungen vorher | Nachher |
| ---: | --- | ---: | ---: |
| 30 | Modellhistorie | 860 | 3 |
| 365 | Modellhistorie | 1530 | 3 |
| 3650 | Modellhistorie | 8099 | 3 |
| 10000 | Modellhistorie, davor Fallback | 21001 | 3 |
| 3650 | Werktagsfallback | 12144 | 3 |

Zeit und zusätzliche PHP-Heapspitze wurden ebenfalls beobachtet: im gespeicherten
Vergleich für 10000 Tage ungefähr 154 auf 31 Millisekunden, bei ungefähr 5,3 auf
7,8 KB zusätzlichem PHP-Heap. Dies sind einzelne synthetische Beobachtungen ohne
Messschwelle, belastbares Produktionsprofil oder Ganzseiten-/Speicherzusage.
Weitere bestehende Kalender-, Historien-, Auth- und Statusreads sind in diesen
fünf Planwerten **nicht** enthalten. Die ganze Seite liest weiterhin die bisherige
Historie; kein bereits eingetretener Produktionsengpass wird behauptet.

Der Read baut einmal den unveränderten `planned_minutes_snapshot()` für aktuelle
Berichtsperioden und alle gelieferten Bericht-Starttage auf. Die Grenzen umfassen
auch eine aktuelle ISO-Woche über den Jahreswechsel. Historisches Blättern bleibt
wie bisher unabhängig von den aktuellen Statistikperioden. Derselbe Resolver
wird synchron an die vorhandenen drei Summen-, Tages- und Saldohelfer gegeben und
nicht im DTO, global, statisch oder in einer Session behalten. Kein neuer Cache
für Identität, Teams, Rollen, Verbindungen oder spätere Operationen.

`planned_hours()` erhält ausschließlich einen optionalen vertrauenswürdigen
Resolver-Endparameter. Bestehende Vierargumentaufrufe behalten ihren bisherigen
Einzel-SQL-Pfad, Schleife und Sollformel. Feiertagsvorrang, wirksames Modell nach
Datum/höchster ID, Fallback/Rundung/Begrenzung, gespeicherte Gutschriften,
Startdatumszuordnung, Diagnosen und abschließende Nullsetzung ungeklärter oder
unvollständiger Statistikfelder ändern sich nicht. Kein neuer Berichtswert,
Index, Writer, UI, JSONfeld, Featureflag oder Runtime-/V5-Zugang.

Der eine grobe Vergleich umfasst zusätzlich sieben kombinierte Randfälle:
unsortierte alte/zukünftige Berichtstage, Jahres-/ISO-Wochenwechsel, Schaltjahr,
beide DST-Grenzen, leere Berichte und vorhandene/fehlende/negative/begrenzte/
null-Fallbackwerte. Quellzeilen, Audit-/Sessionglobals und Defaultzeitzone bleiben
unverändert; eine nachfolgende Operation sieht ein neu gespeichertes Modell.
Der Resolver besitzt weiterhin nicht die Transaktionslebensdauer. Notwendige
PHP-Syntax, definitions-only Klassenkomposition mit gleichem Composerlock und
vollständige Quellerhaltung sind separat geprüft. Nach dem Reanchor wurde die
bereits integrierte Einpass-`report_days`-Quelle unverändert mitgeprüft: Ein grober
Vergleich führt den tatsächlichen extrahierten Kalendercallback samt originalen
`rows`-/`reportFinality`-Methoden gegen dieselben erfundenen Memoryzeilen aus.
Nur Uhr und Include-Wurzel sind injiziert; Auth-/Snapshotwrapper laufen nicht.
Alle vollständigen DTO-Hashes sowie Statistik-/Diagnose-/Finalitätswerte sind vor
und nach der Änderung identisch: Mischbestand mit Arbeit/Gutschrift, aktuelle und
historische Navigation, bekannte/unbekannte ungültige Zeit, Nachtkonflikt,
ISO-Jahreswechsel, Fallback und leerer Bestand. Quellzeilen/Globals/Zeitzone bleiben
erhalten. Dies ist ein enger Quellenkompositionsnachweis, keine echte
Laravel-Request-/Berechtigungsabnahme. Keine kleinteilige neue
Repo-Testsuite, App-/HTTP-/Browser-/reale Daten-/Produktionsabnahme, neue CI,
Remoteaktion oder Deployment. #56 bleibt für echte Last-/UI-/Betriebsabnahme und
die übrigen Vollhistorienpfade offen.
