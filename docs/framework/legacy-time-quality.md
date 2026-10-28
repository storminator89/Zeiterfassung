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

**Additiver Quellstand auf Mainbasis
`25eb97783d148d067dfc488c7f7d6c8c40cbceb3`, Baum
`de4322d6ad20e10db8f6f44279f7682238701696`:** Berichtsformat 3 ergänzt nur
Arbeit/Arbeit-Paare für eindeutig lesbare, gültige abgeschlossene Zeilen desselben
Nutzers mit positiver Bruttodauer. Keine neue Buchungsregel, sondern reine
manuelle Klärungshinweise anhand der bestehenden halboffenen Intervallsemantik.
Abwesenheit/Abwesenheit, offene Überlappungen, Mapping, Reparatur und UI bleiben
offen. Registrierung, Authentifizierung und alle Laufzeitpfade bleiben unberührt.

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
Die Paarkategorien ergänzen `related_record_id`: Bei `work_absence_overlap`
ist `record_id` die Arbeitszeit, `related_record_id` die Abwesenheit; bei
`work_work_overlap` ist `record_id` die kleinere, `related_record_id` die größere
Datensatz-ID. Jeder Befund zählt ein Paar, nicht eine einmalige betroffene
Zeile; alte Kategorien behalten ihre Form. Die bisherigen Details stehen
weiterhin vor den neuen Arbeit/Arbeit-Details.
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
| `work_work_overlap` | Zwei eindeutig prüfbare gültige abgeschlossene Arbeitszeiten desselben Nutzers haben sich überschneidende positive Bruttointervalle; reine Randberührung zählt nicht. Keine automatische Kürzung, Pausenverteilung oder Aussage über doppelte Vergütung. |

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

`scope_status` bleibt immer `partial_diagnostic_only`. Format 3 setzt
`checks.overlap=partial_completed_work_work_and_work_whole_day_absence_only`
und behält die separate `work_whole_day_absence_overlap`-Angabe. Die neue Angabe
`work_work_overlap=checked_only_for_unambiguous_supported_completed_positive_intervals`
benennt ihre enge Prüfgrenze. Der bisherige kombinierte Schlüssel
`work_work_absence_absence_and_open_overlap` entfällt im versionierten Format 3;
stattdessen bleibt `absence_absence_and_open_overlap=not_checked` ausdrücklich
sichtbar. Es gibt im Repository keinen weiteren Leser dieses Diagnoseformats;
externe Verbraucher müssen die Berichtsversionsnummer und die neuen Kategorien
beachten.

Abwesenheit/Abwesenheit und offene Überschneidungen werden weiterhin **nicht
geprüft**. Mehrdeutige, nicht unterstützte oder ungültige Zeilen werden auch für
diesen Teil nicht normalisiert/verwendet. Weitere Session-/Event-/Pausen-/
Projektionsabgleiche sind `not_checked`, Mapping und Reparatur
`not_performed_requires_separate_confirmation`. Diese offenen Gatter bleiben
auch bei Exit 0 ausdrücklich sichtbar; unveränderte Exitcodes sind kein
Kompatibilitäts- oder Vollständigkeitsattest.

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

Für Arbeit/Arbeit werden die vorhandenen Zeilen nach ID gestreamt. Für jede
geeignete Arbeitszeit werden nur Zeilen mit demselben `user_id` und größerer
Datensatz-ID erneut gestreamt. Der Vergleich hält nur das jeweilige Zeilenpaar
und seine geprüften Instants, keine Kandidaten-/Paarliste. So erscheint jedes
geeignete Paar genau einmal. Die Bruttointervalle sind halboffen `[Start, Ende)`:
beide Dauern müssen positiv sein und es muss `StartA < EndeB` sowie
`StartB < EndeA` gelten. Identische positive Intervalle verschiedener IDs zählen;
Berührung bei Ende/Start und Null-Dauer nicht. Dieselbe Vergleichssemantik steht
bereits im Buchungs-Overlapguard. Pausenminuten erhalten keinen erfundenen
Zeitpunkt: Auch Netto0 bei positiver Bruttodauer ist ein prüfbares Intervall.
Der Befund ist lediglich ein Klärungshinweis, keine Neuberechnung von Summen.
Gültige offsetfreie eindeutige Werte behalten den separat gemeldeten
Berlin-Mappingprüfbedarf; kein historischer Instant wird bestätigt/erfunden.

Die neuen Details folgen stabil nach den bisherigen Kategorien in aufsteigender
kleinerer und dann größerer Paar-ID. Die Detailgrenze begrenzt weiterhin weder
Zeilen noch Laufzeit; Zähler laufen auch bei voller Ausgabe weiter. Erneutes
Lesen kann quadratische Laufzeit verursachen, auch bei wenigen Befunden. Keine
neue Performance-/Betriebsfreigabe, kein Index-/Schemawechsel oder versteckter
Aufruf des Offlinewerkzeugs.

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


### Additiver grober Quellnachweis für Format 3

Der bereits lokal geprüfte Zwei-Dateien-Delta wurde am 28.10.2026 auf diese
Mainbasis wiederverankert, statt eine parallele Implementierung zu erzeugen.
Die Format-3-Klasse sowie die wiederverwendeten `booking.php`-/`reporting.php`-
Definitionen sind bytegleich zum vorher geprüften Paket; die unten genannte
grobe Klassenprüfung wurde gegen die tatsächliche neue Main-Basisklasse erneut
ausgeführt. Die aktuelle Datei-/Preflight-Zulassung bleibt unverändert und
wird damit nicht erneut ausgeführt oder als abgenommen behauptet.

Auf der genannten Mainbasis wurde genau eine synthetische anonyme
`sqlite::memory:`-PDO mit 24 erfundenen Zeilen unter PHP **8.5.12** / SQLite
**3.46.1** geprüft. Die echte Klassendefinition meldet exakt 13 erwartete
Arbeit/Arbeit-Paare; die unveränderte Basisklasse meldet dafür keine Kategorie.
Enthalten sind Verschachtelung und identische Intervalle, Nachtarbeit,
explizite unterschiedliche Herbststundenoffsets/Z, halboffene Randberührung,
Null-Dauer, Netto0 bei positiver Bruttodauer und ein anderer Nutzer. Offene,
ungültige, mehrdeutige und nicht existierende Berliner Zeiten sowie beide
Abwesenheitskodierungen erzeugen keine neuen Arbeit/Arbeit-Paare.

Bestehende Kategorien/Detailreihenfolge und Datensatz-/Mappingzähler wurden
gegen die Basisklasse verglichen und bleiben gleich. Limit 2 behält vollständige
Kategorie-/Gesamtzähler und markiert die gekürzte Detailausgabe. Wiederholung
bleibt identisch; Query-only-Transaktion, Originalzeilen und nicht geöffnete
Sitzung bleiben erhalten. Der Bericht enthält keine synthetischen Notizen,
Originalzeiten, Abwesenheitstexte oder Nutzer-IDs. Notwendige PHP-Syntax und
`git diff --check` wurden separat geprüft.

Das ist nur ein begrenzter Klassen-/In-Memory-Nachweis, kein Aufruf der
Scanner-CLI und kein neues Testpaket/CI. Keine reale Datei/DB, Offline-/Betriebs-
oder Lastabnahme, kein Mapping, keine Reparatur, Registrierung, Authentifizierung,
HTTP-/Browser-/UI-/Runtime-/Deploymentaktion. #55 bleibt teilweise und offen;
kein Attest tatsächlicher Bestandsdaten wird aus dem synthetischen Befund
abgeleitet.
