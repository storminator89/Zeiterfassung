# CH-49 / #55: begrenzte, ausschließlich lesende Bestandsdiagnose

Erster Diagnosebaustein auf Main
`15d8ea5ffacc38596397202fd85c77a51cdc0f37`, Quellbaum
`12f379a524314c463beae19aa8fbb9c078fce0ff`. **Keine vollständige #55-Abnahme**:
Allgemeine Überlappungsprüfung, bestätigtes Mapping und Einbindung in die bestehende UI
bleiben offen. Kein neuer Runtimeparser, kein Framework-/Auth-/HTTP-Cutover.

**Additiver Quellstand auf Main `8139842bab8fc64f93313e7b1848a16b96b496c2`:**
Berichtsformat 2 ergänzt ausschließlich die inzwischen vorhandene gemeinsame
`report_absence_conflicts`-Projektion für eindeutig lesbare, gültige abgeschlossene
Arbeitszeiten gegen ganztägige Abwesenheiten desselben Nutzers. Allgemeine
Überlappungsprüfung, Mapping/UI und vollständige #55-Abnahme bleiben offen.

## Expliziter Aufruf, keine Dateisuche

Installierte gelockte Frameworkabhängigkeiten und PHP 8.5 vorausgesetzt:

```sh
php framework/tools/inspect-legacy-times.php \
  --target=/private/offline/existing.sqlite --offline --limit=100
```

Der Pfad ist nur ein Platzhalter, kein Produktivziel. `--target` und `--offline`
sind Pflicht; kein `APP_DATA_DIR`-/`CHRONO_DB_PATH`-Fallback, keine automatische
Dateierstellung. Der Operator muss alle Writer stoppen und einen konsistenten
privaten Offlinebestand bereitstellen. Das Flag beweist dies nicht selbst.

Der CLI lädt ausschließlich Composer-/Klassendefinitionen, die vorhandene
Memory-Referenzfixture und Legacy-Funktionsdefinitionen; **kein** `artisan`,
`bootstrap/app.php`, `.env`, `config.php`, `security.php`, Provider, Login oder
Server. Die vorhandenen Abhängigkeiten werden nicht erweitert.

## Datei- und Schreibgrenze

Unverändert wiederverwendet werden die Pfad-/Snapshotregeln aus
[`OfflineFiles`](../../framework/app/LegacyDatabase/OfflineFiles.php) und die
[bekannte V2-Vorprüfung](offline-v2-adoption.md): existierende kanonische absolute
Privatdatei ohne Symlink-/Hardlinkalias außerhalb beider Webroots, Modus `0400`
oder `0600`, unmittelbares Verzeichnis `0700`, kalter Rollbackjournalheader,
keine WAL-/SHM-/Journal-Sidecars. Keine Rechteänderung, Recovery oder Checkpoints.

Öffnen über Laravels vorhandenen SQLite-Connector mit
`Pdo\Sqlite::OPEN_READONLY`, ohne CREATE; zusätzlich verbindungslokal
`query_only=ON`. Die Diagnose läuft in einer lesenden Transaktion. Die
Dateiidentität und SHA-256 müssen nach deren Schließen dem vorherigen Snapshot
entsprechen; sonst Stop statt Bericht. Ein konkurrierender privilegierter
Dateieigentümer ist damit nicht adversarial isoliert.

`V2Preflight` prüft bereits Schema/Versionen, Integrität, deklarierte und logische
Beziehungen sowie Projektions-/Eventinhaberschaft; diese Regeln werden **nicht**
nachgebaut. Die passende Referenz entsteht mit der vorhandenen Quellfixture und
den echten Migratoren ausschließlich im anonymen Speicher. Alle Referenz-DDL
und die Standard-Laravel-Referenzledger bleiben dort. Auf dem Dateiziel gibt es
keinen Writer, keine Migration/DDL, keine FK-/Index-/Journalmodusänderung,
Reparatur, Eventrekonstruktion, Ledgerübernahme oder Daten-/Schlüsselkopie.

## Tatsächlich geprüfte Daten und Kategorien

Für die bisherigen Diagnosen werden `zeiterfassung.id/startzeit/endzeit/pause`
gelesen. Der neue Teil liest zusätzlich `user_id/absence_kind/beschreibung`
ausschließlich intern für die unveränderte gemeinsame Eigentümer-/Abwesenheits-
klassifikation, einschließlich deutscher Legacy-Abwesenheitsmarker.
Kein Export von Namen, Notizen, Originalzeittexten, Hashes, Zugangsdaten
oder Pfaden. Befunde enthalten nur Datensatz-ID, Kategorie und ggf. Spaltenname.
Nur `work_absence_overlap` ergänzt `related_record_id`: `record_id` ist die
Arbeitszeit, `related_record_id` die Abwesenheit. Jeder Befund zählt ein Paar,
nicht eine einmalige betroffene Zeile; alte Kategorien behalten ihre Form.
Die IDs sind trotzdem betriebliche Informationen: reale Berichte privat halten,
nicht als GitHub-Nachweis hochladen.

| Kategorie | Bedeutung |
| --- | --- |
| `unsupported_timestamp_shape` | Nicht im bewusst engen Diagnoseformat; nicht automatisch ein Fehler des unveränderten Runtimeparsers. |
| `invalid_timestamp` | Ungültiges Kalenderdatum, Uhrzeit oder numerischer Offset im erkannten Format. |
| `ambiguous_berlin_local_time` | Offsetfreie Berlin-Wanduhrzeit passt zu mehreren Instants, z. B. Herbst-Doppelstunde. Keiner wird ausgewählt. |
| `nonexistent_berlin_local_time` | Offsetfreie Berlin-Wanduhrzeit existiert beim Frühlingssprung nicht; keine Normalisierung auf die nächste Stunde. |
| `end_before_start` | Eindeutig prüfbare geschlossene Zeile endet vor ihrem Beginn. |
| `invalid_pause` | Keine nichtnegative ganzzahlige Minutenzahl. |
| `pause_exceeds_gross` | Tatsächliche Pause überschreitet die eindeutig prüfbare Bruttozeit. |
| `multiple_open_records` | Mehrere Null-/Leer-Endmarker desselben Nutzers; alle beteiligten IDs werden gemeldet. |
| `work_absence_overlap` | Eine eindeutig prüfbare abgeschlossene Arbeitszeit überschneidet den ganzen Berliner Kalendertag einer gültigen abgeschlossenen Abwesenheit desselben Nutzers; Einzelwerte bleiben unverändert. |

Diagnoseformat: `YYYY-MM-DD HH:MM:SS` oder `YYYY-MM-DDTHH:MM:SS`, optional
`Z` oder numerischer `±HH:MM`-Offset. Freitext, relative Angaben, Zeitzonennamen,
Sekundenbruchteile und sonstige Formen werden nicht geraten. Diese engere
**Diagnosegrenze ändert nicht** `booking_time`/`report_stored_epoch` und erklärt
deren sonstige akzeptierte Formen nicht nachträglich für ungültig.

Bei offsetfreien, kalendarisch gültigen Werten werden mögliche Berlin-Instants
nur intern gezählt; keine gewählte Konversion wird ausgegeben oder gespeichert.
Explizite Offsets/Z identifizieren dagegen bereits einen Instant und müssen
nicht einem Berliner Offset entsprechen. Offsetfreie format-erkannte Werte
werden zusätzlich gezählt und benötigen eine gesonderte fachliche Bestätigung
der bisherigen Berlin-Annahme. Bei ungültigen Werten genügt eine solche
Bestätigung natürlich nicht als Reparatur. Die historische Herbststunde kann
ohne unabhängige Evidenz nicht rekonstruiert werden.

Für unterstützte **eindeutige** Zeitwerte wird
`report_record_calculation` wiederverwendet (einschließlich bestehender
Null-/Leer-Endmarker und Null-Dauer-Semantik). Bei Formfehlern, Lücken oder
Doppelstunden wird keine Dauer berechnet. Es werden keine neuen Events oder
Historienbehauptungen erzeugt.

## Ergebnis und stabile Exitcodes

- **0:** begrenzter Durchlauf abgeschlossen, keine Befunde und keine
  offsetfreien Werte, die Mappingbestätigung benötigen. **Kein vollständiges
  „sauber“ und keine Migrationsfreigabe.**
- **2:** Durchlauf abgeschlossen, Befunde oder Mappingprüfbedarf vorhanden.
- **1:** gestoppt, kein vollständiger Diagnosebericht; nur redigierte Phase.
- **64:** fehlende/ungültige/mehrfach angegebene Argumente.

`scope_status` bleibt immer `partial_diagnostic_only`.
`checks.overlap=partial_work_whole_day_absence_only` und die separate
`work_whole_day_absence_overlap`-Angabe begrenzen die neue Teilprüfung.
Arbeitszeit/Arbeitszeit-, Abwesenheit/Abwesenheit- und offene Überschneidungen
werden ausdrücklich **nicht geprüft**. Mehrdeutige, nicht unterstützte oder
ungültige Zeilen werden auch für diesen Teil nicht normalisiert/verwendet.
Weitere
Session-/Event-/Pausen-/Projektionsabgleiche sind `not_checked`, Mapping und
Reparatur `not_performed_requires_separate_confirmation`. Diese offenen Gatter
bleiben auch bei Exit 0 ausdrücklich sichtbar.

`--limit` begrenzt die ausgegebenen Befunddetails auf 1–1000 (Default 100).
Zeilen werden gestreamt; Kategorie-/Gesamtzähler laufen weiter und
`findings_truncated` zeigt abgeschnittene Details an. Das ist keine Begrenzung
der untersuchten Zeilenzahl oder Laufzeit des Offlinechecks.

Für jede geeignete Abwesenheit werden alle geeigneten Arbeitszeilen desselben
Nutzers erneut gestreamt, in Batches von höchstens 128. Jeder Batch wird mit
**dieser einen Abwesenheit** an den unveränderten gemeinsamen Helfer übergeben:
höchstens 129 Eingabezeilen und 128 Ergebnispaare gleichzeitig. Jedes geeignete
Arbeitszeit/Abwesenheit-Paar erscheint genau einmal, auch über Batchgrenzen
hinweg; verschiedene Nutzer werden nicht kombiniert. Ganzer lokaler Tag,
Nachtschicht, DST und halboffene Randberührung bleiben allein Helfersemantik.
Dies begrenzt Zeilen-/Paarlisten im Anwendungsspeicher, nicht die Bytegröße eines
einzelnen vorhandenen Textwerts oder den internen SQLite-Speicher. Wiederholte
Leseabfragen je Abwesenheit können bei großen Beständen teuer sein; keine
Performance-, Laufzeit- oder vollständige Skalierungsabnahme wird behauptet.

## Grober synthetischer Nachweis, keine reale Datenabnahme

PHP **8.5.12**, SQLite **3.46.1**, Zeitzonendaten **2026.5**; ein einziger privater
synthetischer kalter Dateibestand, `0400`, aus vorhandener Quellfixture mit
insgesamt **13 Buchungszeilen**. Keine reale DB, Credentials, Appkonfiguration,
Browser-/HTTP-/Server-/Deployaktion oder neue GitHub-CI gestartet.

- **9 Befunde:** je eine Doppelstunde, Frühlingslücke, ungültiger Kalendertag,
  nicht unterstützte relative Form, rückläufige Zeit, überschrittene Pause,
  negative Pause und zwei zusammengehörige offene Zeilen.
- Eindeutige explizite Offsets in der Herbststunde und eine echte Nachtschicht
  wurden nicht als DST-/Reihenfolgefehler gemeldet. Keine Variante der
  mehrdeutigen Zeit ausgegeben oder gespeichert.
- Limit 2 gibt zwei Details aus, behält alle neun Zählungen und markiert die
  Kürzung. Synthetische Notiz nicht im Bericht.
- Dateibytes/Identität bleiben zum ursprünglichen Fixture-Snapshot gleich;
  kein Sidecar. Ein ausschließlich synthetischer DDL-Schreibversuch auf dem
  wiederverwendeten Read-only-Handle wurde verweigert. Ein fehlendes Ziel wurde
  nicht angelegt; fehlender Input endet mit 64.
- PHP-Syntax und `git diff --check` separat geprüft. Kein Volltest, keine
  Produktiv-/Betriebsabnahme, keine Aussage über echte Unternehmensbestände.

Originale bleiben unverändert. Ein künftiges bestätigtes Mapping muss getrennt
die betroffenen Originalwerte/Evidenz reviewbar machen und ausdrücklich
autorisiert werden; diese Diagnose nimmt kein Mapping und keine Reparatur vor.

### Additiver grober Quellnachweis für Format 2

Auf der genannten unveränderlichen Mainbasis wurde genau eine synthetische
anonyme In-Memory-PDO-Fixture unter PHP **8.5.12** / SQLite **3.46.1** geprüft:
139 erfundene Zeilen ergeben genau 262 erwartete Arbeitszeit/Abwesenheit-Paare.
Beide Abwesenheitskodierungen, mehr als 128 Arbeitszeilen pro Batchfolge,
Nachtschicht, explizite Herbststundenoffsets, reine Randberührung und ein anderer
Nutzer sind enthalten. Mehrdeutige/ungültige/offene Zeilen werden nicht in die
neuen Paare aufgenommen. Die bisherigen Diagnosen und Mappingzähler wurden
gegen die unveränderte Basisklasse verglichen und bleiben gleich.

Limit 2 begrenzt Details bei weiterhin vollständigen Zählern; Bericht enthält
keine synthetischen Notizen, Originalzeiten, Abwesenheitstexte oder Nutzer-IDs.
Query-only-Transaktion und sämtliche Fixturezeilen bleiben unverändert.
Syntax und `git diff --check` bestehen. Dies ist **kein tatsächlicher Aufruf des
Dateiscanners**, keine erneute Datei-/Sidecar-/Preflight-Abnahme, kein echter
Bestandsscan, kein Großbestandsbenchmark und keine HTTP-/Browser-/Betriebsabnahme.
CLI und Datei-/Schreibzulassung sind in diesem Delta bytegleich erhalten.
#55 bleibt ausdrücklich offen; keine Daten-, Mapping-, Reparatur-, Aktivierungs-
oder Rolloutfreigabe wird hieraus abgeleitet.
