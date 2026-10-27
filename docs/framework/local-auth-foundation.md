# FW-05 / #76 – inaktiver Laravel-Authkern

**Teilpaket, kein fertiges #76 und keine Loginumschaltung.** Ausschließlich
Backendbausteine unter `framework/`, ohne HTTP-Routen, neue Oberfläche,
Registrierung, Passwortreset oder Zugriff auf Bestandsdateien. Bestehende UI,
`login.php`/`logout.php`, native Sessions, JWT/API, LDAP und Terminal bleiben
unverändert. Produktive Aktivierung bleibt bis Daten-/Transaktionsfreigabe
[#74](https://github.com/storminator89/Zeiterfassung/issues/74)/
[#75](https://github.com/storminator89/Zeiterfassung/issues/75) und gemeinsam
reviewtem Auth-/UI-Cutover gesperrt.

## Ausführbare Bausteine und Grenzen

| Baustein | Verantwortung |
|---|---|
| `config/auth.php` | Laravel-`session`-Guard `web`, unveränderter Laravel-`eloquent`-Provider `chrono_users`; kein eigener Guard/Passwortprüfer. |
| `App\Auth\LegacyUser` | Existierende Tabelle `users`, unveränderte IDs, **Spalte `password` mit PHP-`password_hash`-Werten**, Rollen `user/supervisor/admin`, Team über `supervisor_id`. Keine Laravel-Standardtabelle, Timestamps, Factory, Seeder oder `remember_token`. Passwort/Token werden nicht serialisiert. Das Modell ist ein Lesemodell, kein Account-Schreibdienst. |
| Fester Verbindungsname `chrono` | Absichtlich **nicht konfiguriert**. Ein Authzugriff scheitert vor Query/Verbindungsöffnung; kein Rückgriff auf Scratch-`sqlite`, `CHRONO_DB_PATH`, `APP_DATA_DIR` oder einen neuen PDO. FW-03/FW-04 müssen die spätere auditfähige Laufzeitverbindung separat freigeben; `chrono_fixture`/`chrono_offline_v2` sind dafür kein Ersatz. Boot, Health und Providerregistrierung fragen keine Benutzer ab. |
| `config/hashing.php` | Laravel-Hashing verifiziert vorhandene, von PHP unterstützte Passwort-Hashes, ohne Algorithmuszwang auf Bcrypt (`verify=false`). `rehash_on_login=false`: kein automatischer Hash-/JWT-/LDAP-Widerruf als Migrationsnebenwirkung und kein DB-Schreibzugriff beim Login. Kein Massenrehash. |
| `LocalSession` | Lokales `attempt` über den Standardguard, bestehendes `defaultpassword` bleibt gesperrt, nur `auth_source=local` und die drei Bestandsrollen. Kein LDAP-Passwortfallback, kein JWT und kein Remember-me. Standardguard erneuert ID **und CSRF-Token** bei Erfolg und setzt Laravels Passwortmarker. Logout: Standardguard, Session invalidieren, CSRF erneuern. |
| `RefreshLocalAccount` + `auth:web` + `auth.session` | Aktuelle Identität/Rolle pro Request aus dem Standardprovider, auch wenn der Guard bereits ein Objekt gecacht hat. Fehlender/gelöschter Nutzer, unbekannte Rolle oder nichtlokale Quelle → Logout. **Laravel `AuthenticateSession`** übernimmt Passwortänderungs-Widerruf anhand seines bei Login gespeicherten Markers; kein zweiter Fingerprint-/Sessionversion-Motor. |
| `LegacyUserPolicy`, Gate `chrono-admin` | Eigene Daten; Admin alle; Supervisor ausschließlich direkte `supervisor_id`-Zuordnung. Keine Department-/rekursiven Teams. `reviewCorrection` verlangt zusätzlich eine andere Person, auch für Admin. Kein globaler Admin-`before`-Bypass. `LegacyUser::visibleTo($currentActor)` liefert denselben Lesescope für Listen. |
| `LegacyFormCsrf` | Dünner Adapter von `csrf_token` und vorhandenem `X-CSRF-Token` auf Laravels Sessiontokenvergleich. Fehler bleiben 403 Text mit Reloadhinweis. Token bleibt auch bei `Sec-Fetch-Site: same-origin` Pflicht (Laravel 13 würde sonst allein diesen Header akzeptieren). Keine Pfadausnahme, keine zusätzliche XSRF-Cookienutzung. |
| `chrono-web-login` | Laravel-Named-Limiter/`ThrottleRequests`: **15 Versuche/Benutzer und 60/IP je 600 Sekunden**, Erfolg zählt mit; nicht bei Login/Logout löschen. Hashbasierte Buckets außerhalb der Sitzung, daher nicht durch Cookieänderung zurücksetzbar. 429 Text mit `Retry-After`. Keine selbstentwickelte Countertabelle. |

Eine Policy ist **nur die Autorisierungsgrenze**, nicht die Freigabe einer
Schreiboperation: Status/Finalität, Originalsnapshot, aktuelle Zuordnung unter
Writerlock und Vier-Augen-Invarianten bleiben im gemeinsamen Fach-/Transaktionsdienst.
Insbesondere `view` nicht als pauschale Team-Schreibberechtigung verwenden.
Policy-Subjekt und Listenakteur müssen aus aktuellen DB-Modellen stammen, niemals
aus Rollen-/Teamfeldern des Requests oder der Session. `get_user.php` bleibt
adminexklusiv; dafür später `chrono-admin`, nicht die breitere `view`-Policy nutzen.

## Verpflichtender späterer Integrationsvertrag – hier nicht registriert

1. **Ein Eigentümer pro Routenfamilie.** Login, Logout und zugehörige geschützte
   Alt-URLs gemeinsam mit bestehendem UI integrieren. Kein `require config.php`,
   zweiter Bootstrap oder Wechselpendeln zwischen Legacy- und Laravel-Login.
2. Laravels Websession-/Cookie-Middleware verwenden. Bei der **gesondert
   reviewten** Webgruppenumschaltung `PreventRequestForgery` durch
   `LegacyFormCsrf` ersetzen, nicht erst dahinter ergänzen: der Standardfilter
   kennt das alte Feld sonst noch nicht. Keine `except('*')`-/Altformularausnahme.
   Unveränderte `_token`-/Header-Unterstützung von Laravel bleibt zusätzlich
   möglich. Dieses Paket ersetzt noch keine Middlewaregruppe.
3. `POST /login.php`: nach Session/CSRF zwingend
   `throttle:chrono-web-login`, dann `LocalSession::attempt`; Erfolg über
   bestehendes UI nach `index.php`, Fehler im bisherigen Formular. Das Service
   allein ist **kein öffentlicher Endpoint** und umgeht nicht die erforderliche
   Routen-/Middlewaregruppe. `POST /logout.php`: Session + CSRF, dann Logout;
   andere Methoden 405. Noch keine neuen Controller-/Antwortverträge behaupten.
4. Geschützte lokale Webrouten: nach Session/CSRF **`RefreshLocalAccount`,
   `auth:web`, `auth.session` in dieser Reihenfolge**, dann routegenaue
   Gate/Policyprüfung. Authfehler je Altpfad als Loginredirect/401 aufbereiten,
   kein globaler Redirect/JSON-Ersatz. Keine bereits bestehende PHP-Sessionrolle
   oder `auth_version` als Laravel-Anmeldenachweis übernehmen.
5. Named-Limiter mit dauerhaftem, gemeinsam wirksamem Framework-Cache betreiben.
   Der bestehende isolierte Filecache bleibt unverändert; vor Mehrinstanzbetrieb
   gemeinsame Counter/Atomizität gesondert nachweisen. Kein neuer Dienst wird
   dafür automatisch beschafft. Laravels TTL-Zähler und Reset unterscheiden
   sich in der Umsetzung vom alten SQLite-Zähler; Limits/Fenster bleiben gleich, keine
   cookieabhängige Abschwächung. Cutover startet einen neuen Namensraum und
   benötigt eine bewusste Drosselungsübergangsentscheidung.

## Reauth, Pendingereignisse und Betriebsgates

- **Einmalige bewusste Neuanmeldung**, keine native Sessionkopie/Deserialisierung.
  Cookie bleibt `chrono_framework_session`, getrennt vom nativen PHP-Namen;
  bestehende Frameworkdefaults: verschlüsselt, `Secure`, `HttpOnly`,
  `SameSite=Lax`, hostgebunden, Pfad `/`. Kein Host-, Proxy-, HTTPS- oder
  Cookie-Sicherheitswechsel in diesem Paket. Vor echter Aktivierung HTTPS,
  vertrauenswürdige Proxies, wirksame Client-IP fürs Limit, private Session-/
  Cachepfade und ausdrücklich provisionierten eigenen Frameworkschlüssel prüfen.
- Der erfolgreiche Login ändert den CSRF-Token. Ein altes Pendingereignis bleibt
  an alte numerische Identität **und alten CSRF** gebunden. Nicht automatisch
  unter neuem Account/Token wiederholen, nicht mit neuer `event_id` nachbuchen
  und nicht unbestätigt verwerfen. Vor Klärung Serverstatus/Originalbestätigung
  prüfen; Wiederanmelde-/Klärungsweg gemeinsam mit vorhandener UI und #57
  abnehmen. Hier weder UI-Pendingstore noch Buchungsdienst verändert; dieser
  tatsächliche Browser-/Antwortverlustnachweis ist **offen**.
- Kein neues Sperr-/Sessionversionmodell: im aufgenommenen Schema gibt es keine
  freigegebene allgemeine Kontostatusspalte. Änderungen aus #30 und
  LDAP-Sessionversion #29 werden erst aus deren reviewed Implementierung
  übernommen. Keine Behauptung einer bereits funktionierenden Kontosperre.
  LDAP-Webanmeldung bleibt Legacy/#80; dieser Kern wird nicht vor deren
  abgestimmter Einbindung anstelle des vollständigen Logins aktiviert.
- Einrichtung/Registrierung, Passwortänderungen, LDAPbind, API-JWTs und
  Terminalschlüssel sind ausdrücklich keine Funktionen dieses Teilpakets.

## Eine grobe synthetische Abnahme

```sh
cd framework
php artisan chrono:rehearse-local-auth
```

Keine Parameter für Dateien/Benutzer/Credentials. Der Befehl verweigert eine
bereits konfigurierte `chrono`-Verbindung oder aufgelöste Verbindungen. Er
erzeugt **nur im Prozess** einen synthetischen Wegwerfschlüssel, verschlüsselte
Arraysession, Arraycache und SQLite `:memory:` mit dem aufgenommenen
`users`-Schema und anonymen Fixturekonten. Kein Netzwerk/HTTPserver,
Legacybootstrap, Datenbankdatei, echtes Konto, `.env`- oder Schlüsselwrite.
Verbindung und Konfiguration werden danach verworfen/wiederhergestellt.

Vier grobe Gruppen: vorhandener lokaler Hash/ID, falsches Passwort/LDAPfallback;
eigene/direkte Team-/Adminrechte einschließlich Rollenänderung,
Passwortwiderruf und gelöschtem Konto; alte Formular-/Header-CSRFbindung und
cookieunabhängige Drosselung; Logout und anonymer Folgezugriff. Keine
kleinteiligen UI-, Wortlaut-/Pixeltests, keine Vollsuite und kein neuer CI-Lauf.
Das ist **kein** Nachweis gegen eine echte Bestandsdatei, echte Cookies/HTTPS,
Parallelrequests, reale LDAP-/API-/Terminalkonten oder die laufende App.

Geprüft am 27.10.2026 mit PHP **8.5.12** / Laravel **13.38.0**: vier Gruppen
bestanden; strikte Composer-Validierung, Plattformanforderungen, PHP-Syntax und
`git diff --check` bestanden. Route-Liste weiterhin ausschließlich `GET|HEAD
/up`; Boot öffnet keine DB. Zweimalige Auth-Rehearsal im selben Prozess stellt
unverbindliche DB-/Schlüsselkonfiguration wieder her; vorhandene grobe
Memory-V2-Adoptionsabnahme bleibt grün. Keine neue CI oder HTTP-Abnahme.

Offizielle Grundlage: [Authentifizierung](https://laravel.com/docs/13.x/authentication),
[Autorisierung](https://laravel.com/docs/13.x/authorization),
[CSRF](https://laravel.com/docs/13.x/csrf),
[Rate-Limiting](https://laravel.com/docs/13.x/rate-limiting).
