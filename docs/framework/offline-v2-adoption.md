# FW-03: explizite Offline-Übernahme eines bekannten V2-Dateibestands

Dieses Inkrement ergänzt den [Memory-Rehearsalkern](adoption-rehearsal.md) um einen tatsächlich nutzbaren CLI-Pfad für **vorhandene bekannte App-v2-Datenbanken**. Produktcode ist nicht auf einen Entwicklungsordner beschränkt; die hier durchgeführte Abnahme verwendet dagegen **ausschließlich synthetische, gestoppte private Kopien**. Keine reale Datenbank, gemeinsame lokale Fixture, Schlüssel, Accounts oder laufende App wurden benutzt.

Entwicklungs-/Integrationsbasis: Main `6544a97fbad531f5f87fff349ce811d5d2dcbe28`, Baum `ca4bf949004d3bc30ac375b336d3f23585fbde88` (bereits gemergtes erstes FW-03-Inkrement).

Keine Neuinstallation/vor-v2-Upgrades, Datenbereinigung, produktive Auth-/DB-/Routenumstellung oder vollständige #74/#75-Abnahme. `chrono` als künftige Anwendungsverbindung bleibt unkonfiguriert; weder dieser Befehl noch der Memorytreiber sind ein Auth-/HTTP-DB-Fallback.

## Voraussetzungen des Operators

1. **Alle Web-/API-/Terminalwriter stoppen und Wartungsfenster halten.** `--offline` bestätigt diese Voraussetzung; der Befehl kann nicht beweisen, dass jedes externe Gerät/jeder Prozess tatsächlich gestoppt wurde. Ein SQLite-Immediate-Lock ersetzt keinen Betriebs-/Cutoverplan.
2. Vollständige konsistente private Sicherung nach #65 bereitstellen, einschließlich benötigter Schlüssel/Code-/Konfigversionen. Der Befehl prüft **nur den explizit angegebenen DB-Backupbestand**; er behauptet keine Sicherung von `.key`-Dateien und kopiert/erzeugt keine Credentials.
3. Ziel und DB-Backup sind existierende eigenständige reguläre Dateien außerhalb des Legacyprojekt-Webroots und `framework/public/`: kanonische absolute Pfade, keine Symlinkkomponente/Hardlinkaliase, private Datei `0400` oder `0600`, unmittelbar privates Verzeichnis `0700`. Schreibziel muss ausdrücklich Owner-Write erlauben; Rechte werden niemals automatisch geändert.
4. Zunächst wird nur ein **kalter Rollback-Journalbestand** unterstützt: SQLiteheader Read-/Writeversion 1, keine `-wal`, `-shm` oder `-journal`-Datei. WAL/Sidecars werden **vor PDO-Open** abgelehnt. Nicht löschen, Header bearbeiten oder ungeprüft checkpointen: den konsistenten Offlinebestand nach betrieblichem Restore-/Backupverfahren vorbereiten. Dieser Befehl übernimmt keinen WAL-/Hot-Journal-Recoveryprozess.
5. Installierte gelockte Foundationabhängigkeiten/PHP 8.5 und der zur App-v2-Struktur passende Code. Unbekanntes Schema, frühere Versionen, zusätzliche Views/temporäre Schemas und fremde Frameworkledger werden abgelehnt, nicht repariert.

Vertrauensgrenze sind vom Operator kontrollierte gestoppte private Dateien. Identitäts-/Hashnachprüfungen begrenzen versehentliche Änderungen, sind **keine adversariale TOCTOU-Isolation** gegen einen absichtlich konkurrierenden privilegierten Dateieigentümer. Solche Betriebs-/Angreifermodelle werden hier nicht als abgenommen behauptet.

## Befehle

Pfade sind Platzhalter, keine zu verwendenden Produktivziele. Es gibt **keinen** `APP_DATA_DIR`-/`CHRONO_DB_PATH`-Fallback und keinen automatischen Dateierstellungsmodus.

```sh
cd framework

# Ausschließlich lesende Vorprüfung, keine Writerverbindung.
php artisan chrono:legacy-v2 inspect --target=/private/offline/current.sqlite

# Nur nach erfüllten Voraussetzungen; Backup ausdrücklich separat angeben.
php artisan chrono:legacy-v2 adopt \
  --target=/private/offline/current.sqlite \
  --backup=/private/backup/before-adoption.sqlite --offline

# Begrenzte Rücknahme ausschließlich der eigenen additiven Trackingtabelle.
php artisan chrono:legacy-v2 rollback-ledger \
  --target=/private/offline/current.sqlite \
  --backup=/private/backup/before-adoption.sqlite --offline
```

Ohne `--target` wird gestoppt; Änderungen benötigen zusätzlich `--backup` und `--offline`. Ergebnis sind Status, Schemasignatur und aggregierte Zählwerte, keine Personenzeilen/Pfade/Passworthashes/SQLbindings. Solche Betriebsnachweise bleiben privat; keine realen Inhalte nach GitHub kopieren. Native PDO-/Queryfehlermeldungen mit möglichen DSNs/SQL werden nicht ausgegeben.

## Ablauf und Änderungsgrenze

### Vor jeder Writerverbindung

`OfflineFiles` prüft Pfade, private Modi, Header und Sidecars, Dateidentität und SHA-256. Der Standard-Laravel-`SQLiteConnector` öffnet mit **`Pdo\Sqlite::ATTR_OPEN_FLAGS=OPEN_READONLY`** ohne CREATE; `query_only=ON` ist zusätzliche Absicherung, kein Ersatz für den echten Read-only-Open. Kein `immutable=1`-Bypass und keine selbstgeschriebene DSN-/Treiberinfrastruktur.

Auf einer lesenden Transaktion werden `quick_check`, sämtliche erlaubten Haupttabellen-/Spalten-/FK-/Index-/Triggerdefinitionen und Appmarker `[1,2]` mit der real quellerzeugten Referenz verglichen. Zusätzlich logische Beziehungen, Eventinhaberschaft und genehmigte historische Stornotombstones; keine Phantomprojektionen/Modelle/Baselines. Unbekannte Views, temporäre oder angehängte Schemas werden nicht zugelassen.

Die Referenz entsteht nur im Speicher mit den tatsächlichen bestehenden v1/v2-Migratoren; das ist keine Migration des Dateiziels. `SourceSnapshot` liest Ziel-/Backupzeilen geordnet und **streamt** einen Digest samt Zählwerten, einschließlich aller ursprünglichen IDs/Hashes/Zeittexte/Audit-/Event-/Modellwerte und Quellsequenzen. Zeilen werden nicht in Dateien/Antworten kopiert. Frameworkledger und deren eigene Sequenz sind die ausdrücklich getrennte additive Ausnahme.

Vor der **ersten** Adoption muss das separate kalte DB-Backup byte-/größengleich zum unangetasteten Ziel sein. Für Wiederholung/Rücknahme nach bestätigter eigener Adoption darf das ursprüngliche Backup noch ohne Frameworkledger sein: beide Dateien werden vollständig vorgeprüft, derselbe Schema-/Quellzeilen-/Quellsequenzdigest und ausschließlich die exakt eigene Ledger werden verlangt. Veraltetes Backup nach neuen Buchungen wird abgelehnt; dann wird ein neuer konsistenter Backupstand benötigt, nicht still zurückgespielt.

Bereits korrekt adoptiert → erfolgreiche **nur lesende** Wiederholung ohne Writer/PDO-Schreibopen. Bereits zurückgenommene Ledger → nur lesender Backout-No-op. Fremde, leere oder mehrfache Trackingeinträge gelten nicht als Eigentumsnachweis.

### Ein kontrollierter Writer

Erst nach den vorstehenden Prüfungen wird das unveränderte existierende Ziel erneut mit **OPEN_READWRITE ohne OPEN_CREATE** über den Standardconnector geöffnet. Datei-/Backupidentität, tatsächlicher geöffneter Pfad und beobachtete Einstellungen werden erneut verglichen. Es wird weder Journalmodus noch Foreign-Key-/Synchronous-Verhalten still umgestellt; `busy_timeout=5000` ist verbindungslokal wie im Legacycode. Die während der Offlineverbindung beobachteten PRAGMAs sind **kein Nachweis früherer produktiver Connectioneinstellungen** und keine Freigabe einer neuen FKpolitik.

Der eine neue `Pdo\Sqlite` erhält die vorhandene Framework-Audit-UDF-Registrierung vor einer möglichen Mutation. `SQLiteConnection` wird eigens `chrono_offline_v2` genannt und ausschließlich für diese Operation verwendet; keine Standard-/Auth-/HTTP-Verbindung wird verbunden/ersetzt.

**Laravel-Transaktionsverwaltung**, ausdrücklich `transaction_mode=IMMEDIATE`, besitzt Lockbeginn/Commit/Rollback und korrekten Transaktionszähler. Dadurch werden keine Queryretries innerhalb der Transaktion erlaubt; ein Reconnectversuch auf diesem Migrationshandle wirft zusätzlich sofort einen Fehler. Dies ist kein generischer Domain-/Mehrwriteradapter und keine #75-Konkurrenzfreigabe.

Unter Lock werden Vorprüfung, genau eigener Ledgerzustand und Quell-/Backupdigest erneut geprüft. Dann erstellt nur Laravel `DatabaseMigrationRepository` die getrennte `framework_migrations` und registriert genau einen Eintrag: ID 1, Batch 1, versionierter Name mit Schemasignatur. Eine erneute Quell-/Backupprüfung vor Commit schützt die erklärte Änderungsgrenze. Alle alten Tabellen, Trigger, Marker, Originaldaten und Sequenzen bleiben erhalten. Kein `migrate:fresh`, Requestmigrator, Seed/Rehash, ORM-Audit oder neuer Wochenmodelleintrag.

## Rücknahme und Fehler

`rollback-ledger` akzeptiert nur die exakt passende einzeilige eigene Ledger und dieselben Offline-/Backup-/Quellprüfungen. Laravel entfernt ausschließlich diese **additive Trackingtabelle** in einer Immediate-Transaktion. Keine alten Tabellen/Trigger/Spalten/Schlüssel werden entfernt; kein historisches `down()` und keine automatische Dateiersetzung. Die physische SQLite-Datei muss nach DDL/Backout nicht bytegleich zum Original sein; Quellzeilen, IDs/Sequenzen und Schutzschema müssen es semantisch bleiben.

Bei Fehler: Wartungsfenster halten, Exitstatus/Phase und read-only `inspect` prüfen. Ein transaktionaler Fehler vor Commit wird zurückgerollt; ein Fehler bei nachgelagerter Backup-/Dateikontrolle darf nicht als Beweis „es wurde nichts committed“ ausgegeben werden. Der sichere unveränderte Backupstand bleibt für den kontrollierten vollständigen Restore nach #65 erhalten. Keine ungesicherte Livekopie, Sidecardateien löschen oder freie Eventwiedergabe mit neuen IDs. Nach späterem Runtimecutover entstandene Buchungen sind ein separates #82-Rücknahmegate.

## Tatsächlich ausgeführter grober Dateinachweis

PHP **8.5.12**, Laravel **13.38.0**, SQLite **3.46.1**; ein synthetischer Originalbestand aus den echten Quellmigratoren und zwei getrennte gestoppte private Kopien. Keine realen Daten/Schlüssel:

- Read-only-Open verweigert DDL und verändert Original/Dateibestand nicht; fehlender Zielpfad wird nicht erzeugt. Fehlendes Backup stoppt vor Writer/Tracking.
- Erstes `adopt`, wiederholtes nur lesendes `adopt`, dann `rollback-ledger`: sämtliche ursprünglichen Zeilen/IDs/Zeitwerte/Sequenzen bleiben digestgleich; genau ein Ledgerdatensatz und danach nur diese Ledger entfernt.
- 2 synthetische Nutzer, 2 Ereignisse, 11 Auditzeilen, 2 Modelle, 1 abgeschlossenes Originalintervall und 3 Projektionen erhalten; Original und Backup bleiben **SHA-256-bytegleich**, keine Sidecars zurückgelassen.
- Der bisherige grobe Memory-/Tombstone-/UDF-Reconnectnachweis läuft mit der umgestellten Laravel-Transaktionsverwaltung weiter. Syntax/Composer/Plattform/Diff-/Health-only-Routenprüfung getrennt; keine neue CI, realer Directory-/Hardwarelauf oder Produktionsmigration.

## Weiter offen

Keine echte Unternehmensdatenübernahme ausgeführt/freigegeben, kein WAL-/Hot-Journal-Support, keine leere Laravel-Neuinstallation oder vor-v2-Dateimigration. Keine vollständige #55-Datenqualitätsaussage, Vollrestore #65, #75-Mehrwriter-/Lost-response-/Crashproof oder #82-Cutoverabnahme. Damit ist der kritische bekannte V2-Offlinepfad geliefert, **#74 insgesamt bleibt offen**. Keine weitere Memoryfixtureausweitung als Ersatz für diese noch fehlenden klaren Pakete.
