# Isolierte Laravel-Foundation (FW-02 / #73)

**Noch keine Migration der Anwendung und kein produktiver Einstieg.** Die
bestehende Quodara-Chrono-UI, PHP-URLs, Fachlogik, Sitzungen, Schlüssel und Daten
bleiben außerhalb dieser Foundation unverändert. Kein Merge/Deployment/Cutover
ist mit diesem Paket verbunden.

## Herkunft und begrenzter Umfang

Aus dem offiziellen MIT-Gerüst [`laravel/laravel` v13.11.0](https://github.com/laravel/laravel/tree/v13.11.0)
(Commit `06d016a364a37430eec9cbc52209adce3d7667ce`) abgeleitet.
`laravel/framework:^13.0` ist durch `composer.lock` auf **13.38.0** fixiert;
PHP-Ziel ist **8.5.x**. Kein UI-Starter-Kit, neues Frontend, Node-Build oder
zusätzlicher Dienst. Standard-Laravel-Container, Service-Provider, Middleware,
Form Requests/Validierung, Exception Handler und Monolog stehen bereit.

Bewusst entfernt: Demo-Usermodell, Standardmigrationen/-factory/-seeder,
Welcome-UI, Demo-Tests, Tinker und unbenötigte Entwicklungswerkzeuge. Die
originalen Composer-Hooks zum Erstellen von `.env`, SQLite-Dateien und Schlüsseln
sowie zum Migrieren/Seeden fehlen absichtlich. `composer install` führt lediglich
Laravels Standard-Autoload-/Package-Discovery aus.

## Reproduzierbare Einrichtung (nur isolierte Abnahme)

Voraussetzung: **PHP 8.5** samt realen Extensions, Composer 2. Getestet wurden
PHP **8.5.12** (offizielle Docker-Library-CLI), Composer **2.10.3**, SQLite
**3.46.1**. PHP-Runtime und Root-Dockerfile gehören separat zu #58.

```sh
cd framework
php -v
composer --version
composer validate --strict
composer install --no-dev --prefer-dist --no-interaction
composer check-platform-reqs --no-dev
php artisan --version
php artisan route:list --json
find app bootstrap config public routes -name '*.php' -exec php -l {} \;
```

`install`, nicht `update`, reproduziert den eingecheckten Lock. Keine
`--ignore-platform-reqs`, Migration, Seed, Datenbankkopie oder Schlüsselrotation
ist hierfür nötig. `vendor/`, `.env*` (außer `.env.example`), Caches, Sessions,
Logs und Laufzeitdaten werden nicht eingecheckt. Keine GitHub-CI wird gestartet.

Die real geprüften Pflichtmodule sind `ctype`, `curl`, `dom`, `fileinfo`,
`filter`, `hash`, `mbstring`, `openssl`, `pcre`, `PDO`, `pdo_sqlite`, `session`,
`tokenizer`, `xml`; außerdem sind `json`, `iconv`, `libxml` und `sqlite3`
vorhanden. Composer kann einzelne Extension-Anforderungen durch Polyfills
abdecken; der zusätzliche reale Extension-Nachweis ist deshalb relevant.
**LDAP fehlt in der geprüften CLI** und ist nur bei späterer LDAP-Integration
notwendig; keine reale LDAP-Abnahme wird behauptet.

### Einmaliger isolierter HTTP-Boot

Laravel löst beim Aufbau seiner Response Factory auch den verschlüsselten
Sessiondienst auf. Daher ist selbst für `/up` ein **separater**
`FRAMEWORK_APP_KEY` erforderlich. Nicht `jwt.key`, `encryption.key` oder einen
anderen bestehenden Schlüssel übernehmen. Die Foundation generiert oder rotiert
keinen Schlüssel automatisch. Für eine ausdrücklich isolierte, kurzlebige
Boot-Abnahme kann ein separater Prozessschlüssel verwendet werden:

```sh
# --show gibt nur aus, schreibt keine .env und ersetzt keine Bestandsdatei.
export FRAMEWORK_APP_KEY="$(php artisan key:generate --show --no-interaction)"
APP_ENV=production APP_DEBUG=false php -S 127.0.0.1:18085 -t public public/index.php
# In einem zweiten Terminal:
curl -i -H 'Accept: application/json' http://127.0.0.1:18085/up
# Erwartet: HTTP 200, {"status":"up"}, kein Set-Cookie.
# Server danach stoppen und den nur dafür verwendeten Schlüssel verwerfen.
unset FRAMEWORK_APP_KEY
```

Eine dauerhafte Schlüsselprovisionierung vor Auth-/Websession-Nutzung ist ein
separater, geprüfter Betriebsschritt; kein Aufruf von `key:generate` ohne
`--show`, kein Überschreiben alter Geheimnisse und keine Neuverschlüsselung.

**Nur `GET|HEAD /up` ist registriert.** `routes/web.php` und
`routes/console.php` enthalten keine Geschäfts- oder Schreibpfade. `/index.php`
und die übrigen Legacy-Endpunkte werden hier nicht nachgebildet. Die eingebaute
Laravel-Health-Route wird mit `Accept: application/json` ohne HTML-/CDN-Ansicht
abgenommen. Session-/CSRF-Schutz wird nicht abgeschaltet.

## Isolation, Pfade und sensible Daten

- Die neue `framework/.htaccess` verweigert Zugriff auf den gesamten Quellbaum
  unter dem unveränderten Legacy-Webroot. Root-Router, Root-`.htaccess` und
  Root-Dockerfile bleiben unangetastet. Diese Apache-Regel schützt **nicht**
  andere Webserver: dort den Quellbaum nicht ausliefern. Aktuell nur separat
  auf Loopback prüfen. Ein späterer Cutover muss explizit **nur `public/`**
  ausliefern und den Quellbaumschutz überprüft behandeln, nicht das Repository
  als neuen Webroot freigeben.
- `config/chrono.php` bildet `APP_DATA_DIR`, `CHRONO_DB_PATH`,
  `CHRONO_JWT_KEY_PATH` und `CHRONO_ENCRYPTION_KEY_PATH` lediglich als Metadaten
  ab. Ohne Konfiguration sind sie `null`. Mit `APP_DATA_DIR` werden die
  bisherigen Dateinamen nur als Pfadwerte abgeleitet, **nie gelesen, geöffnet,
  angelegt oder migriert**. Spätere Adapter müssen absolute private Pfade
  außerhalb beider Webroots prüfen. Kein `require` des Legacy-Bootstraps.
- Laravels Verschlüsselung nutzt ausschließlich `FRAMEWORK_APP_KEY` und
  `FRAMEWORK_PREVIOUS_KEYS`, nicht Legacy-Schlüssel und nicht ein geerbtes
  generisches `APP_KEY`. Alte JWTs/LDAP-Geheimnisse bleiben bei ihren Verträgen.
- Verschlüsselte **File-Sessions**, Cookie `chrono_framework_session`,
  `Secure`, `HttpOnly`, `SameSite=Lax`, hostgebunden. Kein Zugriff auf PHPs
  `PHPSESSID`/Legacy-Sessionverzeichnis. Die Health-Route startet keine Sitzung.
- Cache ausschließlich standardmäßig filebasiert mit Namensraum
  `chrono-framework-cache-`; getrennte Datei-/Lockverzeichnisse. Per
  `FRAMEWORK_SESSION_PATH`, `FRAMEWORK_CACHE_PATH`, `FRAMEWORK_CACHE_LOCK_PATH`
  und `FRAMEWORK_FILES_PATH` lassen sich private **separate** Framework-Pfade
  wählen. Defaults liegen ausschließlich im verweigerten `framework/storage/`.
  Keine Public-Storage-Route oder Symlink-Freigabe.
- DB-Konfiguration ist ausschließlich SQLite `:memory:`. Generische `DB_*`
  und Legacy-Datenpfade werden **nicht** als Verbindung übernommen. Es gibt
  keine Migrationen, DB-Sessions, DB-Caches, DB-Queue oder Redis-Verbindung.
  Queue ist `sync`, Mail bleibt im Arbeitsspeicher, keine Jobs werden gestartet.
- Logging: Monolog nach `stderr`, ab `warning`; Reportable Exceptions enthalten
  nur Typ und generischen Text, keine Exceptionnachricht/Stacks/SQL/Bindings,
  Requestbodies, Header, Token oder Personenangaben. HTTP-Fehler sind bei
  `APP_DEBUG=false` generisch. Spätere Pakete dürfen sensitive Daten nicht
  durch eigene Log-Aufrufe oder Fehlerantworten wieder einführen.

## Übergabevertrag / offene Gates

1. **FW-03 Daten (#74):** `database/` enthält nur leere Platzhalter und wird
   nach expliziter Abstimmung übergeben. Kein `users`-Standardmodell, keine
   neue Userdatenbank und keine Umdeutung von `app_migrations` (Version 1/2).
   `framework_migrations` ist nur der inaktive Laravel-Ledgername der
   speicherlosen Scratch-Verbindung. Vor Einführung einer echten Verbindung
   sind private Pfadprüfung, Bestands-/Schemavorprüfung, Baseline, Restore und
   eine eigene Freigabe erforderlich. Der Scratch-Default ist **keine**
   freigegebene produktive Connection-Konfiguration.
2. **Audit/SQLite:** Der gelockte Standard-Connector verwendet auf PHP >=8.4
   bereits `PDO::connect`, erzeugt auf 8.5 `Pdo\Sqlite` und unterstützt
   `createFunction`. Dies ist einmal mit einer neuen `:memory:`-Verbindung
   geprüft; kein handgebauter Connector ist allein hierfür nötig. FW-03 muss
   `audit_actor`/`audit_reason` mit requestlokalem Kontext auf **jeder** echten
   Schreibverbindung, auch nach Reconnect, vor Triggern/Schreibzugriffen
   registrieren und unveränderliche Audittrigger erhalten. Der UDF-API-Nachweis
   belegt weder Domainmigration noch Lock-/Retry-/Transaktionskompatibilität.
   Laravel-Scratch nutzt Foreign Keys an/DEFERRED; Legacy-FK-Verhalten,
   `BEGIN IMMEDIATE`, `busy_timeout=5000`, Konkurrenz, Wiederholung und Audit
   müssen gesondert geklärt werden, nicht blind Defaults übernehmen.
3. **Auth (#76):** [Inaktiver Backendkern](../docs/framework/local-auth-foundation.md)
   mit Laravel-Sessionguard/Eloquent-Provider für die bestehenden Benutzer.
   Die benannte Verbindung `chrono` bleibt absichtlich unkonfiguriert, keine
   Authroute wird registriert. Keine Registrierung, Passwortresets, Userdefaults
   oder native Sessionbrücke. Produktive Aktivierung und LDAP-/Pending-/UI-
   Integration bleiben eigene Gates; #76 ist damit nicht abgeschlossen.
4. **API/Terminal:** Keine API-Routen registriert, keine JWT-Umstellung und kein
   Sanctum. Endpunkte/Status/Antworten, Tokeninvalidierung, Idempotenz und
   Geräte-HMAC bleiben bei den späteren Paketen. Ein Request-Klasse kann dort
   Laravel Form Requests nutzen, ohne neue Geschäftslogik einzuführen.
5. **UI:** Keine Templates/Assets der bestehenden UI ersetzt. Künftige
   Controller/Blade-Adapter müssen bestehende URLs und UI erhalten. Es gibt
   keinen produktiven Route-, Webroot- oder Host-Cutover.

## Grobe Abnahme am 27.10.2026

Bestanden: strikte Composer-Validierung, Install aus Lock mit Standard-Discovery,
Plattform und echte Pflichtmodule, PHP-Syntax, Artisan-Boot/Route-Liste,
isolierter HTTP-Start `/up` 200 JSON ohne Sessioncookie, Legacy-Pfad 404,
generischer 500 ohne sensiblen Exceptiontext, keine DB-Verbindung beim Boot,
keine Anlage des konfigurierten (nicht vorhandenen) Legacy-Datenverzeichnisses,
`Pdo\Sqlite`/`createFunction` auf `:memory:`.

Keine funktionale Daten-/Auth-/UI-Migration und keine Volltestsuite. Keine
Migration/Seeds, Änderungen an bestehenden Daten oder Schlüsseln, neuen
GitHub-Testläufe, produktiven URL-Änderungen, Merges oder Deployments.

Offizielle Grundlage: [Installation](https://laravel.com/docs/13.x/installation),
[Deploymentanforderungen](https://laravel.com/docs/13.x/deployment),
[Konfiguration](https://laravel.com/docs/13.x/configuration),
[Connector im gelockten Release](https://github.com/laravel/framework/blob/v13.38.0/src/Illuminate/Database/Connectors/Connector.php).
