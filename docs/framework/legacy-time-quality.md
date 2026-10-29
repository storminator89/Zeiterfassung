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

**Additiver Format-4-Quellschritt:** Auch zwei eindeutig lesbare, gültige
abgeschlossene Abwesenheiten desselben Nutzers werden nun paarweise geprüft.
`booking_span` liefert dafür unverändert den ganzen Berliner Kalendertag des
Startinstants. Unterschiedliche Abwesenheitsarten, Null-Gutschrift und zeitlich
getrennte Kompatibilitätsintervalle können deshalb denselben Tag belegen. Der
Befund fordert manuelle Klärung, ohne eine Zeile, Sollzeit oder Gutschrift zu
ändern. Offene Überschneidungen, Mapping, Reparatur und UI bleiben weiterhin offen.

**Additiver Format-5-Quellschritt:** Für eindeutig lesbare gültige Paare mit
mindestens einer Null-/Leer-Endmarkierung gibt es nun `possible_open_overlap`.
Das ist ausdrücklich ein möglicher Konflikt, kein Nachweis einer tatsächlichen
Überlappung: Ein offenes Arbeitsende bleibt unbekannt, ohne Jetzt-Ende, Dauer,
Gutschrift oder Obergrenzenwert im Bericht. Die bestehende ganztägige Semantik
einer Abwesenheit bleibt erhalten. Mapping, Reparatur und UI bleiben offen.

**Additive Originalintervall-Schritte:** Format6 ergänzt ausschließlich gespeicherte
Session-/Pausengrenzen; Format7 ergänzt abgeschlossene positive Originalpausenpaare
innerhalb derselben Session. Die genauen Grenzen und Nachweise stehen unten.
Kein vollständiger Event-/Projektionsabgleich, Mapping, Reparatur oder UI-Abschluss.

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
`work_work_overlap` und `absence_absence_overlap` ist `record_id` die kleinere, `related_record_id` die größere
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
| `absence_absence_overlap` | Zwei eindeutig prüfbare gültige abgeschlossene Abwesenheiten desselben Nutzers belegen nach der bestehenden Buchungssemantik denselben ganzen Berliner Kalendertag; auch verschiedene Arten oder getrennte Kompatibilitätsintervalle. Keine automatische Auswahl, Löschung oder Neuberechnung. |
| `possible_open_overlap` | Gleiches Konto, mindestens eine offene Zeile: Die vorhandenen Startwerte und bekannten End-/ganztägigen Abwesenheitsgrenzen schließen einen Konflikt nicht aus. Keine behauptete tatsächliche Arbeitsdauer oder Überlappung; auch ein späterer gültiger Eintrag kann deshalb einen Klärungshinweis ergeben. Kleinere ID in `record_id`, größere in `related_record_id`. |

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

`scope_status` bleibt immer `partial_diagnostic_only`. Das frühere Format 4 setzt
`checks.overlap=partial_completed_work_work_and_whole_day_absence_pairs_only`
und behält die separate `work_whole_day_absence_overlap`-Angabe. Die neue Angabe
`work_work_overlap=checked_only_for_unambiguous_supported_completed_positive_intervals`
benennt ihre enge Prüfgrenze. Der bisherige kombinierte Schlüssel
`work_work_absence_absence_and_open_overlap` entfiel im versionierten Format 3.
Format 4 ersetzt dessen verbleibenden Schlüssel `absence_absence_and_open_overlap`
durch `absence_absence_overlap=checked_only_for_unambiguous_supported_completed_whole_day_rows`
und das weiterhin ausdrückliche `open_overlap=not_checked`. Es gibt im Repository keinen weiteren Leser dieses Diagnoseformats;
externe Verbraucher müssen die Berichtsversionsnummer und die neuen Kategorien
beachten.

Format 5 setzt `checks.overlap=partial_completed_pairs_and_possible_open_pairs_only`
und `open_overlap=possible_only_for_unambiguous_supported_rows_no_guessed_end`.
Offene Paare werden nach allen bisherigen Details geordnet nach kleinerer und
größerer ID je einmal gestreamt; kein Vergleich verschiedener Konten, keine
Paarliste im Speicher. Bekannte End-/Start-Randberührung und abgeschlossene
Null-Dauer-Arbeit zählen nicht. Ohne bekannte Arbeitsendzeit wird lediglich
geprüft, ob eine Überlappung ausgeschlossen ist; es wird kein Ende ausgewählt.
Mehrdeutige, nicht unterstützte oder ungültige Zeilen werden auch für
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

Abwesenheit/Abwesenheit verwendet dieselbe beschränkte Paarfolge wie
Arbeit/Arbeit: gleicher Nutzer und größere zweite ID, stets nur ein Zeilenpaar,
ohne Kandidatenliste. Die unveränderte gemeinsame `booking_is_absence`-Erkennung
beachtet auch die drei exakten deutschen Legacy-Abwesenheitsmarker. Erst nach
enger Form-/DST- und gültiger Completed-Prüfung wird `booking_span` aufgerufen;
kein historischer Fold-Instant wird geraten. Der ganze lokale Tag kann bei DST
23 oder 25 Stunden umfassen. Benachbarte Tage berühren sich nur und sind kein
neuer Paarbefund. Die neuen Details folgen nach sämtlichen bisherigen Details,
geordnet nach kleinerer und dann größerer ID. Die begrenzte Detailausgabe stoppt
keine Zähler; quadratische Laufzeit bleibt möglich. Aus einem Paarbefund folgt
keine Behauptung einer Doppelvergütung oder eine Freigabe zur Datenreparatur.

## Grober synthetischer Nachweis, keine reale Datenabnahme

Format 5 wurde auf Mainbasis `182ec621327a120471d36ba369e147a6b1d481f6`
mit PHP 8.5.12 / SQLite 3.46.1 an 21 anonymen Memory-Zeilen geprüft:
fünf exakte mögliche Paare, Eigentümertrennung, Randberührung/Null-Dauer,
ganzer DST-Abwesenheitstag, ungültige/mehrdeutige Werte, unveränderte bisherigen
Befunde/Zähler und Daten/Schema sowie Detailbegrenzung. Nur die Klasse in einer
Query-only-Transaktion; kein neuer CLI-/Dateiadmissions-/UI-/Mappingnachweis.

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

### Additiver grober Quellnachweis für Format 4

Ein grober anonymer SQLite-Memory-Journey mit 19 erfundenen Zeilen unter PHP
8.5.12 liefert genau sieben zusätzliche Abwesenheitspaare. Verschiedene Arten,
deutsche Legacy-Erkennung, Null-Dauer, getrennte Kompatibilitätsintervalle,
explizite Offset-/Z-Starttage sowie 23-/25-Stunden-Tage sind enthalten. Andere
Nutzer, benachbarte Tage, Arbeitszeilen, offene, ungültige oder mehrdeutige Zeilen
erzeugen keine neuen Abwesenheitspaare. Alle früheren Befunde, Zähler und ihre
Detailreihenfolge stimmen mit der tatsächlich ausgeführten unveränderten
Basisklasse überein; Limit 2 begrenzt nur Details, nicht die sieben Paarzählungen.
Wiederholung, Query-only-Transaktion und Originalzeilen bleiben gleich. Keine
Notizen, Nutzer-IDs oder Zeittexte erscheinen im Bericht. Die vollständige
Klasseninverse stellt den alten Source exakt wieder her; gemeinsame Parser und
Buchungs-/Reporthelper sowie Scanner-CLI sind unverändert.

Dies ist kein Aufruf des Dateiscanners, kein tatsächlicher Bestandsscan und
keine Performance-, Mapping-, Reparatur-, UI-, Runtime- oder Betriebsabnahme.
Keine Datei-DB, Konten, Credentials, neue Instanz oder Server wurden erzeugt.
Offene Überlappungen und bestätigtes Mapping bleiben eigene Gates; #55 bleibt offen.


## Additiver Format-6-Schritt: Grenzen gespeicherter Originalintervalle

Quellbasis: Main `53ba78648e4b58a24fcc1202d19bb410215a6dd9`, Baum
`3b3b4d145b8b411e08c9771401eb9b1a153e6789`.
Nach allen bisherigen Zeilen-/Paarbefunden prüft dieselbe query-only-Transaktion
auch die **gespeicherten** UTC-Epochen in `work_sessions` und `break_intervals`:

- `original_session_invalid_epoch` / `original_break_invalid_epoch`: ein
  vorhandener Start oder ein nicht-NULL-Ende ist kein SQLite-INTEGER. Keine
  Umwandlung von REAL/Text, kein Zeitzonenparser und kein Null-zu-Nullzeit-Cast.
- `original_session_end_before_start` / `original_break_end_before_start`:
  eindeutiges gespeichertes Ende liegt vor dem Start. NULL bleibt offen.
- `original_break_outside_session`: bei gültigen, nicht umgekehrten Originalen
  liegt der Pausenstart vor dem Sessionstart oder eine bekannte Pausengrenze
  nach dem bekannten Sessionende. Gleichheit und Null-Dauer sind zulässig.
- `original_open_break_in_closed_session`: gültige gespeicherte Session ist
  geschlossen, die Pause besitzt aber kein gespeichertes Ende. Es wird weder
  das Sessionende noch die aktuelle Uhrzeit als Pausenende eingesetzt.

Jede ungültige Zeitspalte und jede übrige genannte Verletzung zählt einen
Befund; dieselbe Pause kann außerhalb liegen **und** noch offen sein. Ungültige
oder umgekehrte Zeitspannen werden nicht zur Grenzberechnung weiterverwendet.
Vollständige Zähler bleiben auch bei begrenzter Detailausgabe erhalten. Details
kommen nach allen alten Befunden, zuerst Sessions nach ID, dann Pausen nach ID.

**Versionierte neue Detailform:** Nur diese Kategorien verwenden
`{entity: work_sessions|break_intervals, entity_id: <exakte Dezimalzeichenfolge>,
category, column: started_at|ended_at|null}`. Eine Pausen-/Session-ID wird also
niemals als `record_id` ausgegeben. Alle bisherigen Kategorien behalten ihre
bisherigen Felder und Reihenfolge. Keine Konto-/Geräte-ID, Zeitwerte, Notizen,
Ereignisantworten, Zugangsdaten oder Rohzeilen werden ausgegeben; echte Berichte
bleiben trotzdem private Betriebsinformationen. Externe Leser müssen Format6
und die neue diskriminierte Form beachten. Exitcodes bleiben unverändert.

`checks.original_session_break_boundaries` nennt die enge Prüfung ausdrücklich.
Der bisherige kombinierte Schlüssel steht nun auf
`not_checked_beyond_original_interval_boundaries`: kein vollständiger Event-/
Session-/Pausen-/Projektionsabgleich, keine Prüfung tatsächlicher Pausenlage,
überlappender Pausen oder geschätzter offener Dauern. Die bestehende V2-Vorprüfung
bleibt allein für Beziehungen, Inhaberschaft und belegte Stornoprojektionen
zuständig. Insbesondere werden rechtmäßige genehmigte Projektionskorrekturen
nicht mit unveränderlichen Originalintervallen gleichgesetzt.

Nur zwei Quelldateien ändern sich; Scanner-CLI, Datei-/Offline-/Known-v2-Zulassung,
Parser, Writer, Schema und Runtime bleiben unverändert. Kein tatsächlicher
Bestandsscan, Mapping, Reparatur, Ereignisrekonstruktion, Konto-/Credentialzugriff,
Betriebs-/Browser-/Produktiv- oder Migrationsfreigabe. #55 bleibt offen.

Grober Quellnachweis für Format6: PHP8.5.12 / SQLite3.46.1, tatsächliche Klasse
und ursprüngliche Migratoren ausschließlich auf erfundenem anonymem Memory.
Zwölf erwartete neue Befunde über die sechs Kategorien; gültige offene,
gleiche/Null-Dauer- und signed64-Grenzwerte bleiben ohne erfundene Endzeit.
Die Quell-ID `9007199254740993` bleibt als Zeichenfolge exakt. Nach Entfernen
nur der neuen versionierten Felder/Kategorien ist der vollständige frühere
Bericht identisch; Limit2 begrenzt Details, nicht Zähler. Wiederholung,
query-only-Transaktion, Originalzeilen, SQLite-Änderungszähler, Sitzung und
Zeitzone bleiben erhalten. Keine Rohzeiten, Namen, Notizen oder Kontoidentitäten
im Bericht. Syntax und vollständige Klasseninverse separat geprüft.
Das ist kein tatsächlicher Aufruf des Dateiscanners oder seiner Preflight-/
Datei-/Sidecarzulassung, kein Bestandsscan, Lasttest oder Betriebsattest.

## Additiver Format-7-Schritt: abgeschlossene Originalpausenpaare

Quellbasis: Main `2ea273e6eddd294ff210a25b7bf65d535337aee5`, Baum
`35f86aaf85c32cc149cae6f2b017ff2ca7aad109`.
Die noch offene Prüfung überlappender gespeicherter Originalpausen wird eng
ergänzt: `original_break_overlap` meldet zwei **abgeschlossene positive**
Intervalle aus `break_intervals` mit derselben gespeicherten `session_id`.
Beide Starts und Enden müssen SQLite-INTEGER sein. Die halboffene Bedingung
`StartA < EndeB && StartB < EndeA` verwendet nur Vergleiche, keine potentiell
überlaufende Subtraktion. Verschachtelte und identische positive Intervalle
zählen; Randberührung und Null-Dauer nicht. Offene, umgekehrte oder ungültige
Pausen erhalten kein geschätztes Ende und nehmen an diesen Paaren nicht teil.

Der Vergleich bleibt von den bisherigen Eltern-Grenzbefunden getrennt: Zwei
prüfbare Pausen können sich auch dann überschneiden, wenn eine Sessiongrenze
ungültig ist oder die Pausen außerhalb der Session liegen. Deren bisherige
Befunde bleiben zusätzlich erhalten. Unterschiedliche Sessions werden niemals
kombiniert, auch nicht bei demselben Konto. Referenzen und Inhaberschaft bleiben
Aufgabe der unveränderten V2-Vorprüfung. Kein Vergleich mit genehmigten
Projektionskorrekturen, kein Abzug oder Neuverteilen von Pausenminuten und keine
Behauptung über eine falsche Vergütung.

Neue Paardetails folgen nach **allen** bisherigen Details, stabil geordnet nach
kleinerer und dann größerer Pausen-ID. Sie verwenden
`{entity: break_intervals, entity_id: <exakte Dezimalzeichenfolge>,
category: original_break_overlap, column: null,
related_entity_id: <exakte Dezimalzeichenfolge>}`. Die kleinere Quell-ID steht
in `entity_id`, die größere in `related_entity_id`; keine davon wird als
Projektions-`record_id` ausgegeben. Es erscheinen keine Session-/Kontoidentitäten,
Zeitwerte oder Rohzeilen. Alte Kategorien behalten Form und Reihenfolge.

`report_version=7` und `checks.original_break_overlap` benennen diese enge
Prüfung. `event_session_break_projection_reconciliation` bleibt ausdrücklich
`not_checked_beyond_original_interval_boundaries_and_completed_break_pairs`.
Offene Pausenüberschneidungen, vollständiger Event-/Session-/Projektionsabgleich,
bestätigtes Mapping, Reparatur und UI bleiben offen; `scope_status` und Exitcodes
ändern sich nicht. Externe Leser müssen die neue Version/Paardetailform beachten.

Die Abfragen streamen pro äußerer Pause nur größere IDs derselben Session.
Jedes geeignete Paar erscheint einmal; gehalten werden nur zwei Zeilen statt
einer Kandidaten-/Paarliste. Das Detail-Limit beschränkt weder Zähler noch
untersuchte Zeilen oder Laufzeit. Wiederholtes Lesen kann quadratisch teuer
sein; keine Performance-/Großbestandsabnahme oder Index-/Schemaänderung.

**Grober Quellnachweis:** PHP8.5.12 / SQLite3.46.1, tatsächliche Klasse und
ursprüngliche Quellmigratoren ausschließlich auf anonymem Memory. 22 erfundene
Pausenzeilen ergeben genau elf erwartete neue Paare, darunter Verschachtelung,
identische Intervalle, getrennte Session-/Eigentümergrenzen, separat ungültige
Eltern, signed64-Zeitgrenzen sowie exakte Quell-IDs `9007199254740993` und
`9223372036854775807`. Offene/ungültige/umgekehrte/Null-Dauer-Pausen und reine
Randberührung erzeugen keine neuen Paare. Nach Entfernen ausschließlich der
neuen versionierten Felder/Kategorie ist der bisherige vollständige Bericht
identisch; Limit2 behält alle Zähler. Wiederholung, query-only-Transaktion,
Originalzeilen/Schema, SQLite-Änderungszähler, Session und Zeitzone bleiben
unverändert. Keine Namen, Notizen, Rohzeiten oder Kontoidentitäten im Bericht.
Notwendige PHP-Syntax und vollständige Quellinverse wurden separat geprüft.

Nur Klasse und diese Dokumentation ändern sich. Kein tatsächlicher Aufruf der
Dateiscanner-CLI oder erneuter Datei-/Sidecar-/V2-Zulassungsnachweis, echter
Bestandsscan, Lasttest, neue Testsuite oder manuell gestartete CI. Keine App-/
Auth-/HTTP-/Browser-/Runtime-/Produktiv-/Migrationsabnahme, Konten, Credentials,
Schlüssel oder Daten-/Konfigurationsänderung. #55 bleibt teilweise und offen.

## Format8: abgeschlossene positive Originalsessions desselben Kontos

Auf Main `8032f3fc79f390a64d9196a2aaa588d029adc77b`, Baum
`01894602696fdb3e056c6fdefcdad24837d33120`, fehlte neben den bestehenden
Projektions-/Pausenprüfungen eine rein tatsächliche Originalpaarprüfung:
Zwei abgeschlossene positive `work_sessions` desselben Kontos konnten sich
überschneiden, während ihre gegenwärtigen Projektionen getrennt lagen. Die
unveränderte bekannte V2-Vorprüfung lässt solche gültig referenzierten Zeilen
zu, weil sie Beziehungen/Inhaberschaft, nicht diese Zeitüberschneidung prüft.

Format8 ergänzt `original_session_overlap`. Nur zwei gespeicherte ganzzahlige
Start-/Endepochen mit **positivem abgeschlossenem** Intervall und demselben
internen `user_id` nehmen teil. Halboffene Grenzen: Randberührung zählt nicht.
NULL-Ende, Null-Dauer, umgekehrte oder nichtganzzahlige Werte werden nicht als
Paar verwendet. Keine Subtraktion, kein Jetzt-Ende, kein Zeitparser oder neue
Zeitkonversion; Vergleich bleibt auch an signed64-Grenzen ganzzahlig.

Jedes Paar erscheint einmal, geordnet nach kleinerer/größerer Original-ID:
`entity=work_sessions`, `entity_id` und `related_entity_id` als exakte dezimale
Strings, `column=null`. Kein `record_id`, der eine effektive Buchung vortäuschen
würde; kein Konto, Originalzeitwert, Benutzername, Notiz, Eventinhalt oder Pfad
wird zusätzlich ausgegeben. Die bisherigen Befunde bleiben exakt davor stehen.
Ein Paar zählt einen Befund, nicht eine einmalig betroffene Session.

Das sind **Originalintervallfakten**, keine Behauptung falscher heutiger Summen,
Doppelvergütung oder ungültiger inzwischen freigegebener Korrekturen. Projektionen
können sich nach einer Korrektur bewusst von Originalen unterscheiden. Es gibt
keinen neuen Original-/Projektionsabgleich, keine automatische Auswahl,
Rekonstruktion, Reparatur, Migration oder zusätzliche Sperre von Runtimeberichten.
Kein Session-/Buchungswriter oder aktueller Stempelstatus wird geändert.

`checks.original_session_overlap` benennt den neuen engen Prüfumfang;
`event_session_break_projection_reconciliation` bleibt ausdrücklich unvollständig
und verweist nun auf abgeschlossene Originalpaare, nicht auf einen verbundenen
Event-/Session-/Pausen-/Projektionsablauf. Alle bisherigen Checks/Kategorien
bleiben erhalten. Die unveränderte `needs_review`-Ableitung berücksichtigt den
neuen Zähler; eine zuvor in diesem begrenzten Bericht unauffällige Quelle kann
somit ausdrücklich zur Prüfung stehen. Exitcodes und CLI-/Datei-/Snapshot-/
V2-Zulassung bleiben unverändert; kein Format8-Ergebnis ist eine Gesamtfreigabe.

Für jede geeignete Session werden höher-IDige Sessions desselben Kontos gestreamt.
Es entsteht keine Paarliste im Speicher. `--limit` begrenzt weiterhin nur die
Detailausgabe auf1–1000; Zähler laufen vollständig weiter. Es gibt kein neues
Laufzeit-/Datenmengenlimit und keinen Leistungsnachweis für große Bestände.
Keine Index-/Schema-/Dependency-/Test-/CI-Änderung. Fremde externe Leser müssen
wie bisher die Berichtsversionsnummer beachten; kein Repositoryleser konsumiert
dieses neue Paarformat als Buchungs- oder Freigabeautorität.

### Grober Format8-Quellnachweis

PHP8.5.12 / SQLite3.46.1 / Laravel13.38.0: tatsächliche alte/neue Klasse und echte
Sourcefixture/Migratoren auf anonymem query-only Memory; tatsächliche unveränderte
V2Preflight-Komponente besteht auf beiden synthetischen Snapshots.24 erfundene
Sessions,4 Pausen,29 Projektionen:12 neue Originalsessionpaare;13 alte Befunde
bleiben exakt samt Reihenfolge, Kategorien und Zählern erhalten. Verschachtelte,
verkettete und identische Intervalle, Randberührung/Null/offen/umgekehrt/REAL/TEXT,
getrennte Konten, signed64-Extrema, IDs über 2^53 sowie PHP_INT_MAX und unabhängig
geänderte/ungültige Projektionen geprüft. Limit2 behält volle Zähler; Wiederholung,
Datensparsamkeit, Schema/Zeilen/Änderungszähler, query-only/Transaktion und native
Session/Zeitzone unverändert. Ein eigener vorher befund- und offsetfrei sauberer
synthetischer Bericht ohne Originalpaarcheck wird ausschließlich durch ein
bekanntes Originalpaar zu `needs_review=true` — kein vollständiges Sauberkeitsattest.

Die erste Vorprüfungs-Harnessfassung nutzte generisches `new PDO`; sie musste
für die bestehende Sourcefixture ausschließlich auf Laravels unveränderten
SQLiteConnector und dessen konkreten PDO-SQLite-Typ umgestellt werden. Kein
Produktcode/Zulassungsgate wurde hierfür abgeschwächt. Keine neue Testsuite oder
manuelle CI; Syntax, vollständige Patch-/Baumerhaltung, inverse/replay, Kaltbundle
und unabhängiger Quellreview separat. Keine echte Datei-/CLI-/OfflineFiles-/
Sidecar-/Betriebs-/V5-/App-/Auth-/HTTP-/Browser-/Konkurrenz-/Last-/Produktivabnahme,
Bestandsprüfung, vollständiger Eventabgleich, Mapping oder Reparatur. Keine realen
Konten, Credentials/Keys, Datei-DB, Konfiguration, Hosts oder Deployment. #55
bleibt ausdrücklich teilweise/offen; gehaltene/abgelehnte Pakete bleiben unberührt.
