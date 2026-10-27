# CH-04 / #9 – API nach Berliner Buchungstag filtern

Basis: `e49e5185e35ee92a27927917a42d2c672acb17af`.
Bezug: [Issue #9](https://github.com/storminator89/Zeiterfassung/issues/9).

**Späterer CH-18-Nachtrag:** Der Tagesvertrag unten bleibt erhalten, die damalige
unbeschränkte Pagination ist inzwischen bewusst auf Default100/Max1000 geändert.
Offset-only bleibt gültig, liefert aber eine begrenzte Seite; `meta.limit` ist
das wirksame Limit. [Aktueller Paginationvertrag](CH18-API-PAGINATION.md).

## Ein gemeinsamer Tagesvertrag

`GET /api.php/timeentries` verwendet für `start_date` und `end_date` jetzt
denselben **Berliner Starttag** wie `report_day`: `booking_time` interpretiert
offsetfreie Altwerte als `Europe/Berlin`; ein expliziter Offset oder `Z` bestimmt
den Instant. `booking_local_date` rechnet diesen Instant nach Berlin um.
Ein Beispiel: `2026-10-27T00:30:00+01:00` und
`2026-10-26T23:30:00Z` gehören beide zum **27. Oktober**, nicht zum UTC-26.
Sommer-/Winterzeit werden nicht mit festen `+1 hour`-/`+2 hours`-SQLmodifikatoren
nachgebildet. Der Datumspräfix des gespeicherten Texts ist kein Ersatz für die
Umrechnung.

Der definitions-only Adapter `lib/api_booking_day.php` registriert
`chrono_api_booking_day` ausschließlich auf dem jeweiligen Lese-PDO, wenn ein
Datumsfilter vorliegt. Er delegiert an den vorhandenen Berichtsparser; es gibt
keinen zweiten Parser oder Kalendervertrag. Der weiterhin **inaktive**
Frameworkleser verwendet denselben Adapter. Keine Routenaktivierung.

Beide Grenzen bleiben inklusiv. Zählabfrage und Seitenabfrage verwenden exakt
dasselbe Prädikat **vor** LIMIT/OFFSET. Eigene Person, rohe Zeilen, JSONform,
`meta`, textuelles `startzeit DESC` und das bereits reparierte Offset-ohne-Limit
(`LIMIT -1`) bleiben erhalten. Die API-Datumseingabe behält ihre bestehende
Formprüfung; keine neue Kalenderprüfung oder Paginationvalidierung.

## Bestandsdaten und Grenzen

- Übernachtarbeit bleibt vollständig dem Berliner **Starttag** zugeordnet;
  kein Aufteilen auf Tageskonten und keine historische Umsortierung.
- Offene Einträge bleiben filterbar; `endzeit`, Pause, Sollzeit und Gutschrift
  werden nicht neu berechnet. Berichte behalten ihren Ausschluss offener Zeiten
  aus abgeschlossenen Minutensummen.
- Leere/NULL- oder vom vorhandenen Parser nicht lesbare Startwerte liefern im
  Filter SQL-NULL und werden dort nicht als gültiger Tag erfunden. Ungefilterte
  API-Abfragen behalten solche rohen Zeilen. Dies ist keine Datenreparatur oder
  vollständige Invalid-Daten-Anzeige; der bestehende Berichtsparser wird nicht
  erweitert oder abgeschwächt. SQLite und der Bestandsparser akzeptieren nicht
  zwingend dieselben fehlerhaften Alttexte.
- Der Tagesausdruck ist eine PHP-SQLitefunktion, kein indizierter Datumsbereich.
  Count und Seite werten passende eigene Bestandszeilen aus; bei zwei Grenzen
  sind wiederholte Parseraufrufe möglich. Kein neuer Index, Schema-/Hostwechsel,
  Querybudget oder Nachweis für große Produktionsmengen.
- JWT/Login, Rollen-/Teamscopes, `GET /attendance`, Schreibpfade und bestehende
  UI bleiben unangetastet.

## Grober Nachweis, keine Integrationsabnahme

```sh
php tests/api_booking_day.php
```

Zwei grobe CLI-Gruppen, PHP **8.5.12**, SQLite **3.46.1**: 16 synthetische
Startwerte für Winter/Sommer, beide Zeitumstellungen, Monats-/Jahreswechsel,
UTC-/Offset-/offsetfreie Darstellung, zwei Prozesszeitzonen sowie Nacht-/offene
Einträge. Die zweite Gruppe prüft den unveränderten nativen Lesefunktionskörper
auf einer anonymen **SQLite-Memoryfixture**: eigene Person, ein-/beidseitige
inklusive Grenzen, Total/Seitenmetadaten, LIMIT/OFFSET und Offset-only sowie
unlesbare Daten ohne Mutation. Nur die Funktionsdefinition wird geladen;
`config.php`, Routing und Bearerprüfung werden nicht gebootet oder umgangen.

Die vorhandene Frameworkrehearsal wurde nur von der alten UTC-Erwartung auf
den 25. Oktober und Ausschluss am Vortag umgestellt; sie wurde für dieses Paket
**nicht ausgeführt**. Die inaktive Adaptergleichheit ist zusätzlich statisch
geprüft. Ergebnis-Commit, Patch-/Blobidentitäten und Lintprotokoll stehen im
separaten eingefrorenen Übergabenachweis. Keine HTTP-/Login-/Browserprüfung,
realen Daten, Serverstarts, CI-Auslösung, Veröffentlichung oder Produktivabnahme.
