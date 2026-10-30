# #32 / Teilpaket: drei User-Auditfelder, explizite Offline-V3

Basis Main `9cff54fbc63f50f29176c087f5e65fb3b9aafaef`, Tree
`50172be086477917d0b36701ce845a9efde2ff13`. Dieses Quellpaket ergänzt nur
`role`, `department_id`, `supervisor_id` zu den vier bisherigen User-Snapshotkeys.
Keine Passwörter, Tokens, PINs, Authquelle, E-Mail oder andere Credentials; keine
Namen-Joins, Kontenänderung, neue Pflichtbegründung, automatische Datenkorrektur
oder vollständige #32-Abnahme. LDAP-/Terminal-/Pausen-Entities bleiben getrennt.
**Hier wurde kein echter Bestand aktualisiert, zugelassen oder aktiviert.**

## Explizite Version statt still geänderter V2

`practice.php`, `config.php`, der ursprüngliche V2-Referenzaufbau, die
`schema-map.json`-Inventur und `V2Adoption` bleiben unverändert. V2-`inspect()`
verlangt weiterhin genau `[1,2]` und dieselbe vollständige Schema-/Triggerprüfung.
Eine separat benannte feste V3-Prüfung verlangt `[1,2,3]` und die ausdrücklich
auf V2 aufgesetzte anonyme V3-Referenz. Es gibt keine beliebige Versionsallowlist,
Trigger-Ausnahme oder automatische Erkennung für die Runtimezulassung.

`lib/user_audit_v3.php` ist definitions-only. Sein fester Applybody setzt eine
aktive Eigentümertransaktion und exakte Marker `[1,2]` voraus. Er ersetzt nur
`audit_users_INSERT/UPDATE/DELETE` und ergänzt Marker3. Die Trigger behalten
Namen, Operationen, Owner=user.id, `unixepoch()`, `audit_actor()` und
`audit_reason()` sowie eine User-Auditzeile je Operation, auch bei No-op-Updates.
Keine Änderung am Startarbeitsmodell oder sonstigen Audit-/Immutable-Triggern.
SQLite garantiert damit keine feste Reihenfolge zwischen verschiedenen
AFTER-Triggern; keine bestimmte relative Journal-ID-Reihenfolge wird versprochen.
Die nativen vertrauenswürdigen UDF-Fallbacks und der Framework-AuditContext bleiben
getrennt. Der Upgrade-Zielhandle erhält niemals `practice_connection()`-Fallbacks.

Nur neue Auditvorgänge enthalten die drei Felder. Alte JSON-/Baselinezeilen,
IDs, Modelle und Journalwerte werden weder aufgefüllt noch umgeschrieben.
Die vorhandene History-/ausgewählte-JSON-Whitelist nimmt genau diese drei
Skalarfelder auf: bestehendes Escaping, Größenlimits und aktueller Eigen-/Direkt-
team-/Adminscope gelten weiter. Keine historische Rollen-/Teamsicht oder neue
Verknüpfung zu fremden Konten. Fehlende alte Felder bleiben fehlend, nicht null
oder aktueller Stammdatenstand. Der Export bleibt ausgewählt/teilweise, kein
vollständiger oder forensischer Auditnachweis.

## Genau eine versionierte Eigentümertransaktion

`UserAuditV3Upgrade` ist eine feste Offlineoperation, kein beliebiger SQLwriter.
Auf einem frischen dedizierten Laravel-SQLitehandle: IMMEDIATE, eine Transaktion,
kein Reconnect/Retry/Savepoint. Vor und unter dem Lock vollständige kanonische
V2-Prüfung und genau eigene Adoption; nach den drei DDL-Ersetzungen Marker3 und
zweite Migration gemeinsam prüfen und committen. Audit-/Geschäftszeilen und
Sequenzen bleiben unverändert. Nur `app_migrations` erhält ausdrücklich3;
dessen komplette `[1,2]`→`[1,2,3]`-Liste wird getrennt geprüft. Der vorhandene
SourceSnapshot deckt alle anderen Tabellen und Sequenzen weiter ab; die bereits
separate Framework-Ledgersequenz muss exakt2 ergeben, sie wird nie zurückgesetzt.

Die ursprüngliche Adoption bleibt unverändert:

1. `id=1, batch=1, adopt_legacy_v2_<baseSignature>` mit dem bisherigen vollständigen
   Präfix `2026_10_27_000001_`.
2. Nur anhängen: `id=2, batch=2, 2026_10_28_000002_user_audit_v3_<postSignature>`.

`baseSignature` ist die ursprüngliche kanonische V2-Signatur; `postSignature` die
volle neue V3-Signatur. Die alte Ledgerzeile wird nicht neu signiert, ersetzt
oder gelöscht. Abweichende IDs/Marker/Trigger/Signaturen/Extrazeilen stoppen.
Vor COMMIT rollt ein Fehler die eigene DDL/Marker-/Ledgertransaktion zurück.
Unklare Commit-/Rollback-/Close-/Nachprüfergebnisse bleiben unbestätigt:
Wartungsfenster behalten, Stand prüfen, kein automatischer Retry oder Restore.

## Ausdrückliches Offlinewerkzeug, keine Native-Automigration

```text
php artisan chrono:user-audit-v3 inspect --target=/private/existing.sqlite --offline
php artisan chrono:user-audit-v3 upgrade --target=/private/existing.sqlite --backup=/private/current-copy.sqlite --offline
```

Nur Platzhalter, keine freigegebenen echten Ziele. `action`, `--target` und
`--offline` sind Pflicht, kein Appdatenpfad-/Umgebungsfallback. `upgrade` verlangt
auch bei Wiederholung ein eigenständiges **bytegleiches aktuelles** gestopptes
Backup. Bei bereits V3 reicht das alte V2-Backup nicht: DDL/Marker/Ledger und
möglicherweise Geschäftsdaten sind inzwischen anders. Erst exakte V3-/Ledger-
und aktuelle Backupprüfung erlaubt den schreibfreien Already-applied-Pfad;
keine neue DDL, Marker, Ledgerzeile oder Schema-Cookieänderung.

Das Werkzeug nutzt unverändert OfflineFiles: privater bestehender kanonischer
Pfad außerhalb beider Webroots, keine Symlink-/Hardlinkaliasse, private Modi,
kalte Rollbackdatei, keine WAL-/SHM-/Journalreste. Erst Readonly/query_only und
Backupidentität/-Daten prüfen, dann einen frischen Schreibhandle öffnen. Kein
CREATE-Ziel, chmod, Checkpoint, Wechsel von FK-/Journalpolitik, Netzwerk, nativer
Bootstrap, Schlüsselzugriff oder automatisches Sicherungs-/Restoreverfahren.
`--offline` ist eine Operatorbestätigung, kein Beweis gestoppter konkurrierender
Writer. Erfolgsbericht erst nach Disconnect und geschlossener Nachprüfung;
Fehler nennt nur eine feste Phase, keine PDOdetails, Personendaten oder Pfade.

CLI-Registrierung öffnet keine Datei. Native Migrationen werden nicht erweitert
oder automatisch angestoßen. Der alte Native-V2-Guard ersetzt installierte
V3-Trigger nicht; dies ist keine umfassende Altversions-/Rollbackabnahme.
Unveränderte V2-Inspect-/Adopt-/Rollback-/`inspect-legacy-times.php`-Werkzeuge
verweigern V3 weiterhin; die zusätzliche Diagnoseunterstützung wäre ein getrennter
Schritt. V2-Ziele bleiben wie bisher unterstützt.

## Explizite Runtime-Neuzulassung und Backout

Siehe [Runtime-Zulassung](runtime-database-admission.md#explizites-user-audit-v3-profil).
Das achtteilige alte Manifest und geschlossene Defaultschalter bleiben gleich.
V3 erfordert ausdrücklich `schema_profile=user-audit-v3` und die alte
`base_schema_signature`, zusätzlich zur neuen `schema_signature` und dem echten
`PRAGMA schema_version`-Cookie. **Cookie ist nicht Applikationsversion3** und
wird nicht auf3 gesetzt. PDO, SQLite3-Snapshotquelle und Zielartefakt vergleichen
exakt dieselben vollständigen erwarteten Ledgerzeilen. Keine automatische
Manifest-/Configänderung oder Herabstufung zur V2-Adoption bei Fehlern.

Kein `down()`: V3-Historie wird nicht gelöscht und die neue Aufnahme nicht still
abgeschaltet. Alte V2-Backoutwerkzeuge dürfen die zweite Ledgerzeile nicht entfernen.
Vor neuen Geschäftsschreibvorgängen ist nur ein separat freigegebenes kaltes
Restore mit passendem Code und neuer Dateidentitäts-/Manifestzulassung denkbar.
Nach neuen Buchungen würde das alte Backup Daten verlieren: offline bleiben und
einen eigens geprüften Forward-/Backoutweg wählen, kein automatisches Restore.
#65/#82 und echte Zulassung/Produktion bleiben offen.

## Begrenzter synthetischer Quellnachweis

Ein grober vollständig erfundener Offline-/Schema-/Audit-/Rollbackablauf und
notwendige Syntaxprüfung; keine neue feine Testsuite. Geprüft werden nur private
synthetische Kopien bzw. anonyme Referenzen, exakter alter V2-Hash/Standardpfad,
Aufnahme der drei sicheren neuen Felder, unveränderte Altzeilen, aktuelle
Backup-/Wiederholungsgrenze und späte Transaktionsrollback-/Preservationsgrenzen.
Ein vor neuen Geschäftsschreibvorgängen wiederhergestelltes synthetisches Backup
ist kein echter Betriebsrestore. Laufzeit/Quellhash und tatsächlich bestandene
Fälle stehen im privaten Paketnachweis. Der grobe Ablauf bestand unter PHP
**8.5.12 / SQLite 3.46.1**: alte V2-Ausgabe und Defaultkonfiguration exakt,
ein V3-Upgrade mit unverändertem Originaljournal/erster Ledgerzeile, aktuelle
V3-Backupgrenze, getrennte synthetische Pre-Business-Restorekopie, drei sichere
UI-/Exportdifferenzen und ein später Precommit-Abbruch mit vollständiger
Schema-/Marker-/Ledger-Rücknahme. Der neue echte CLI-/Operatorablauf und reale
Commit-/Crash-/Konkurrenzfehler wurden nicht ausgeführt. Kein echter Bestand, Account, Schlüssel,
Credential, Kontensaldo, HTTP-/Browser-/Directory-/Hardwareablauf, Deployment oder
Runtime-/Sicherheitskonfigurationswechsel. Independentes finales Quellreview
und jede echte Upgrade-/Restore-/Cutoverfreigabe bleiben eigene Gates.


## #74: wiederholbarer tatsächlicher CLI-End-to-End-Nachweis

Quellenbasis tatsächlicher Main `3a5cef6dd4e1980745dce70ce806b280101ae95e`,
Baum `c1cfcc9b3876edde410b1ef60ba2d52b86d46c9d`. Der frühere private Nachweis
oben prüfte den echten CLI-/Operatorpfad noch nicht. Ein zusätzlicher einzelner
grober, wiederholbarer Quell-PoC prüft jetzt genau diesen Pfad:

```sh
php framework/tools/rehearse-user-audit-upgrade.php
```

Kein Datenbank-/Pfadargument, kein `.env`-/APP_DATA_DIR-/Runtimefallback. Der
CLI-only-PoC erzeugt unter einer neuen privaten temporären Zufallsdirectory
ausschließlich seine eigene sourcegenerierte bekannte V2-Quelle und unabhängige
kalte Kopien. Die beiden vorhandenen synthetischen Fixture-Identitäten besitzen
unbrauchbare Passwortmarker; keine realen/nutzbaren Konten, Credentials, Keys,
Tokens oder Listener. Der normale Projektbootstrap samt FreshWeb-/LegacyAuth-/
Runtimeprovidern wird nicht geladen; kein bestehender Bestand wird übernommen.

Die minimale echte Laravel-Console lädt ausschließlich den vorhandenen
`LegacyFixtureServiceProvider` zusätzlich zu den Standard-Frameworkprovidern.
Sie verwendet private neue Environment-/Bootstrapcache-/Storagepfade, nicht
Betreiber-`.env`, gespeicherte Appkonfiguration oder Routen. Eventuelle
Environment-Cachepfadüberschreibungen werden nur im eigenen Prozess entfernt;
keine Hostvariable oder Betreiberdatei wird geändert. Die vorhandenen konkreten
`chrono:legacy-v2`-/`chrono:user-audit-v3`-Commandklassen, Audit-UDFs, Offline-
Datei-/Backupaufnahme und Immediate-Transaktionsdienste werden nicht ersetzt.
Der gewöhnliche Appbootstrap wird damit bewusst **nicht** abgenommen; Default-
Connection und Runtime-/Businesswriter bleiben unaufgelöst.

Ein zusammenhängender tatsächlicher CLI-Ablauf wurde unter PHP 8.5.12 / Laravel
13.38.0 / SQLite 3.46.1 vollständig ausgeführt:

1. V2-Datei aus der echten Fixturequelle, Adoption und aktuelle kalte Sicherung.
2. Echter V2-Inspect, Upgrade zu exakt `[1,2,3]`, erste Ledgerzeile exakt erhalten,
   ausschließlich zweite Zeile mit ID/Batch2 additiv. Alle übrigen Fach-/Audit-/
   Event-/Modellzeilen und Sequenzen unverändert.
3. Das alte V2-Backup wird für Wiederholung am V3-Ziel vor Mutation abgelehnt.
4. Nach aktueller kalter V3-Sicherung zwei Wiederholungen: Target-/Backupbytes,
   Dateidentitäten und Schema-Cookie unverändert, keine zweite DDL/Baseline/Audit.
5. Echter V3-Inspect und ausdrückliche Ablehnung des alten V2-Ledgerbackouts;
   abschließend vollständiger Quell-/Backup-/Integritätsvergleich.

PHP-Syntax und grober Ablauf PASS, stderr leer. Derselbe gesamte Ablauf wurde
auch mit bewusst gesetzten nichtgeheimen Runtime-Auswahlschaltern und einem
synthetischen Configcache-Canary wiederholt: kein Runtime-/Default-PDO oder
Betreiber-Configcache geöffnet, Canary unverändert/nicht ausgeführt. Dies ist
Isolationsnachweis des PoC, kein alternativer Runtimeopener.

Nur der neue CLI-PoC und dieses Dokument ändern sich. Keine Produktions-
Migration, Audittrigger, Userwriter, Admission, Runtime-/Securitykonfiguration,
Routes/Provider, UI, README-/Medien-, Dependency- oder CIänderung. Keine neue
feine Testsuite/manuelle CI. Keine Deltas aus gehaltenen/abgelehnten Paketen
#404/#405 oder den Korrektur-/API-Filtern übernommen.

Nach Erfolg bleibt nur die genau neue private Proofdirectory für lokale
Nachprüfbarkeit erhalten; Aggregate nennen keine Zeilenwerte. Fehler nennt nur
feste Phase/Typ, keine SQL-/PDO-/Credential-/Pfaddetails; unsicheres Ergebnis ist
keine automatische Retry-/Restorefreigabe. Kein V4/V5-Altbestandsupgrade, neuer
Geschäfts-/Userwrite, WAL-/Crash-/Upgrade-Konkurrenz-/Restore-/Auth-/Browser-/
Hostnachweis oder produktiver Cutover. #74/#82 und die breite #32-Abnahme bleiben
für diese übrigen Kriterien offen; der konkrete CLI-Migrationspfad ist nun
wiederholbar grob nachweisbar.
