# Native Historienfilter — erster Teil von CH-46 / #52

## Korrektur: neueste Treffer ohne künstliche ID-Obergrenze (#79)

Auf Main `f94eb4d339b8b360c6a01b942f315762399ab871` / Tree
`b00eb2e78afe34d5038a28a4dcfb8f80d4035bf8` setzte der gemeinsame Queryhelper
auch ohne Seitenmarke `a.id < PHP_INT_MAX`. Dadurch fehlte eine tatsächlich
sichtbare gültige Auditzeile mit der maximalen SQLite-/PHP-Integer-ID bereits
auf der neuesten Seite und in deren ausgewählten/korrelierten JSON-Auszügen.
Ein erfundener Memory-Datensatz reproduziert diese Auslassung am alten Helper.
Dies behauptet weder einen betroffenen realen Bestand noch eine neue Schreib-
oder Autoinkrementfähigkeit nach ausgeschöpftem ID-Raum.

Nur bei **angegebener** gültiger Seitenmarke wird jetzt `a.id < before`
gebunden. Ohne oder mit bisher zulässiger leerer Marke werden die neuesten
Treffer ohne künstliche ID-Obergrenze gelesen. Explizit `before=PHP_INT_MAX`
bleibt streng exklusiv und liefert dieselben SQL-/Parameterbytes wie vorher.
Parenthesierter eigener/Admin-/aktueller Direktteamscope, alle weiteren
konjunktiven Filter, Reihenfolge und `LIMIT 100` bleiben unverändert. Kein
addiertes ID-Sentinel, Überlauf, Float oder JavaScript-Number wird benötigt.
Native Karten, Bezüge und beide Auszüge sowie Laravel `HistoryQuery` verwenden
bereits denselben Helper; es gibt keinen zweiten Abfrage-/Berechtigungsweg.
Der alte allgemeine Kompatibilitätsreader `history(int $before = PHP_INT_MAX)`
bleibt unverändert; die aktuellen HTTP-Seiten verwenden `HistoryQuery`.

Grober Nachweis mit PHP8.5.12/SQLite3.46.1/Laravel13.38.0, anonymem Memoryfixture
und unverändertem Composer-Lock: tatsächlicher Shared-Query und Laravel-Reader,
aktueller Controller, kompilierter bestehender Historyinhalt mit inertem Layout,
ausgewählte und korrelierte JSON-Projektionen.40 Vorher-/Nachhervergleiche ohne
Maximalzeile über Rollen/Filter/Seitenmarken; Maximalzeile nur für Eigentümer,
Direktvorgesetzten und Admin, exklusive explizite Marke, leere Marke, große
Nachbar-IDs, gemeinsame Filter, vollständige vierseitige Keysetfolge ohne
Duplikate und unveränderte Fehlerverweigerung. Quell-/Schema-/Auditbestand,
Änderungszähler, `query_only`, native Session-/Zeitzonenglobals bleiben erhalten.
Der erste externe Korrelationsprüfschritt hatte keinen caller-eigenen Snapshot;
der bestehende Schutz verweigerte korrekt. Der korrigierte Prüfablauf verwendet
unverändert den echten Snapshotreader, kein Produktgate wurde gelockert.

Nur Helper und dieses Dokument ändern sich. Keine Writer-/Audit-/Schema-/Index-,
Rollen-/Auth-/CSRF-/Routen-/Runtime-/Host-/Securityänderung, kein neuer Testbestand
oder manuell ausgelöster CI-Lauf. RequestGuard ist ein Identitätsdouble; kein
Native-Bootstrap, voller App-/Login-/HTTP-/Browser-/Layout-/Produktions-/Konkurrenz-
oder Performancenachweis, keine echten Daten/Konten/Zugangsdaten/Schlüssel oder
Dateidatenbank. Die bisherigen JSON-ID-Wiretypen und Exportgrenzen bleiben
unverändert. Geschützte Browserabnahme übernimmt weiterhin der Nutzer; keine
vollständige #79-Abnahme oder Wiedereröffnung/Neuerledigung von #52 behauptet.

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
