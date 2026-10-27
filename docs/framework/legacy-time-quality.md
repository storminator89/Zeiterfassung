# CH-49 / #55: begrenzte, ausschließlich lesende Bestandsdiagnose

Erster Diagnosebaustein auf Main
`15d8ea5ffacc38596397202fd85c77a51cdc0f37`, Quellbaum
`12f379a524314c463beae19aa8fbb9c078fce0ff`. **Keine vollständige #55-Abnahme**:
Überlappungsprüfung, bestätigtes Mapping und Einbindung in die bestehende UI
bleiben offen. Kein neuer Runtimeparser, kein Framework-/Auth-/HTTP-Cutover.

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

Nur `zeiterfassung.id/startzeit/endzeit/pause` werden für diesen Diagnosebaustein
gelesen. Kein Export von Namen, Notizen, Originalzeittexten, Hashes, Zugangsdaten
oder Pfaden. Befunde enthalten nur Datensatz-ID, Kategorie und ggf. Spaltenname.
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
`checks.overlap=deferred_pending_shared_booking_conflict_helper`: dieses Paket
dupliziert den noch nicht gemergten #35-Konflikthelfer nicht. Weitere
Session-/Event-/Pausen-/Projektionsabgleiche sind `not_checked`, Mapping und
Reparatur `not_performed_requires_separate_confirmation`. Diese offenen Gatter
bleiben auch bei Exit 0 ausdrücklich sichtbar.

`--limit` begrenzt die ausgegebenen Befunddetails auf 1–1000 (Default 100).
Zeilen werden gestreamt; Kategorie-/Gesamtzähler laufen weiter und
`findings_truncated` zeigt abgeschnittene Details an. Das ist keine Begrenzung
der untersuchten Zeilenzahl oder Laufzeit des Offlinechecks.

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
