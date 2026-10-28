# Explizite V4-Zulassung für eine neue leere Installation

**Vorbereitung, keine Laufzeitaktivierung oder fertige Aufnahmeauswahl.**
Diese Quelle basiert auf tatsächlichem Main
`e372faab83fb5eb0703af6cbece37858c719b09f` plus dem unverändert lokal
zusammengesetzten geprüften [Policykern](intake-policy-core.md)
`3e813d6e6e13471bcb3a0098bfda815be0aaa526`. Diese Voraussetzungskomposition ist
kein behaupteter neuer Main. Alte V2/V3-Daten, Konten, Einstellungen, Pfade,
Schlüssel, Aufnahme-/Auth-/Setupdienste und Geschäftsrouten werden nicht migriert
oder aktiviert. Es gibt keinen File-Creator oder neuen CLI-Befehl in diesem Paket.

## Eigenes genaues Profil statt aufgeweichter Altzulassung

`V2Preflight::inspectIntakePolicyV4` erweitert nur seinen eigenen festen
Inventarumfang um `user_intake_policy`. Es vergleicht wie bisher Tabellen,
Spalten, CHECKs, Indizes und Trigger mit kanonischen anonymen Referenzen und
prüft Versionen `[1,2,3,4]`, Beziehungen sowie die gültige Policy-Einzelzeile.
Die ursprünglichen V2- und User-Audit-V3-Einstiege bleiben erhalten und lassen
V4 nicht als vermeintliches altes Schema durch.

`IntakePolicyV4References::build($trustedBaseMapping)` baut ausschließlich drei
leere anonyme Referenzen aus dem unveränderten Quellinventar, den vorhandenen
V2/V3-Definitionen und der geprüften V4-Factory. Die unveränderte optionale
`login_attempts`-Form und Laravels Standard-Ledgerschema werden in den Referenzen
berücksichtigt. Signaturen entstehen aus den tatsächlichen kanonischen
Definitionen, nicht aus vorgegebenen Request-/Konfigurationshashes. Die
Referenz-PDOs bleiben privat; das Inventar ist vertrauenswürdige Repositoryquelle,
keine Eingabe eines Webformulars. Es wird kein Geschäfts-/Dateibestand geöffnet.

- `signatures()` liefert genau `base_schema_signature` (V2),
  `user_audit_schema_signature` (V3) und `schema_signature` (V4).
- `inspect(PDO)` prüft die V4-Struktur auch nach legitimen Konto-/Policyänderungen.
- `requireFresh(PDO)` verlangt zusätzlich kanonische leere Geschäftsdaten und
  Anfangs-Seeds, keine Loginversuche und Policyrevision1/manuell an/andere aus/
  kein Änderungsakteur. Ein nur strukturell passender Altbestand genügt nicht.
- `preservedSource(PDO)` erfasst Counts/Hashes des Anfangsbestands und der
  Policy einschließlich ihres Zeitstempels, ohne die Zeilen zurückzugeben.
  Fehlende und leere Lazy-Login-Tabelle sind nur nach Schema- und Leerheitsprüfung
  gleichwertig; befüllte Loginzähler werden niemals normalisiert oder gelöscht.

## Feste frische Ledgerregistrierung

`new FreshIntakePolicyV4Installation($references)` bietet ausschließlich
`registerFreshConnection(SQLiteConnection, ?Closure $trustedIdentityGuard)`.
Der Aufrufer hat bereits eine **eigene neue**, vollständige leere V4-Quelle
bereitgestellt. Der Kern erstellt weder Datei noch Geschäftsschema oder Nutzer.
Die Verbindung muss dediziert/idle sein, heißen
`chrono_fresh_intake_policy_v4`, PHP-8.5-SQLite/ERRMODE_EXCEPTION,
`transaction_mode=IMMEDIATE`, `busy_timeout=5000`, `query_only=0` verwenden.
Anonyme Memoryquellen sind für Nachweise zulässig; Dateien brauchen den bereits
geprüften privaten Rollbackjournalbestand und zwingend einen vertrauenswürdigen
Identitäts-/Besitzguard. WAL, Attach-Quellen, fremde Konfigurationspfade und
Reconnect sind kein Fallback.

Der File-Creator muss den Guard ausschließlich aus seiner NEW-only-Zielzuordnung
bauen: dev/ino/nlink, regulärer privater Modus, unveränderte private Elternidentität
und erwartete Main-PDO-Identität prüfen. Kein Requestcallback und **kein kalter
Vollhash während der aktiven Ledgertransaktion**: eigene DDL/Journaldateien dürfen
die Datei legitim verändern. Pfad-/Modus-/Sidecar-/Eigentumszulassung und spätere
Manifestaufnahme gehören zum separaten NEW-only-Creator, nicht in einen
Webcontroller und nicht in dieses generische Schema-Ledgerstück.

Vor und unter genau einer bestehenden Laravel-IMMEDIATE-Transaktion wird der
vollständige frische Zustand geprüft. Laravels `DatabaseMigrationRepository`
erstellt, falls nötig, sein Standardschema und schreibt genau drei feste
Zeilen mit IDs/Batches1,2,3. Sie beschreiben die **frisch erzeugte Schema-Lineage**
V2 → User-Audit-V3 → Intake-Policy-V4; die wiederverwendeten historischen
Migrationsnamenspräfixe sind kein Nachweis einer tatsächlich vorgenommenen
Altdatenmigration oder vorhandener Vorgeschichte. Partielle/fremde Ledger und
abweichende Sequenzen werden geschlossen abgelehnt. Ein vollständig gleiches
Ledger ist nur bei weiterhin unverändert frischer Quelle idempotent akzeptiert.

Alle Geschäfts-/Policy-/Auditwerte müssen durch die Ledgeroperation unverändert
bleiben. Die Klasse besitzt nur ihre Transaktion; sie öffnet keinen zweiten
Writer, führt keinen Retry aus und ersetzt keinen PDO. Rückgabe enthält die
Signaturkette, Schema-/Versionsergebnis, `registered`/`already_registered` und
`schema_version` (SQLite-Schema-Cookie, **nicht** Anwendungsversionsnummer4).
Commit-/Cleanupfehler bleiben unbestätigt: Ziel privat halten und prüfen, kein
falscher Erfolg oder pauschales Rollbackversprechen/automatischer Wiederholversuch.

## Explizites Runtime-Manifest und unveränderte Außengrenzen

`RuntimeAdmission::fromConfiguration` akzeptiert V4 nur mit genau elf Feldern:
die bisherigen acht expliziten Pfad-/Datei-/SQLite-Metadaten plus
`schema_profile='intake-policy-v4'`, `base_schema_signature` und
`user_audit_schema_signature`. Alle drei Signaturen sind vollständige
unterschiedliche 64-stellige kleine Hexstrings. Fehlende/zusätzliche/gleiche
Felder werden nicht als V2/V3 oder erlaubender Default interpretiert.
Die optionalen Quellenmetadaten in `chrono_runtime.php` berücksichtigen dafür
`FRAMEWORK_CHRONO_DB_USER_AUDIT_SCHEMA_SIGNATURE`; es wird kein solcher Wert gesetzt.

V2 behält sein genaues Ein-Zeilen-Ledger, V3 sein Zwei-Zeilen-Ledger und seine
bisherigen Manifestfelder. V4 erwartet genau drei Signaturzeilen. Beim Öffnen
für Lesen/Writer und am nativen Snapshot-Quelleneingang prüft `RuntimeDatabase`
ausschließlich im neuen V4-Zweig zusätzlich Versionen und gültige
Policy-Einzelzeile. Danach sind legitime Policykombinationen und normale
Geschäftsdaten zulässig; der Runtimepfad verlangt nicht immer wieder den
Installations-Anfangszustand. Der bestehende Snapshotkopierpfad benutzt bereits
dasselbe profilspezifische Ledger und unveränderte vollständige Schema-/Backup-
Vergleiche; sein Auth-/Downloadvertrag wird nicht geändert.

Private Webroot-/Symlink-/Hardlink-/Modus-/dev-/ino-/Headergrenzen, READONLY/
READWRITE-ohne-CREATE, Rollbackjournal, busy timeout, query_only, separate
Runtime-Lese-/Schreibfreigaben, lazy Provider und der einzige bestehende Writer
bleiben erhalten. `read_enabled`/`write_enabled` bleiben standardmäßig false.
Kein tatsächliches Manifest, Konto, Directory-/CA-/Schlüssel-/Securitysetting,
Provider, Route oder Host wird angelegt/gesetzt/umgeschaltet.

## Gemeinsame Folgeintegration und Nachweisgrenze

Der separate NEW-only-File-Creator soll ausschließlich die feste leere Factory
materialisieren, die Zielidentität prüfen, das frische Ledger registrieren,
alle Writer schließen und erst dann sein Manifest aus genau diesem Ziel
aufnehmen und read-only gegenprüfen. Er darf vorhandene Ziele nicht ersetzen.
Erst danach folgt die getrennte sichere Erst-Admin-Einrichtung; sie bleibt
unabhängig von den drei späteren Aufnahmeoptionen. Bestehende LDAP-Anmeldungen
werden nicht an eine Aufnahmeflag gekoppelt. Admin-UI und alle manuellen/LDAP-/
Registrierungsgates sind weiterhin getrennte, gemeinsam zu prüfende Folgeschritte.
Keine scheinbar wirksamen Checkboxen, kein Umgehen bestehender Holds.

Ein grober erfundener Nachweis vergleicht die ursprünglichen reinen V2/V3-
Manifest-/Ledgerverträge, leere Memory-V4-Registrierung, Quellwerterhalt,
Rollback/Refusal und eine eigens neu erzeugte private **leere synthetische**
Tempdatei. Explizite Runtime-Reader-/Writerzulassung erzeugt dort keine Konten
oder Buchungen; fehlende Signaturen/Versionsmarker/Ledgerzeilen werden abgewiesen,
die Tempdatei anschließend bereinigt. Syntax/Diff und Quellmapping kommen hinzu.
Das ist kein Login-/CSRF-/Session-/HTTP-/Browser-, tatsächlicher Installations-,
Geschäftsschreib-, Snapshotexport-, Konkurrenz-/Crash-/Restore- oder
Altdatenmigrationsnachweis, kein CI-/Deployment- oder vollständiger UI-Pass.
