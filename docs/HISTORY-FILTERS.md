# Native Historienfilter — erster Teil von CH-46 / #52

## Ergänzung: protokollierten Vorgang eingrenzen

Isolierter Quellschritt auf Main `5c91d62f842202512f31827668d8dce2b26a09ff`
(nach #261; ursprüngliche Featurequelle Main97b64e10 nach #246). Die bestehende native und vorbereitete Laravel-Historie erhalten
im vorhandenen Filterformular die optionale Auswahl „Vorgang“: Angelegt
(`INSERT`), Geändert (`UPDATE`), Gelöscht (`DELETE`) oder Übernahmestand
(`BASELINE`). Dies meint den gespeicherten Auditvorgang, nicht einen
Antragsstatus oder eine neue fachliche Ereigniszuordnung. Ohne Auswahl bleibt
die bisherige SQL-/Exportantwort unverändert; unbekannte oder nichtskalare
Auswahlwerte sind ungültige Filter, kein unbeschränkter Fallback.

Der gemeinsame kanonische Queryhelper ergänzt ausschließlich einen gebundenen
`AND a.operation=?`-Filter **vor** dem unveränderten `ORDER BY a.id DESC LIMIT 100`.
Native Karten, Bezüge und beide JSON-Auszüge sowie der Laravel-Reader verwenden
diese gleiche Abfrage. Person, Bereich, Datensatz, Antrag und Berliner
Änderungszeitraum verengen weiterhin gemeinsam den bisherigen Owner-/Admin-/
aktuellen Teamscope. Kein Filtern einer bereits geladenen Seite, kein neuer
Count, Cursorloop, Gesamtexport oder Index-/Schemawechsel.

Die neuesten/älteren Seiten, Bezugsschalter und ausgewählten/korrelierten
JSON-Links bewahren die Auswahl. Auch die exportierten Filter nennen sie;
`returned_rows` und der unveränderte Hinweis `older_matches_may_exist` beziehen
sich auf die tatsächlich gefilterte Seite. Genau 100 Treffer bedeuten weiterhin
nur „ältere Treffer möglich“, nicht deren bewiesene Existenz. Die Darstellung
und Auswahl verwandter Referenzen bleiben unverändert; sie können wie bisher
außerhalb der Ankerfilter liegen.

Ein grober erfundener SQLite-Memory-Journey mit 1500 Auditzeilen prüft alle vier
Vorgänge für eigene, aktuelle Team- und Adminsicht, vollständige absteigende
100er-Seiten samt Rest zwischen Nichttreffern/fremden Zeilen, kombinierte Filter,
native/Laravel-SQLgleichheit, Cursor-/Exportlinks, Exportanzahl/-hinweis und
ungültige Auswahl. Erlaubte JSON-Projektion und `query_only` bleiben erhalten.
PHP 8.5.12-Syntax, inerte Blade-Kompilierung und Diffcheck sind geprüft. Ein
anfänglicher falscher Restmengenwert im externen Prüfskript wurde korrigiert;
keine Produktänderung war dafür nötig. Keine neue kleinteilige Repo-Testsuite,
App-/Auth-/Session-/HTTP-/Browserausführung, tatsächlichen Konten/Daten/Secrets,
Writer-, Runtime-/Host-/Security-/Berechtigungsänderung oder Veröffentlichung.
Keine vollständige #52-/Produktivabnahme; geschützte Browser-/Downloadabnahme
bleibt gesondert offen.

`history.php` behält Navigation, Theme, Historienkarten, escaped Vorher-/Nachher-JSON und das absteigende 100er-Keyset-Paging bei. Die optionalen GET-Filter grenzen ausschließlich die bereits sichtbaren Auditzeilen ein:

- `user_id`: numerische Person-ID der Auditzeile, keine Suche in zusätzlichen Kontodaten.
- `from`, `to`: Datum der **protokollierten Änderung**, nicht Buchungstag. Beide lokalen Kalendertage in `Europe/Berlin` sind eingeschlossen; technisch gilt `[von 00:00, Tag nach bis 00:00)`, auch bei Zeitumstellungen.
- `entity`, `record_id`: vorhandener Bereich und dessen Datensatz-ID. Ohne Bereich können gleiche IDs verschiedener Bereiche Treffer liefern.
- `request_id`: ausschließlich `entity=time_adjustments` und die eigene `record_id` des protokollierten Korrekturantrags. Dies ist keine automatische Verknüpfung zur Buchung, Sitzung oder zum Stempelereignis.

Alle angegebenen Filter wirken gemeinsam mit `AND`. Der unveränderte bestehende Rollen-/Teamscope bleibt zusätzlich erforderlich. Es gibt keinen Lookup in aktuellen Buchungs-/Antragstabellen; protokollierte DELETE-/Stornozeilen bleiben daher grundsätzlich durchsuchbar. Das ist noch kein Nachweis vollständiger fachlicher Korrelation bei Storno.

Ungültige IDs, Bereiche, Datumswerte, Seitenmarken und umgekehrte Zeiträume ergeben HTTP 400 mit sichtbarem Fehler und **keine** Auditabfrage, statt auf eine unbeschränkte Liste zurückzufallen. „Filtern“ beginnt ohne alten Cursor bei den neuesten Treffern; „Ältere Einträge“ behält alle Filter. „Filter zurücksetzen“ entfernt sie ausdrücklich.

## Nachweis und Grenzen

Dieses Paket ändert weder Datenbankschema, Audittrigger, Rollen, Teams noch schreibende Funktionen. Kein Export, keine zusätzlichen Auditfelder, kein lesbarer Felddiff und kein neues UI/Framework sind enthalten. Issue #52 bleibt teilweise offen.

Der gezielte grobe Nachweis prüft nur den reinen Filter-/SQL-Helfer mit synthetischen Daten in SQLite `:memory:`: Standardliste, Person-/Teamscope, kombinierte Filter, exakte Antragssemantik, DELETE-Zeile und filtererhaltendes 100er-Paging. Hinzu kommen ungültige Eingaben und die lokalen Tagesgrenzen an einem 23-/25-Stunden-Tag sowie Syntaxprüfungen der beiden PHP-Dateien. Commit, Laufzeit und Ergebnis werden im separaten Übergabenachweis festgehalten. Dabei werden weder `config.php` noch echte Datenbanken, Zugangsdaten, HTTP, Browser, Server oder Deployment verwendet. Die Integration der nativen Seite, Darstellung im Browser und reale Stornoabläufe sind damit nicht getestet.

Nächster fachlicher Teil: lesbare Feldänderungen aus den **bereits sichtbaren** Vorher-/Nachher-Werten. Ereignis-/Sitzungs-/Buchungs-/Antragskorrelation und ein datensparsamer Export benötigen anschließend einen ausdrücklich abgegrenzten Vertrag unter demselben Scope; diese Filter behaupten ihn nicht.
