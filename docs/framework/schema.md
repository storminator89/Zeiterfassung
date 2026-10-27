# FW-03: SQLite-Bestand und sicherer Übernahmevertrag

Status: **statische, nichtschreibende Bestandsaufnahme** zu #74. Keine ausführbare Laravel-Migration, kein bestandener Altbestand-/Rollbacktest, keine Produktivfreigabe. Quellenbasis `6c5f635506949168b6afb0a568c71fe46ae915f7` (Draft #7), Assessmentbasis `33c53edf…`; Details in [README](README.md).

**Späteres Inkrement:** Der [strikt speicherinterne V2-Rehearsalkern](adoption-rehearsal.md) implementiert nun einen begrenzten Vorprüfungs-/Laraveltracking-/UDF-Lebenszyklus für synthetische Quellfixtures. Die folgende Bestandsaufnahme/volle Dateiübernahmeplanung bleibt ihre ursprüngliche Quelle und ist keine Behauptung, dass die noch offenen Datei-/Neuinstallations-/vor-v2-/Rollbackgates erledigt seien.

## 1. Vollständiges deklaratives Inventar

[`schema-map.json`](schema-map.json) enthält die exakten `CREATE TABLE`-Deklarationen aus `config.php`, `security.php`, `lib/attendance.php`, `lib/practice.php`, die vier später hinzugefügten Spalten, alle drei explizit benannten Indizes und sämtliche 34 generierten Trigger. Erfassung nur durch Lesen des Quelltexts, **nicht** durch Ausführen der Bootstraps oder Öffnen einer Datenbank. Die dortigen SHA-256-Werte identifizieren **Quelldateien**, keine Datenbank, kein Produktivschema und keine personenbezogenen Daten.

| Tabelle (exakte Schreibweise) | Identität / Besonderheit | Zweck und unveränderte Datentypen |
|---|---|---|
| `users` | `id INTEGER PRIMARY KEY AUTOINCREMENT`; `username`, `email` UNIQUE | Bestehende `password`, `role`, `token`, `department_id`, `supervisor_id`; `regelarbeitszeit`/`ueberstunden` REAL, später `auth_source TEXT NOT NULL DEFAULT 'local'`. Keine Laravel-Standardfelder hinzufügen/ersetzen. |
| `zeiterfassung` | `id INTEGER PRIMARY KEY AUTOINCREMENT` | Berichtsprojektion mit Original-TEXT `startzeit/endzeit`, INTEGER `pause`, `beschreibung/standort`, `user_id`; später `absence_kind TEXT`, `planned_minutes INTEGER`. Storno kann Projektion löschen, niemals Originalintervalle/Audit. |
| `Feiertage` | `id INTEGER PRIMARY KEY AUTOINCREMENT`, `datum` UNIQUE | TEXT-Tag/Name; Großschreibung und gespeicherte Datumswerte bewahren. |
| `departments` | `id INTEGER PRIMARY KEY AUTOINCREMENT`, `name` UNIQUE | Abteilungsidentität, kein automatisches Teamscopemodell. |
| `ldap_settings` | `id INTEGER PRIMARY KEY AUTOINCREMENT` | Host/Port/Bindname/BaseDN/verschlüsseltes Bindgeheimnis; jüngste ID maßgeblich. Keine Secrets ins Mapping/Log aufnehmen. |
| `pause_settings` | `id INTEGER PRIMARY KEY AUTOINCREMENT` | `hours_threshold`, `minimum_pause` INTEGER. Wenn Bestandsbootstrap Tabelle leer vorfindet: Defaults `(6,30),(9,45)`; keine automatische Neuaussaat bei Laravel-Adoption. |
| `app_migrations` | `version INTEGER PRIMARY KEY` | Bestehende Marker 1 und 2, **nicht** mit Laravel-Migrationshistorie verwechseln. |
| `terminal_settings` | `id INTEGER PRIMARY KEY CHECK(id=1)` | Schalter `enabled`, neuer Bestand beginnt `(1,0)`. |
| `terminal_sites` | `id INTEGER PRIMARY KEY`, `name` UNIQUE | Basissite `(1,'Praxis')` nur bei initialer Erstellung/Legacy-v1-Migration. |
| `terminals` | `id INTEGER PRIMARY KEY` | `site_id,name,generation,pairing_hash,pairing_expires,enabled,last_seen`; Generation/Pairing-/Enabledzustand bewahren. |
| `employee_credentials` | `user_id INTEGER PRIMARY KEY`, `employee_code` UNIQUE, `card_hash` UNIQUE | `site_id,pin_hash,card_hash,enabled`; maximal ein vorhandener persönlicher Zugang, keine neue Ersatzidentität. |
| `terminal_nonces` | zusammengesetzter PK `(terminal_id,nonce)` | Integer `expires`, Replayzustand. Kein erfundenes `id`/Eloquent-Singlekeymodell. |
| `terminal_limits` | TEXT-PK `bucket` | Integer `starts,attempts`; Limits sind bestehender Betriebszustand. |
| `terminal_grants` | TEXT-PK `token_hash` | `terminal_id,user_id,credential_version,expires,used_event`; kurzlebige Berechtigung/Retrybindung bewahren, nicht als API-Token neu ausstellen. |
| `work_sessions` | `id INTEGER PRIMARY KEY`; `record_id` UNIQUE | `user_id,started_at,ended_at` UTC-Epoch INTEGER; ein offenes Intervall pro Person durch partiellen Index. Original, nicht von ORM-Casts neu schreiben. |
| `break_intervals` | `id INTEGER PRIMARY KEY` | `session_id,started_at,ended_at` UTC-Epoch INTEGER; eine offene Pause pro Session. |
| `attendance_events` | `id INTEGER PRIMARY KEY`; UNIQUE `(source,event_id)` | `user_id,session_id,kind,received_at,payload_hash,response`; append-only, gespeicherte Antwort ist Idempotenzvertrag. |
| `time_adjustments` | `id INTEGER PRIMARY KEY` | UTC-Epoch `proposed_start/end,reviewed_at,created_at`, Pausen, Snapshot/Gründe, Status/Reviewer; später `cancel INTEGER NOT NULL DEFAULT 0`. Finaler Antrag bleibt final. |
| `audit_log` | `id INTEGER PRIMARY KEY AUTOINCREMENT` | UTC-Epoch `occurred_at`, Akteur/Grund, polymorphe `entity,record_id,user_id`, `operation,before_json,after_json`; append-only. |
| `work_models` | `id INTEGER PRIMARY KEY AUTOINCREMENT` | `user_id,valid_from TEXT,minutes_json TEXT,reason,created_by,created_at INTEGER`; append-only, wirksam nach `(valid_from DESC,id DESC)`. |
| `login_attempts` | TEXT-PK `bucket` | Integer `starts,attempts`; **lazy** durch ersten Loginversuch angelegt, deshalb in gültigem frisch initialisiertem Bestand zunächst fehlend erlaubt. Vorhandene Tabelle nicht löschen/resetten. |

21 Anwendungstabellen einschließlich optionaler `login_attempts`; interne SQLiteobjekte wie `sqlite_sequence` nicht als Anwendungstabelle zählen, aber vorhandene Sequenzstände erhalten. `INTEGER PRIMARY KEY` ist nicht überall `AUTOINCREMENT`: keine pauschale `$table->id()`-Übersetzung. TEXT-/Compositekeys und explizit nicht deklarierte Nullability nicht durch ORMkonventionen verändern. Vollständige Spaltendetails stehen bewusst einmalig im JSON.

### Deklarierte und logische Beziehungen

DDL-FKs existieren nur für `users.department_id → departments.id`, `users.supervisor_id → users.id`, `zeiterfassung.user_id → users.id`. Die übrigen Beziehungen im JSON sind **logische Verträge, keine nachträglich bereits erzwungenen FKs**. Keine Cascade-/Foreign-ID-Konvention aus einem Relationshipnamen ableiten.

Vorprüfung muss Beziehungen prüfen, aber folgende gewollte Fälle unterscheiden:

- Genehmigtes Storno löscht `zeiterfassung`, behält `work_sessions.record_id`, Korrekturbezug und Audit. Fehlende Projektion ist **kein** Beweis kaputter Historie. Erlauben nur mit dazugehörigem freigegebenem `cancel=1`-Antrag und passender Audit-Löschhistorie; bei fehlendem/widersprüchlichem Beleg stoppen und melden. Nicht automatisch Projektion neu erzeugen, Originalintervall löschen oder Cascade-FK hinzufügen.
- `work_models.created_by=0` bezeichnet historische Systemerzeugung. Audit für gemeinsame `Feiertage` verwendet `user_id=0`; `audit_log.entity/record_id` ist polymorphe historische Referenz, keine Pflicht-Livezeile. Andere nicht auflösbare/ungültige Referenzen sind Untersuchungsfälle, kein stiller Repair.
- Alte offene Projektionen ohne `work_sessions` sind im Bestand ausdrücklich unterstützt und werden erst beim fachlichen Übergang kontrolliert übernommen. Eine Baseline darf hierfür weder Session/Ereigniszeiten erfinden noch fehlende Pausen rekonstruieren.
- Department-/Supervisor-Orphans, Mehrfachoffene, ungültige Zeitwerte oder unvollständige Migratorenstände stoppen die automatische Übernahme und werden an #55 übergeben. Kein fachlicher Datenputz als Migration.

### Unique-/Indexschutz

Explizit: `one_open_session ON work_sessions(user_id) WHERE ended_at IS NULL` (UNIQUE), `one_open_break ON break_intervals(session_id) WHERE ended_at IS NULL` (UNIQUE), `work_models_effective ON work_models(user_id,valid_from,id)` (nicht unique). `WHERE`-Prädikate sind Teil des Vertrags; ein gewöhnlicher Index genügt nicht. Zusätzlich alle inline PK-/UNIQUE-Constraints erhalten, insbesondere `(source,event_id)`, `work_sessions.record_id`, `employee_code/card_hash` und Nutzernamen/E-Mail. Nicht nur Anzahl/Indexnamen, sondern Spalten, Uniqueflag und Teilindexpredicate vergleichen.

### Die 34 Trigger

- 21 Audittrigger: INSERT/UPDATE/DELETE auf `users,Feiertage,work_sessions,break_intervals,zeiterfassung,time_adjustments,work_models`, je `audit_{table}_{operation}`. `audit_actor()`/`audit_reason()` und SQLite-JSON-/Epochfunktionen müssen auf dem tatsächlichen Handle funktionieren. Feldliste/Ownerexpression im JSON sind exakt der Bestand, **keine Behauptung vollständigen Adminaudits**: z. B. Rollen/Department/Secrets stehen nicht im User-JSON.
- 6 `immutable_{audit_log|attendance_events|work_models}_{UPDATE|DELETE}`: append-only.
- 4 `immutable_closed_{work_sessions|break_intervals}` / `immutable_delete_{…}`: geschlossene Originale nicht ändern, Originalintervalle nicht löschen.
- 2 `adjustment_no_delete`, `adjustment_closed`: Entscheidungen final, Anträge erhalten.
- 1 `initial_work_model`: neue Nutzer erhalten ein initiales Wochenmodell; dessen Insert wird selbst durch DB-Trigger auditiert. Keine zusätzliche Modell-/Audit-Erzeugung im ORM.

Triggerdefinitionen im JSON sind die statisch expandierten Quelldefinitionen, **kein** geprüfter Dump eines laufenden SQLitebestands. Adoption prüft Definition/Semantik, nicht nur 34 Namen.

## 2. Bestehende Migrationshistorie

1. `config.php` erstellt sechs Basistabellen `IF NOT EXISTS`, setzt bei leerer Pausenregeltabelle Defaults und ergänzt `users.auth_source` nach Spaltenprüfung. Das ist kein versionierter Laravel-Migrator und läuft bisher im Request.
2. `attendance_migrate()` setzt `busy_timeout=5000`, erstellt `app_migrations`, nimmt bei fehlender Version 1 unter `BEGIN IMMEDIATE` Terminal-/Intervall-/Event-/Korrekturtabellen samt Indizes auf und schreibt Marker 1. Modul bleibt standardmäßig aus.
3. `practice_migrate()` registriert UDFs **vor** Versionsprüfung. Bei fehlender Version 2: `BEGIN IMMEDIATE`, zweite Markerprüfung nach Lock, Audit-/Modelltabellen und drei fachliche Ergänzungen (`cancel` und die beiden Abwesenheitsfelder; die Authquelle stammt aus Schritt 1), Audittrigger, einmalige mit unbekannter Vorgeschichte beschriftete `BASELINE`-Zeilen, Finalitäts-/Append-only-Trigger, ursprüngliche Wochenmodelle aus Tages-Soll, neuer-User-Modelltrigger, Marker 2 und Commit. Fehler rollen die Transaktion zurück.

**Präzisierung der vier Spalten im Mapping:** `users.auth_source` aus `config.php`; `zeiterfassung.absence_kind`, `zeiterfassung.planned_minutes`, `time_adjustments.cancel` aus Practice-v2. Keine fünfte Ergänzung. Bei bereits vorhandener Version 2 diese Vorgeschichte/Modelle/Baselines **nicht wiederholen**. `app_migrations` ist Wahrheit plus Strukturprüfung, nicht allein Beweis einer intakten DB.

## 3. Versionierte Laravel-Übernahme – Design, noch nicht implementiert

### Vor jeder ersten Änderung

Kein Aufruf von `config.php/security.php`, kein `migrate:fresh`, kein automatisches Installer-`touch database.sqlite`, kein Laravel-Migrationstracking vor Vorprüfung. Ein fehlender PDO-SQLite-Dateipfad kann sonst schon beim Connect eine leere DB erzeugen. Der spätere explizite Übernahmebefehl muss das Ziel **vor Connect** unterscheiden:

1. Absoluter ausdrücklich gewählter isolierter Zielpfad; privater Pfad außerhalb beider Webroots, kein Symlink-/Fallback auf laufenden Altbestand. Für Erstinstallation eigener expliziter New-Modus; für Adoption Datei muss bereits existieren und wird zunächst read-only geprüft. Ein unbekannter/leerer Pfad ist kein automatisches Neuanlageeinverständnis. Foundation defaultet getrennt, nicht auf `APP_DATA_DIR/timetracking.sqlite`.
2. Nur lesende Schema-/Metadatensichtung: `sqlite_schema`, `table_xinfo`, `index_list/index_xinfo`, `foreign_key_list`, Appmarker und effektive PRAGMAs; `quick_check`/notwendige Integritäts-/Beziehungsabfragen. Geprüfte Methode zum wirklichen Read-only-Open ohne Dateierzeugung implementieren, nicht nur namensgebendes `--dry-run`.
3. Privater Vergleichsbeleg: Tabellen-/Zeilenzahlen, bestehende IDs/Sequenzen, unveränderte Originalwerte, Event-/Audit-/Modellbestand, Indizes/Trigger und relevante Metadaten. Keine personenbezogenen Zeilen/Passworthashes/Schlüssel im Repo/Issue; aggregierte Nachweise allein genügen nicht für spätere Verlustbehauptungen, der genaue Vergleich bleibt im geschützten Fixture-/Abnahmeziel.
4. State klassifizieren: **vollständig leer im expliziten New-Modus**, **bekannter vollständiger App-v2-Bestand**, **separat unterstützter vor-v2-Bestand**, **unbekannt/inkonsistent**. Unbekanntes oder teilweise fehlgeschlagenes Schema stoppt ohne Änderung. Frühere Bestände zunächst nur inventarisieren, erst nach eigener versionierter Upgradesequenz freigeben; Marker nicht blind ergänzen.
5. Effektive FK-/Journal-/Busy-/Synchronous-Werte festhalten und Go/No-Go entscheiden; Beziehungen inklusive erlaubter Tombstones prüfen. Kein automatischer Datenfix und kein Credential-/Schlüsselzugriff für Schemaaufnahme.

### Geplante Ausführung nach FW-01-Review und FW-02-Handoff

- **New:** echte Laravel-Migration erzeugt die bestehende Struktur/Schutzmechanik in definierter Reihenfolge, ohne Demo-/Userseeds. Nur ursprüngliche Infrastrukturdefaults (Terminal aus, Basissite, Pausenregeln, Appmarker) nachvollziehbar einmalig initialisieren. UDFs stehen vor Triggern/erstem Write bereit; später neue Nutzer weiter über einen initialen Modelltrigger, nicht doppelt durch ORM.
- **Adopt v2:** Struktur/Schutz/Version bestätigen, existierende Daten und `app_migrations` unverändert lassen. Nur additive, eindeutig getrennte Framework-Migrationsmetadaten als kontrollierte Änderung zulassen. Der übergebene Foundationstand `b9e0822174d580c4c83c15c6b2f789a7be29be3f` konfiguriert dafür `framework_migrations`; vor Implementierung den tatsächlich integrierten Lock-/Konfigstand erneut abgleichen. Keine sechs `CREATE IF NOT EXISTS` als Ersatz für Driftprüfung, keine neue BASELINE, Modelle, Events oder Schlüssel.
- Ein versionsbenannter Übernahmeschritt und unveränderliches Mapping-/Schemaidentitätsprotokoll dokumentieren, ohne persönliche Inhalte. Vorprüfung erzwingen, auch wenn später jemand `artisan migrate` statt des Übernahmebefehls nutzt: Laravel könnte andernfalls das Trackingrepository bereits vor der eigentlichen Migration schreiben. Dieses Enforcement ist Teil der noch offenen Implementierung, kein hier gelöstes Detail.
- Unter Write-Pause/angemessenem Lock dieselben Bedingungen erneut prüfen, dann kontrollierter Commit. Ein Absturz zwischen Adoption/Metadaten/Frameworktracking darf keinen unklaren Mischzustand verschleiern. Wiederholung erkennt exakt bestätigten Stand, erzeugt keine zweite Baseline/Modelle/Journale und meldet Drift. PoC muss die tatsächlich verwendete Laravel-Trackingtransaktionsgrenze belegen.
- Für **vor-v2** nur eine explizite, versionierte und reviewte Upgradefolge mit denselben Baselinelabels/Originalwerten; keine Bestandszeit-/Pausenrekonstruktion. Diese Folge ist bislang **nicht geliefert**. Sichere Ablehnung eines unbekannten Bestands ist besser als stille vermeintliche Reparatur, aber kein Ersatz für die geforderte repräsentative Altbestandabnahme.

## 4. Gemeinsamer PDO, UDFs und SQLiteeinstellungen

[Laravel aktiviert SQLite-FKs standardmäßig](https://laravel.com/docs/13.x/database#sqlite-configuration), während der geprüfte Legacyquellcode `foreign_keys`/`journal_mode`/`synchronous` nicht konfiguriert. **Nicht behaupten**, der konkrete Laufzeitbestand sei allein damit nachgewiesen FK=off oder Journal=DELETE. Effektive Werte müssen im Ziel gemessen werden. Bestandsgleiches Verhalten bewusst konfigurieren, eine strengere FKpolitik erst nach #55 und separater Freigabe. `busy_timeout=5000` ist dagegen im Legacycode explizit gesetzt. WAL ist keine vorsorgliche Foundationoptimierung; Sidecars/Restore/Konkurrenz ändern sich damit.

PHP 8.5 verwendet [`Pdo\Sqlite::createFunction`](https://www.php.net/manual/en/pdo-sqlite.createfunction.php). Der [offizielle Laravel-13-Connector](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Database/Connectors/Connector.php) nutzt auf neuen PHP-Versionen bereits `PDO::connect`; deshalb **keinen generischen eigenen Connector auf Vorrat bauen**. Für den tatsächlich gelockten Frameworkstand belegen, dass der Handle wirklich `Pdo\Sqlite` ist. Eine passende Registrierung über den Standardconnector-/Connection-Lebenszyklus ist trotzdem nötig: UDFs werden nicht automatisch durch Laravel angelegt.

Verbindlich für spätere Writer:

- `audit_actor` und `audit_reason` als nullargumentige UDFs registrieren und Rückgabewert/Fehler prüfen, bevor Migration/Mutation/Trigger laufen; nicht als konstante/deterministische Funktionen markieren. Verfügbarkeit von JSONfunktionen und `unixepoch()` ist Plattformgate.
- Gleicher Handle für Mutation, Eventantwort und Auditkontext, kein separates PDO in View/Export/Service; jeder nach Reconnect neu erzeugte Writer registriert erneut. Verbindungsaufteilung/read-write-Split/Queueworker erst nach eigenem Nachweis, nicht unkontrolliert aktivieren.
- Auditkontext pro Operation und Request klar setzen/zurücksetzen, keine klebenden Globals in langlebigen Prozessen. Berechtigte System-/Migrationaktionen ausdrücklich benennen; fehlende Registrierung muss Schreiben fehlschlagen lassen, nicht Audittrigger entfernen. Vorhandene fallback-Akteure nicht mit extern versiegeltem Audit verwechseln.
- Kein zusätzliches Modelobserver-Audit/Startmodell. Der vorhandene Nutzertrigger erzeugt Modell/Audit selbst.
- `BEGIN IMMEDIATE`, Terminal-locked-Aufruf, Busy-/Retry-/Commitgrenzen und offene Session/Pause behalten. Audit-/Unique-/Finalitätsschutz unter dem Laravel-PDO gehört #74/#75, nicht zu einem bloß erfolgreichen Frameworkboot.

## 5. Zeit-, Hash-, Modell- und ORMvertrag

- Epoch-INTEGER (`started_at/ended_at`, Event-/Audit-/Antrags-/Grantzeiten) sind UTC-Sekunden. Projektion `startzeit/endzeit` bleibt gespeicherter TEXT, neue Attendancewerte Berlin-ISO mit Offset. Alte offsetlose Texte wurden als Berlinlokalzeit gelesen; bei DST-Mehrdeutigkeit niemals einen vermeintlich richtigen Offset erfinden oder Originale neu serialisieren. Unsicherheit melden.
- `Feiertage.datum`/`work_models.valid_from` sind Kalendertage, nicht UTC-Mitternachtsinstants. Sollmodell enthält sieben Minutenwerte; `created_by=0` ist möglich. Modelle rückwirkend ändern Auswertung, nicht schon gespeicherte `planned_minutes` einer Abwesenheit.
- Abwesenheitsgutschrift ist keine Stempelzeit. `absence_kind/planned_minutes` und klassifizierende Altbeschreibungen erhalten; gesamter lokaler Tag belegt den Überlappungsraum. Bericht ordnet abgeschlossene Nachtschicht dem Starttag zu, offene Buchungen ergeben keinen künstlichen Istwert. Keine neue Saldo-/Lohnlogik.
- Passwort/PIN per vorhandener `password_verify`-kompatibler Hash; keine Rehashmigration. Karten-HMAC, Eventpayloadhash und gespeicherte Response sowie Credentialversion-JSON unverändert; keine andere Keyorder/JSONnormalisierung. Token-/Schlüsselidentität nicht mit Laravel `APP_KEY` verschmelzen.
- Query Builder zuerst. Bei Eloquent explizit `$table`, Keytyp/Incrementing und `$timestamps=false`; Compositekey `terminal_nonces` nicht durch erfundene ID ersetzen. Keine impliziten `created_at/updated_at`, Softdeletes, Cascadeevents, Datums-/JSON-Casts mit automatischem Rückschreiben, globale Scopes oder JSON-API-Ressourcen, die Originale verändern. Audit-/Event-/Modellhistory nicht als normale mutierbare CRUD-Modelle anbieten.

## 6. Private Ablage und Rollback

Private Pfadverträge: `APP_DATA_DIR/timetracking.sqlite`, `jwt.key`, `encryption.key`, `terminal-devices.key`, `terminal-cards.key`; letztere werden bei Bedarf angelegt. Mapping nennt nur die Pfade, nicht Inhalte oder realen Deploymentort. Keine Schlüssel aus Sicherheitsbootstrap neu erzeugen, keine Produktivdaten zum Entwicklungsfixture kopieren. Für GCMformat/Terminal-HMAC siehe [Kompatibilität](compatibility.md).

Sicherung/Vollrestore bleibt #65; diese Migration erfindet keinen Browserrestore. Vor späterer Änderung: passende Code-/Composerlock-/Konfigversion, konsistente DB samt ggf. nötigen SQLite-Sidecars und vorhandene Schlüssel gemeinsam sichern; laufende DB nicht einfach als Einzeldatei kopieren. Zuständigkeit und Schreibpause im #82-Cutoverplan festlegen.

**Rücknahmegrenzen:**

1. Vor jeglichem Daten-/Routingwechsel: isolierten Frameworkcode entfernen/zurücknehmen; Legacy unverändert, keine Datenrücknahme nötig.
2. Nach reiner bestätigter v2-Adoption/additivem Metadatenschritt: kompatibler Legacycode kann nur zurück, wenn Marker, Trigger, PRAGMAs und sämtliche Daten unverändert kompatibel bleiben. Metadaten nicht blind löschen und keine Audit-/Modell-/Eventtabellen droppen; nachvollziehbarer Backout statt destruktives `down()`.
3. Nach ersten Laravelbuchungen: altes Backup einspielen wäre Datenverlust. Bevorzugt schema-abwärtskompatiblen Code zurückschalten bei unveränderten Daten; sonst alle Writer pausieren, neue Buchungen/Audit/Entscheidungen geschützt sichern und einen geprüften fachlichen Wiederübernahmeplan freigeben. Kein freies Replay mit neuen Event-IDs, keine Regeneration alter Auditgeschichte. Ohne nachgewiesenen Weg **kein Cutover**.
4. Späterer Legacyabbau erst nach eigenem #82-Review und Restore-/Rollbackbeleg einschließlich einer nach Cutover entstandenen Buchung. Kein automatischer `down()` einer Bestandsbaseline; die spätere Implementierung muss destruktiven Rollback verweigern oder ausdrücklich kontrolliert sperren.

## 7. Verbleibende #74-Abnahme, konkret und begrenzt

| Kriterium aus #74 | Hier geliefert | Noch offen / Abhängigkeit |
|---|---|---|
| Vollständiges Bestandsmapping | 21 Tabellen, genaue Spaltendeklarationen/Keys, 3 explizite Indizes, 34 Trigger, Versionen und Pfade; keine DBberührung | Tatsächliche Zielschemavorprüfung, erlaubte Schemafamilien/Driftvergleich implementieren. |
| Leer + repräsentativer Altbestand zweimal ohne Verlust | Nur Ablauf/Beweisvertrag | Sichere synthetische/anonymisierte Referenzfixtures **ohne Kopieren realer Personen/Schlüssel**, Migration/Adoption und Vor-/Nachvergleich nach #73-Handoff/#71-Review. Kein Durchlauf hier. |
| Writer mit Audit-/Unique-/Finalitätsschutz | Genaue UDF-/Trigger-/Tombstone-/Transaktionsverträge | Integrierte Connectionregistrierung inkl. Reconnect, tatsächliche PHP8.5-/SQLiteprüfung und grober Write/Rollbacknachweis; #75-Gate vor Buchungsschreiber. |
| Wiederholung/kein Doppeljournal | v2-Adoption darf Legacybaseline nicht erneut ausführen | Tracking-/Adoptionscommitgrenze und Crash-/Wiederholungsverhalten implementieren und grob prüfen. |
| Rücknahme/additive Folge | Grenzen und konkrete Verlustgefahr beschrieben | Ausführbarer nichtdestruktiver Backout-/Restorebeleg mit #65/#82. |

Nur wenige grobe Integritäts-/Migrationsabläufe nach Freigabe, keine kleinteilige neue Testsuite. Dieses Paket hat keinen funktionalen Test, kein CI, keine laufende App/Port-18083-Fixture, keinen Produktionsaccount und keine reale LDAP-/Terminalhardware verwendet. **#74 darf aufgrund dieses Dokumentationspakets nicht als erledigt markiert werden.**
