# #74/#82 — technischer Stand und lokal erfüllbare Gates

Geprüfte Quellenbasis: tatsächlicher Main
`3efbd9134ae511c30a5fce657e858fb333a3e55c`, Baum
`394064b77453486f012ca835ea2182deded3a850`. Die ursprünglichen Issue-Checkboxen
und frühen Plantexte sind kein aktuelles Implementierungsinventar. Dieses Paket
ändert nur den vorhandenen groben SQLite-PoC und Dokumentation, nicht Migration,
Writer, Admission, Konfiguration, Rechte, UI, Routen, README oder Medien.

## Was heute wirklich vorhanden ist

| Gate | Tatsächlicher Code / begrenztes aktuelles Ergebnis | Was für den jeweiligen Zweck noch fehlt |
|---|---|---|
| Bekannten V2-Bestand erkennen und additiv registrieren | `V2Preflight`, `V2Adoption`, `OfflineFiles`, `chrono:legacy-v2`: exakte Struktur/Marker/Beziehungen, Read-only-Preflight vor Ledger, aktuelle Backup-/Dateiidentität, eigener Immediate-Block, idempotente Ledger | Keine allgemeine Erkennung/Upgradefolge beliebiger vor-v2-Bestände; Datenqualität und bestätigte Zuordnung bleiben getrennt #55. |
| Neuinstallation statt unnötiger Migration wegwerfbarer Testdaten | `FreshApplicationFiles`, `FreshAccountLifecycleV5Installation`, `AccountLifecycleV5References`, `chrono:prepare-fresh --new-only --local-browser-first-admin`: NEW-only V5 existiert bereits | Keine alte Datei umbenennen, kopieren oder Manifest umschreiben. Neue leere Testinstallation benötigt kein noch fehlendes Altbestandsupgrade; sie ist noch keine bedienbare App. |
| Audit, Unique/Finalität, Replay, Lock und Fehlergrenzen | Bestehender `AttendanceWriter`/Standard-PDO; aktueller [File-PoC](sqlite-write-proof.md) mit echten konkurrierenden Prozessen und echten Busy-/Rollbackfällen PASS | Kein vollständiger Last-/Crash-/Hostnachweis. Unbekannter COMMIT bleibt unbekannt und sperrt den Adapter, selbst wenn der kontrollierte PoC den Rollback separat sieht. |
| Rücknahme nach neuen Buchungen | Aktualisierter PoC nutzt realen Offlinebefehl: altes Backup wird abgelehnt, aktuelles kaltes Backup, zweimal Ledgerbackout und Wiederadoption, neue Fachzeilen/Sequenzen/Receipt unverändert | Das entfernt nur exakt eigene V2-Ledger. Keine Rücknahme von V3/V4/V5-Fachschema, kein Wiederherstellen alter Daten, kein nachgewiesener ganzer Code-/Route-/Deploymentbackout. |
| NEW V5 Runtime/Web | `RuntimeDatabase`, `AccountLifecycleRuntimeCohort`, FreshWeb-Composition und [Standardinstallation](laravel-default-installation.md) sind implementiert; Runtime ist nicht mehr pauschal unerreichbar | Normaler passender App-Key, tatsächliche erste Registrierung/Closure/Login, HTTPS-/Cookie-/Session-/Route-/JS-Prüfung auf exaktem Ziel sind eigene Schritte. Dieses Paket führt sie nicht aus. Frühere begrenzte V5-Journeys sind in [Runtimeprofil](account-lifecycle-v5-runtime-profile.md) an ihren alten Commit gebunden. |
| Wiederherstellung und andere Datenträger | Identitätsgebundene kalte Sicherung und vorhandener Aufnahmevertrag, früherer begrenzter In-place-V5-Nachweis dokumentiert | Beliebiger anderer Pfad/Inode/Datenträger oder kopiertes Manifest wird nicht neu zugelassen: allgemeine Recovery/Rebind-Funktion fehlt. Laufende WAL-/Hot-Journal-Dateien werden bewusst vor Connect abgelehnt; kein automatisches Checkpoint/Reparieren. |
| Vollständiger Cutover und Legacy-Abbau | Laravel-Standardquellen/Compose/Entrypoint vorhanden, Native-Kompatibilität ausdrücklich getrennt | Zielbuild, echte Mount/UID/TLS/Lock-/Stop-/Restartgrenzen, relevante Web/API/LDAP/Terminal-Clients und ganzer Backout müssen für tatsächlich gewählte Kanäle funktionieren. Gehaltene/abgelehnte Routenpakete, insbesondere #197 sowie #50-/API-Filter-Denials, bleiben unangetastet; kein breites URL-/Integrationsattest. |

## Auf dieser Quelle tatsächlich lokal geprüft

PHP 8.5.12, Laravel 13.38.0, SQLite 3.46.1, echtes `Pdo\Sqlite` und exakt
vorhandener Composer-Lock; keine Softwareinstallation. Komplett isolierte
Prozessenvironment ohne `.env`/Runtime-/LDAP-/API-/Terminalauswahl, sourcegenerierte
fabrikierte Memorydaten und neue private temporäre Dateien außerhalb Webroots.

- Vorhandenes `chrono:rehearse-v2-adoption` PASS: Vorprüfung, Wiederholung,
  Quell-/Sequenzschutz, echter Stornotombstone, Driftablehnung vor Tracking,
  UDFs/Auditkontext und Reconnect, Defaultverbindung nicht geöffnet.
- Aktualisierter vorhandener File-PoC PASS wie oben: echte zwei Writerprozesse,
  gleicher Event-Receiptvertrag, Kontext/Finalität, sichere Unknown-Outcome-
  Sperre und V2-Ledgerbackout nach neu bestätigten Buchungen.
- Tatsächlicher NEW-V5-Browserfirstadmin-Producer PASS: leere Quelle mit Markern
  `[1,2,3,4,5]`, vier exakten Ledgerzeilen, physisch vorhandener leerer Loginbudget-
  Tabelle. Zweimaliges `registerFreshConnection` bestätigt `already_registered`,
  keine doppelte Baseline/Modelle/Audit/Ledger und unveränderte Quelle/Dateiidentität.
  Es entstehen nur nichtgeheime Metadaten/Verzeichnisse/DB, **kein Account,
  Passwort, App-/API-/LDAP-/Terminalkey, Setuptoken, Listener oder Runtimeflag**.

Der erste Dependency-Reuse des lokalen Harness zeigte auf App-Klassen eines
alten Checkouts. Dies war kein Produktfehler und kein gültiger aktueller Nachweis;
ein ausschließlich ignorierter lokaler Autoloadwrapper bindet nun aktuelle App-
Quelle vor die unverändert gelockten Dependencies. Beide groben Prüfungen wurden
frisch wiederholt. Die ursprünglichen reproduzierten PoC-Fehler bei COMMIT/PDO-
Cleanup wurden nicht im Writer umgangen, sondern im veralteten Nachweis korrigiert.
Keine neue Repository-Mikrosuite oder manuell gestartete GitHub-CI.

## Was lokal als Nächstes sinnvoll ist — nicht pauschal blockiert

Für wegwerfbare autorisierte Testdaten ist die NEW-V5-Standardinstallation der
kurze Pfad: keine umfangreiche Altbestandsmigration planen. Ein isolierter normaler
Setup-/Login-/Buchung-/Korrektur-/Anzeige-/Exportlauf auf diesem exakten Quellstand
kann die noch offenen App-Gates prüfen, sofern seine konkreten Operationen und
Zielumgebung erlaubt sind; dieses Dokument erteilt keine zusätzliche Freigabe und
umgeht keine konkrete Ablehnung. Vorhandene sensible Provisionierungs-/Browser-
und Hostgrenzen sind pro tatsächlicher Aktion zu respektieren, nicht als Vorwand
für einen alternativen Opener, Sessioninjektion oder Credentialumgehung.

In der hier verwendeten Umgebung ist kein Docker-Binary vorhanden: Image-/Compose-
/Daemon-/Mount-/TLS-Start wurde **nicht versucht**, also weder bestanden noch als
verweigerte Operation dargestellt. Ein vorhandenes Chromium-Binary allein ist
keine Browserabnahme; kein Browser-/Credential-/Listenerlauf dieses Pakets.
Reale LDAP-/Terminalhardware, beliebige Altbestände, WAL-/Crash-/Volume-Recovery
und Betriebsfreigabe bleiben ungetestet. Diese Grenzen blockieren nicht das
unabhängige sichere Memory-/File-/NEW-Producerprüfen oben und behaupten keinen
vollständigen #74/#82-Abschluss oder produktiven Cutover.
