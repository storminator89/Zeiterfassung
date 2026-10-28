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
