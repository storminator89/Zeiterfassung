# Kompatibilitätsmatrix: Bestand → geplante Laravel-Zuordnung

Vertragsbasis `6c5f635506949168b6afb0a568c71fe46ae915f7`, einschließlich offenem Draft #7; siehe [Quellen/Prüfgrenze](README.md). Alle Laravel-Controller/-Middleware/-Dienste in dieser Tabelle sind **Zielverantwortlichkeiten**, nicht schon implementierte Klassen/Routen. Physische URL und relative Asset-/AJAX-/Formlinks bleiben erhalten; kein automatisch vorangestelltes `/api` durch Laravel-Defaults.

## Middleware-/Scope-Legende

- **W:** Browserheaders, Session, aktuelle `users`-Identität/Passwortfingerprint, Sprache/Theme; für unsichere Methoden bestehender CSRF-Vertrag (`csrf_token` oder `X-CSRF-Token`). Authfehler routeabhängig Redirect/Text, nicht pauschal JSON/419. Native Session → getrennte Laravel-Sitzung mit geplanter Reauth gemäß [ADR](ADR-0001-incremental-laravel.md).
- **U:** eigener Benutzer. **A:** Admin. **T:** Admin alle; Supervisor nur `users.supervisor_id = aktueller Benutzer`; Mitarbeiter eigene Daten. Kein impliziter Department-/rekursiver Teamscope.
- **B:** Bearer-HS256/JWT-Guard, vorhandener Schlüssel/Claims/Fingerprint; ohne verpflichtende Websession/CSRF. Istbestand startet durch `config.php` dennoch eine PHP-Session; das darf nicht als API-Authentifizierung missverstanden werden.
- **D:** Terminalfreigabe (`APP_TERMINAL_ENABLED=1` **und** DB-Schalter), roher Body, Gerätesignatur/Nonce und gegebenenfalls Mitarbeitergrant; keine Websession/Browser-CSRF. Pairing ist die ausdrücklich getrennte Ausnahme mit Einmalcode.
- **V:** zusätzliche aktuelle Autorisierung und fachliche Validierung im gemeinsamen Dienst. Ein Controller/Form Request ersetzt keine Team-/Vier-Augen-/DB-Invariante.
- Methoden sind die hauptsächlichen tatsächlichen Pfade. `†` bedeutet: Altdatei besitzt keinen eigenen strengen Methodenguard; weitere Methoden laufen möglicherweise in denselben Pfad, unsichere Methoden aber weiterhin durch CSRF. Eine neue 405 wäre eine bewusst zu reviewende Vertragsänderung, nicht hier schon umgesetzt. `HEAD`-/`OPTIONS`-Annahmen nicht aus Laravel-Konventionen ableiten.

## Web und AJAX

| Bestehender Einstieg / Methode | Laravel-Zielroute → Controller / Dienst | Auth/Middleware/Scope | Erhaltener Transport und wichtige Grenzen | Eigentümer / Cutoverfamilie |
|---|---|---|---|---|
| `GET /`, `/index.php`, `/attendance.php` | Identische URLs → `AttendanceController::page` / Status+Report-Viewmodel | W,U | HTML der vorhandenen Hauptseite; `/index.php` includiert Attendance, kein neuer Startscreen. Loginredirect ohne Session. | #79 UI, #76 Auth; Web-Anwesenheit |
| `GET /attendance.php?format=status` (auch Index-/Rootalias im aktuellen Includeweg) | `AttendanceController::status` / Statusdienst | W,U | JSON `success, records, state, record_id`; letzte 30 **angelegte** Projektionen (`ORDER BY id DESC`), Lesetransaktion; `records` mit `id,startzeit,endzeit,pause,absence_kind,planned_minutes`. Kein globaler/paginierter API-Status. | #78/#79; Web-Anwesenheit |
| `POST /attendance.php` (auch bestehende Index-/Rootaliase) | `AttendanceController::transition` / AttendanceService | W,U,V | Form `event_id,action,confirm_end_pause='1'`; `source=web:{id}`. JSON-Buchungsbestätigung; Serverzeit, kein Clientzeit-Stempeln. | #78/#79 nach #75; Web-Schreiber |
| `POST /save.php` mit `action` | `LegacySaveController::transition` / derselbe AttendanceService | W,U,V | Gleicher Dienst/Quellnamensraum; fehlende ID wird im Altalias zufällig erzeugt. Diese Retryschwäche nicht als sichere Idempotenz behaupten. GET 405. | #78/#79 nach #75; Web-Schreiber |
| `POST /save.php` mit `urlaubStart,urlaubEnde,beschreibung` | `AbsenceController::store` / AbsenceService (`practice_absence`) | W,U,V | JSON; nur `Urlaub`/`Krankheit`, Sollminuten aus historisch gültigem Modell, freie/Feiertage übersprungen, Ganzkalendertag-Überlappung. Keine Urlaubsfreigabe/-anspruchsfunktion. | #78/#79; Web-Schreiber |
| `POST /save.php` mit `id/update/delete`; `/delete.php` `†` | `LegacyMutationDeniedController` | W,U | 409 Text: begründete Korrektur statt direktes CRUD/Storno. Nicht als Eloquent-Ressource reaktivieren. Save unbekannt 400; ohne Anmeldung 401. | #78/#79; Kompatibilitäts-Sperrrouten |
| `GET /login.php`, `POST /login.php` | `SessionController::create/store` / Laravel-Guard + Directoryadapter | W, anonym erlaubt; CSRF bei POST, Loginlimit | Bestehendes HTML/Fehler und Redirect `index.php`; lokale `auth_source=local` + Passwortprüfung, alternativ bestehender LDAPweg. Sprache `?lang`. Kein API-JWT-Login über LDAP als Nebenwirkung. | #76/#80; Authfamilie |
| `POST /logout.php` | `SessionController::destroy` | W, CSRF | Session leeren, Cookie löschen, Redirect `login.php`; andere Methode 405. Kein neuer GET-Logout. | #76; Authfamilie |
| `GET/POST /register.php` | `SetupController` / initialer Admin-Setup | W, anonym; POST-CSRF + `APP_SETUP_TOKEN` | Ohne Setupfreigabe 403; nur leere `users`, erneute transaktionale Prüfung; bestehender Nutzerbestand redirectet Login. Keine offene Registrierung/Reseeds. | #76; Setup gesondert gesperrt |
| `GET /dashboard.php` `†` | `DashboardController` / ReportService + vorhandene View | W,U | Bestehende HTML-/Kalender-/Diagrammseite, eigene Daten; keine Formelneuentwicklung. | #79/#78; Web-Lesen |
| `GET /get_time_records.php?page=…` `†` | `ReportFragmentController::records` / ReportService | Ziel W,U; fehlender expliziter Alt-Authguard als Gate | HTML-Fragment, 10 Datensätze pro Seite, Gruppierung auf gelesener Seite; nicht plötzlich JSON. Direkter Altpfad setzt `$_SESSION['user_id']` voraus. Fehlenden Guard nicht als beabsichtigten anonymen Datenvertrag ausgeben. | #79/#78; Web-Fragmente |
| `GET /get_latest_record.php` `†` | `ReportFragmentController::latest` | W,U | HTML-Fragment der jüngsten Arbeitsprojektion, Abwesenheiten ausgeschlossen; anonym derzeit Textabbruch ohne explizites 401. | #79/#78; Web-Fragmente |
| `GET /export_csv.php` `†` | `CsvExportController` / ReportService | W,U | Eigene Daten, UTF-8 BOM, `;`, neun bestehende Spalten, Attachment `Arbeitszeiterfassung_YYYY-MM-DD.csv`, Formelschutz für Text. Keine Team-/Payroll-API erfinden. Anonym Textabbruch statt normierter JSON. | #79/#78; Export |
| `GET/POST /settings.php` | `SettingsController` / Preferences + erlaubtes eigenes Admin-Überstundenfeld | W,U; Rollenprüfung für `ueberstunden` | HTML, `lang`, `theme_mode` und bestehende Meldungen; UI zeigt noch Importformular, Import bleibt gesperrt. | #79/#76; Web-Verwaltung |
| `GET /supervisor.php` `†` | `SupervisorController` / TeamReportService | W,T | Bestehendes HTML; Mitarbeiter bekommen keine Teamliste, Admin alle, Supervisor nur direkte Zuordnung. Zweite alte Render-/Berechnungsstrecke ist bekanntes separates Assessmentthema. | #79/#78; Team-Lesen |
| `GET/POST /admin.php` | `AdminController` + abgegrenzte Formactions | W,A,V | HTML, Suche/Pagination; `add/edit/delete_user`, `add/edit/delete_department`, `update_pause_settings`; Konten mit Audithistorie nicht löschen. Keine neu erfundene Kontosperre/Last-Admin-Regel in dieser Migration. | #79/#76; Admin |
| `POST /admin.php` mit `generate_token` | `LegacyTokenController::issue` / JWTadapter | W,A | Eigener Admin-JWT, nicht fremder Nutzer/Team; eine Stunde trotz altem irreführendem Kommentar, Feld `users.token` unverändert. | #77/#76; Admin-JWT |
| `POST /admin.php` mit `sync_ldap` | `DirectorySyncController` / Directoryadapter | W,A,V | Formfelder `ldap_host,ldap_port,ldap_user,ldap_pass,ldap_base_dn`; vorhandene IDs und Teamzuordnung. Siehe LDAPdetails unten; keine neue HTTP-LDAP-API. | #80; Admin-Directory |
| `GET/POST /adjustments.php` | `AdjustmentController` / CorrectionService | W,U/T,V | Eigener Antrag, zweite berechtigte Person für Review, direkte Teamgrenze; POST-Redirect `?saved=1`, Fehler im HTML. Offsetpflichtige Zeiten, Originalsnapshot, Finalität und Grund bewahren. | #78/#79 nach #75; Korrektur |
| `GET/POST /work_models.php` | `WorkModelController` / WorkModelService | W,U lesend, A schreibend | HTML, append-only Modell; `user_id,valid_from,day1..day7,reason`, neue Version statt Update. Nichtadmin POST 403; jüngstes `valid_from,id` gewinnt. | #78/#79; Modelle |
| `GET /history.php?before=…` `†` | `HistoryController` / AuditReader | W,T | HTML, 100 Journalzeilen rückwärts, polymorphe Originalidentität/Baselinehinweis; keine Audit-CRUD-Route. | #79/#78; Historie |
| `GET/POST /terminals.php` | `TerminalAdminController` / TerminalAdministration | W,A,V | HTML, Aktionen `toggle,site,device,revoke_device,credential,revoke_credential`; Paircode einmal über Session angezeigt; Revoke ändert Generation, Keys nicht global neu erzeugen. | #81/#79; Terminalverwaltung |
| `GET /get_user.php?id=…` `†` | `AdminUserController::show` | W,A | JSON nur bei Erfolg, Objekt ohne Passwort; Textfehler 403/400/404/500. Kein `users`-Listenendpoint/Teamzugriff. | #79/#76; Admin-AJAX |
| `GET /download.php` `†` | `DatabaseSnapshotController` | W,A | Binäres Attachment `timetracking.sqlite`, konsistentes `VACUUM INTO`, **kein** vollständiges Backup privater Schlüssel. Nicht als gewöhnlicher Static-File-Download ersetzen. | #82 + Restore #65; Backup |
| `/import.php` `†` | `PartialRestoreDeniedController` | W,A | Immer 409 Text; Offline-Vollrestore, keine Uploadverarbeitung wieder aktivieren. | #82 + #65; Restore-Sperrroute |

## Bestehende Bearer-API

Alle Pfade beginnen exakt mit `/api.php`. Aktuelle Header: `Access-Control-Allow-Origin: *`, Methoden `GET, POST, OPTIONS, DELETE`, Header `Content-Type, Authorization`. Keine Cookie-Credentials-CORS voraussetzen. Preflight erfolgt **vor** `config.php`, also ohne Session-/DB-/Schlüsselerzeugung. CORS-/Security-Änderung ist ein eigenes Gate, nicht Foundationnebenwirkung.

| Methode / URL | Geplanter Laravel-Controller / Dienst | Middleware/Scope | Tatsächlicher Vertrag / Gate | Eigentümer |
|---|---|---|---|---|
| `OPTIONS /api.php` und beliebiger Unterpfad | `LegacyApiCors` | CORS ohne Auth/DB | 200 leer. Nicht automatisch 204. | #77 |
| `POST /api.php/login` | `LegacyTokenController::login` | CORS + Loginlimit, ohne B | JSON `username,password`; ausschließlich lokale Konten; Erfolg `{success:true,token}`, ungültige Credentials 401 JSON. Ungültige Form/Throttle können Text 400/429 liefern. | #77/#76 |
| `POST /api.php/attendance` | `ApiAttendanceController::transition` / gemeinsamer AttendanceService | B,U,V | `event_id,action,confirm_end_pause` strikt bool; `source=api:{id}`. Event-ID Pflicht; keine `expectedRecord`-Weitergabe im aktuellen direkten Pfad (bekanntes #36-Gate). | #77/#78 nach #75 |
| `POST /api.php/workentry` | `LegacyAttendanceAliasController::start` / gleicher Dienst | B,U,V | Startalias, historische `endzeit` oder nichtleere `pause` → 409 Text; vorhandene Clientzeiten/Standort sind keine autoritativen Stempel. Fehlende Event-ID wird generiert. Antwort ist heutige Buchungsbestätigung, **nicht** `{data:{id:…}}`. | #77/#25 + Extensionfix #22 |
| `POST /api.php/setendzeit` | `LegacyAttendanceAliasController::end` | B,U,V | `id` ist erwartete aktive Projektion, bool `confirm_end_pause`; fehlende Event-ID wird generiert. Nicht frei beliebige Zeile ändern. | #77/#78 |
| `GET /api.php/users` | `ApiIdentityController::self` | B,U | `{success:true,data:[{id,username,email,role}]}` nur eigene Person, auch mit Admin-JWT keine globale Liste. | #77/#25 |
| `GET /api.php/timeentries` | `ApiReportController::index` | B,U | `{success,data,meta:{total,filtered,limit,offset}}`; Filter `start_date,end_date,limit,offset`; `user_id` aus Query wird nicht ausgewertet, der Tokenbenutzer ist stets der Scope. Offset ohne wirksames Limit nutzt SQLite-`LIMIT -1`; übrige Integergrenzen #24 und UTC-Datumsfilter #9 bleiben offen, kein stiller Carbon-/Paginatorwechsel. | #77/#24/#25 |
| `DELETE /api.php/timeentry/{numericId}` | `ApiMutationDeniedController` | B,U | Immer 409 Text, Korrekturantrag statt Löschung. | #77/#78 |
| Ungültiger API-Pfad bei GET/POST/DELETE; sonstige Methoden | `LegacyApiFallback` | CORS; nicht globale Webfallbacks | Unbekannter Pfad 400 JSON `{success:false,message:'Invalid endpoint'}`, andere Methode 405 JSON. Kein automatisch anderes 404-/422-Format. | #77/#25 |

`attendance_json` liefert fachlich 400/403/404/409/429 je Fall und 503 bei unbestätigtem technischen Fehler mit `{success:false,message}`. Erfolgreiche Übergänge liefern `success,message,time` (UTC ISO), `local_time` (Berlin mit Offset), `event_id,record_id,state` und optional `pause_warning`; gespeicherte Retryantwort byte-/inhaltstreu bewahren. `abort_request` liefert dagegen Text/plain, auch bei Bearerfehlern 401. Die fehlerhafte Mischung und falsche Swaggerbeispiele werden von #25/#24 korrigiert; das Dokument behauptet keine perfekte einheitliche API.

**Erweiterungen:** `firefox_add_on/` und `time-tracker-extension_edge/` rufen die konfigurierbare API-Basis mit `/login`, `/users`, `/timeentries`, `/workentry`, `/setendzeit` auf; keine eigene Serverroute. Beide erwarten teils veraltetes `data.id`, senden keine stabile Event-ID und haben keine vollständige Pause-/Bestätigungslogik. Passwort-/Tokenpersistenz ist eigenes #23-Thema. Laravel darf nicht zur Erfüllung fehlerhafter Clients direkte historische CRUD-Pfade oder fremde Daten freigeben; autorisierte #22/#23/#25-Fixes bewusst koordinieren. Quelllesung ist kein Browserstore-/Verteilungsnachweis.

## Terminalprotokoll – ein Endpoint, fünf Operationen

| Einstieg | Laravel-Ziel / Auth | Erhaltener Vertrag | Eigentümer |
|---|---|---|---|
| `POST /terminal_api.php`, `op=pair` | `TerminalController::pair`; D-Freigabe + IP-Limit + einmaliger Paircode, noch kein HMAC | `device_id,code`; Paircodehash/Expiry/Generation beibehalten; Erfolg `success,device_id,key,name`; 10 Minuten Pairinggültigkeit. | #81 |
| `POST /terminal_api.php`, `op=identify` | `TerminalController::identify`; D-HMAC, Geräte-/Personenlimit | Mitarbeitercode oder Karten-HMAC plus achtstellige PIN; Site/Enabled prüfen; Antwort `success,grant,expires_in:10,name,state,record_id`. Keine Websession. | #81 |
| `POST /terminal_api.php`, `op=event` | `TerminalController::event`; D-HMAC + Grant + V / gleicher **locked** Buchungsdienst | Grant an Gerät/Person/Credentialversion/Site gebunden; `event_id,action,confirm_end_pause`; gleiche Event-ID erlaubt Grantretry, andere ID verboten; Commit samt Grantverbrauch/Lastseen/Audit. | #81/#78 nach #75 |
| `POST /terminal_api.php`, `op=heartbeat` | `TerminalController::heartbeat`; D-HMAC | Lastseen + `{success:true,time}`; keine Buchung. | #81 |
| Andere Operation/Methode/zu großer Body | `TerminalProtocolErrors` | Modul aus zuerst 404; sonst Nicht-POST 405, mehr als 8192 Bytes 413, ungültiges JSON 400, unbekannte Aktion 400; JSON-Fehler aus Attendancewrapper. | #81 |

Exakte HMAC-Nachricht aus `lib/terminal.php`: `"terminal-v1\n{id}\n{timestamp}\n{nonce}\n{rawBody}"`, Header `X-Terminal-ID`, `X-Terminal-Time`, `X-Terminal-Nonce`, `X-Terminal-Signature`. Zeitfenster ±90 Sekunden, Nonce 32 kleine Hexzeichen, Nonceablauf +180 Sekunden; Schlüssel aus Geräte-ID/Generation und `terminal-devices.key`. **Methode und Pfad sind im aktuellen HMAC-Input nicht enthalten**; keine stärkere Bindung behaupten oder still ergänzen. Laravel muss rohe Bytes vor JSON-Neuserialisierung erhalten. Neue HTTP-Middleware darf keine Cookie-/CSRF-Anmeldung erzwingen.

Python-Agent/Kiosk ist eine **andere, lokale Vertrauensgrenze**, nicht zum Laravel-Webroot umzuziehen: lokales `GET /`, `/kiosk.js`, `/kiosk.css`, `POST /identify`, `/event`, `/reset` mit vorhandenem Host-/Origin-/Browsertoken-Schutz; Agent sendet zentral weiterhin ausschließlich `/terminal_api.php`. Pairing, lokaler Gerätedatenträger und Hardwarepilot unverändert getrennt. Kein tatsächlicher NFC-/Hardwaretest in diesem Paket.

## LDAP-Einstiege und Geheimnisformat

`login.php` ist der Browser-Benutzerbind; `admin.php?` mit POST-Flag `sync_ldap` ist Konfiguration+Bind+Import, **kein** separater LDAPendpoint. Bestehende Annahmen: LDAPv3, Timeout 5 Sekunden, LDAPS oder verpflichtendes StartTLS, Login-DN `uid={escapedUser},{baseDN}`, Suche nach escaped `uid`; Import `inetOrgPerson` und Attribute `uid,cn,sn,mail,ou,manager`. Manager-/OU-Zuordnung ist nicht automatisch allgemeines Active-Directory-/SSO-Supportversprechen. Lokale APIanmeldung akzeptiert keine LDAPkonten.

Aktuell speichert Admin Binddaten `v2:` + Base64 von **12-Byte-IV + 16-Byte-Tag + AES-256-GCM-Ciphertext**, mit `hex2bin(encryption.key)`. Die alte `ENCRYPTION_METHOD='aes-256-cbc'`-Konstante wird von diesen Helfern ignoriert. Nicht-v2-Altdaten entschlüsselt der aktuelle Helfer bewusst nicht. Laravel `Crypt` oder ein CBC-Decoder sind kein Drop-in-Format. Login benutzt zum Benutzerbind das eingegebene Passwort; Bind-Sync benutzt Formdaten. Bei erfolgreichem LDAPlogin wird der lokale Zufallshash derzeit erneuert und invalidiert dadurch alte Sessions/JWTs. Fallback gilt nur lokalen Konten, niemals lokales Passwortfallback für `auth_source=ldap` einführen. Bestehende Sync-/Recovery-/Versionsfehler #27/#28/#29 und Pilot #63 bleiben explizite Gates vor #80-Cutover.

## Statische, nichtöffentliche und ausgeschlossene Einstiege

| Quelle | Behandlung |
|---|---|
| `/apidoc.html`, `/swagger.json`, `/swagger-ui/*` | Vorhandene Dokumentations-/Assetpfade bewahren; #25 besitzt inhaltliche APIkorrekturen. Keine neuen/unimplementierten Routes aus Annotationen generieren. |
| `/assets/*` | Vorhandene URLs/Dateien und Darstellung behalten; kein Asset-/Designsystemwechsel. Remote-CSS/Fonts und noch vorhandene Legacy-Skripte sind nicht durch diese Migration bereits selbstgehostet. |
| `header.php`, `config.php`, `security.php`, `functions.php`, `router.php`, `lib/*`, `languages/*` | Interne Bootstrap-/View-/Fachquellen, **keine neuen öffentlichen Laravelrouten**. Header/Functions müssen später von DB-/Sessionnebenwirkungen entkoppelt werden, nicht als zweiter Bootstrap geladen werden. |
| `tests/*`, `terminal-agent/*`, Dotfiles, DB-/Key-/Env-/Backupdateien | Nicht öffentlich. Router und Apache haben unterschiedliche Mechanismen; Foundation ändert keinen Host-/Securityvertrag. Laravel später ausschließlich `framework/public/`, erst mit separat genehmigtem #82-Cutover. |

Eine spätere isolierte Framework-Healthroute gehört #73, nicht zum Legacy-URLbestand. Sie ist keine Freigabe, die obigen Routen oder Daten bereits durch Laravel zu bedienen.
