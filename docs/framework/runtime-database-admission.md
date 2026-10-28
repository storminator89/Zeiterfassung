# FW-03/#74: deaktivierte Runtime-Zulassung des übernommenen V2-Bestands

Dieser Quellbaustein schließt die bisher fehlende Laravel-Verbindungs-/Writerverdrahtung. **Keine Aktivierung, Datenübernahme oder #82-Cutoverfreigabe.** Entwicklungsbasis: Main `686f69a25eb1243e143b0accf102d3fb9bd798a4`; Integrationsbasis: Main `e9cffe00188751d68b5494f61ce4d7199f7032a5`, Baum `02e5260c2cbfe80c63795ba00c14a67693a65925`. Native Einstiege, UI, Auth-/API-/LDAP-Schlüssel und Sitzungen bleiben unverändert. Keine Migration/Seeds, Accounts, neuen Dienste oder Host-/Securityänderungen.

## Geschlossen bleibt der Default

`config/chrono_runtime.php` enthält zwei getrennte echte Boolean-Schalter: `read_enabled=false`, `write_enabled=false`. Ohne Lese-Opt-in registriert der Provider **weder `chrono` noch einen Writer**. Schreib-Opt-in ohne Lese-Opt-in ist ungültig. Ein inkompatibles `chrono`, ein bereits aufgelöster Chronohandle oder vorhandenes Writerbinding wird nicht überschrieben. Laravels Konfigcache darf ausschließlich die exakt aus demselben Manifest erzeugte Chronokonfiguration wiederverwenden. Default `sqlite=:memory:` und dessen Treiber bleiben unverändert.

Konfiguration laden/cachen und Provider registrieren prüfen höchstens die Form des expliziten Manifests: kein `stat`, Datei-Read, PDO, `config.php`/`security.php`, Migrate/Adopt oder Schlüsselzugriff. Auch bei gültig konfiguriertem, aber nicht existierendem Ziel wird erst die **explizite Verbindungs-/Writerauflösung** verweigert; Providerboot öffnet es nicht. Die neue Providerzeile aktiviert keine Geschäftsroute.

## Vertrauenswürdiges Betriebsmanifest, keine Requestdaten

Vor Aktivierung separat: bestehendes [Offline-V2-Verfahren](offline-v2-adoption.md), geprüftes Backup/Restore und Cutoverfreigabe, einschließlich expliziter Connectionpolitik. Dieses Paket führt sie nicht aus. Nach erfolgreicher bekannter V2-Vorprüfung und exakt eigener Adoption hält der Operator privat fest:

| Manifestfeld | Herkunft / Bedeutung |
|---|---|
| `path` | Expliziter kanonischer absoluter privater bestehender Zielpfad außerhalb beider Webroots; kein URI, Alias oder Fallback. |
| `device`, `inode` | Aktuelle Dateidentität des zugelassenen Ziels, nicht einer separaten Backupkopie. |
| `schema_signature` | Schemasignatur der bestätigten V2-Vorprüfung und genau eigenen Ledger. |
| `schema_version` | Aktueller SQLite-`PRAGMA schema_version` **nach** Adoption; normale Datenbuchungen ändern ihn nicht. |
| `foreign_keys`, `journal_mode`, `synchronous` | Explizit geprüfte effektive Connectionpolitik; nicht aus beobachteten Offlinewerten blind auf alte Produktivhandles schließen. |

Die entsprechenden `FRAMEWORK_CHRONO_*`-Variablen sind vollständig in der Konfigurationsdatei aufgeführt. Zahlen sind nichtnegative Integers oder dezimale Integertexte, keine truthy Werte/Floatcasts. Kein `DB_*`, `CHRONO_DB_PATH`, `APP_DATA_DIR`, Requestfeld oder hochgeladener Manifestpfad wird übernommen. Es gibt keinen automatischen Manifestexport/-import und kein Provisionierungsskript. Pfad/Identität/Manifest bleiben private Betriebsmetadaten, nicht ins Issue/Repository kopieren.

Diese Metadaten sind ein **vertrauenswürdiger Operatorvertrag**, kein kryptografischer dauernder Beweis des Dateninhalts. Vorprüfung/Restore bleibt ein eigener Betriebsschritt. Die begrenzte Runtime-Prüfung ersetzt keine erneute Integritäts-/Beziehungsprüfung nach unbekannten Änderungen. Insbesondere kann ein privilegierter Dateieigentümer In-place-Inhalte manipulieren; die Prüfung ist keine adversariale Dateisystem-/TOCTOU-Isolation.

## Nur beim expliziten Öffnen

`RuntimeAdmission` validiert rein die Manifestform. `RuntimeDatabase` verwendet `OfflineFiles::validate()` ausschließlich für die bestehende private Pfad-/Modusgrenze. **Keine kalte Snapshot-/Hash-/Sidecarprüfung bei jedem Request.** Hash, Größe und mtime werden nicht festgenagelt: legitime Buchungen verändern sie.

Der gepflegte Laravel-`SQLiteConnector` öffnet genau diesen bestehenden Bestand mit `Pdo\Sqlite::OPEN_READONLY` oder `OPEN_READWRITE`, **nie OPEN_CREATE**. Vor/nach Open werden Device/Inode/Privatpfad und ein begrenzter 20-Byte-SQLiteheader verglichen (WAL vor PDO-Open verweigert, ohne normale Rollback-Journalsidecars abzulehnen); auf dem tatsächlichen Handle: genau `main` mit diesem Pfad, PHP-8.5-SQLite, Exceptionmodus, erwartete `schema_version`, genau eigene einzeilige Ledger mit Schemasignatur, erlaubte Journal-/FK-/Synchronous-Werte, `busy_timeout=5000` und Readonly-`query_only`. Die Prüfzahl ist begrenzt; keine vollständigen Zeilen-/Quick-Checkscans je Anfrage. Native DSN-/SQL-/Pfadfehler beim Öffnen/Writeraufbau werden nicht weitergegeben oder als vorherige Exception angehängt; spätere Fachoperationen behalten ihre bisherigen Fehlerverträge.

`journal_mode`, `foreign_keys` und `synchronous` werden **nicht umgestellt**. Wenn ein frischer Standardhandle der genehmigten Politik nicht entspricht, wird abgebrochen statt still umkonfiguriert. Eine andere bewusste Politik braucht einen gesondert geprüften Schritt. WAL ist ausdrücklich nicht zugelassen; auch der vorhandene Writer unterstützt nur `delete`/`truncate`/`persist`. Kein Checkpoint, Sidecarlöschen oder WAL-Headerumbau.

Normale Rollback-Journal-Sidecars und Konkurrenz gehören dagegen zum normalen SQLite-Lock-/Busyvertrag; das Offline-No-Sidecar-Gate wird nicht zur Runtime-Voraussetzung gemacht oder abgeschwächt. Kein Domainretry und keine zweite Ereignis-ID.

## Bestehende Laravel-/Fachverträge

- Laravel erhält über seinen unterstützten **benannten** Resolver `extend('chrono', ...)` eine normale `SQLiteConnection` mit Treiber `sqlite`, DEFERRED und echtem Readonly-PDO. Kein globaler Treiberersatz. Eloquent-/Domainleser behalten ihren bestehenden expliziten `chrono`-Vertrag. Readreconnect öffnet erneut ausschließlich über dieselbe Admissionprüfung.
- Nur mit zusätzlichem Schreib-Opt-in ist `AttendanceWriter` transient gebunden: **jede Auflösung ein frischer unabhängiger Handle**, nicht die DB-Manager-/Readconnection. Das ist keine erzwungene One-shot-API: ein aufbewahrter Writer kann erneut aufgerufen werden; die bestehenden Dienste lösen ihn pro Operation auf. Der vorhandene Writer besitzt weiterhin UDFs, operationlokalen Auditkontext, IMMEDIATE/5000, Commit/Rollback, kein Reconnect und keinen automatischen Retry. Keine zweite Transaktionsverwaltung oder Fachformel.
- Vertrauenswürdige aktuelle Akteur-/Rollenprüfung bleibt bei den bestehenden Web-/API-Diensten und Middleware. Dieser Provider erzeugt/übernimmt keine Identität, Sitzung oder Token. API-Schlüssel/Loginbudget, LDAP und Routenaktivierung werden hier nicht gebunden.
- Geänderte Identität/Schema/Settings/Ledger verweigern neue Handles; nach Restore/Schemaänderung ist getrennte Re-Admission nötig. Bereits offene Handles sind kein Überwachungsdienst. Keine Freigabe für einen absichtlich konkurrierenden Dateiaustausch oder zusätzliche langlebige Worker-/Octane-Lifecyclevarianten.

## Begrenzte Abnahme und offene Gates

Ein grober synthetischer privater Tempdateilauf deckt geschlossene/lazy Provider, nicht erzeugtes fehlendes Ziel, echte Readonly-Schreibverweigerung, Identitäts-/Setting-/Ledgerabweichung, normale Rollback-Journalleser sowie einen neuen bestehenden Writer mit Audit/gespeicherter Replayantwort ab. Quelle und Einzelbelege liegen im Paketnachweis; keine reale Appdaten-/Auth-/Serverausführung, Keys/Accounts oder neue CI.

Das ist keine vollständige #74/#75/#82-Abnahme: Neuinstallation/vor-v2, reale Datenqualität/Restore, WAL, umfassende Konkurrenz-/Crash-/Antwortverlustfälle, Frameworkkey-/Login-/Reauth-/Pendingflow und öffentliches Routing/Cutover bleiben separate Gates. Default und laufende Anwendung bleiben unangetastet.

## Explizites User-Audit-V3-Profil

Der [gesonderte drei-Feld-Schritt](user-audit-v3.md) bietet nur nach einem
expliziten Offlineupgrade ein zusätzliches Manifestprofil. Das bisherige genaue
achtteilige V2-Manifest bleibt unverändert. Ohne konfiguriertes Profil werden
keine zusätzlichen Nullfelder ergänzt; Read-/Write-Defaults bleiben false.

Nur für den bestätigten V3-Bestand zusätzlich:

- `FRAMEWORK_CHRONO_DB_SCHEMA_PROFILE=user-audit-v3`
- `FRAMEWORK_CHRONO_DB_BASE_SCHEMA_SIGNATURE`: ursprüngliche kanonische V2-Signatur
- bestehende `FRAMEWORK_CHRONO_DB_SCHEMA_SIGNATURE`: vollständige neue V3-Signatur
- bestehende `FRAMEWORK_CHRONO_DB_SCHEMA_VERSION`: tatsächlicher neuer SQLite-
  Schemacookie, **nicht** Applikationsversion3.

Der V3-Manifestparser verlangt genau zehn Keys, exakten Profilstring und zwei
verschiedene vollständige Signaturen. Unvollständige, unbekannte oder null-extra
Metadaten werden verweigert, nie durch Dateilesen/Requestwerte ergänzt. Die alte
Adoptionszeile bleibt mit alter Signatur und Batch1; nur die feste V3-Zeile mit
neuer Signatur und Batch2 wird angehängt. Ein reiner `expectedLedgerRows()`-
Projektor ist die gemeinsame genaue Erwartung für PDO-Runtime, native SQLite3-
Snapshotquelle und kopiertes Snapshotartefakt. Keine Prefix-/Count-Näherung oder
Entfernung der alten Schema-/Datei-/Readonly-/Settingsprüfungen.

Das Upgrade ändert Schema-Cookie und Ledger; alte Manifeste bleiben geschlossen,
bis der Operator die neue private Identität/Signatur separat neu zulässt. Keine
Konfiguration wird automatisch überschrieben. Upgrade-, Echtbestand-, Backup-/
Restore-/Runtimeaktivierungsfreigabe wird durch die bloße Quellregistrierung
nicht erteilt. Alte V2-Adoption und V2-Backout bleiben streng V2-only; kein
Down/Rewrite des Journals oder vermeintlich verlustfreies Restore nach neuen
Geschäftsschreibvorgängen. V3-fähige Diagnose und echte Betriebsabnahme sind
weiter eigene Arbeit, keine behauptete vollständige #32/#74/#82-Abnahme.
