# FW-09 / #80 – inaktive LDAP-Providergrenze

**Teilpaket, keine abgeschlossene LDAP-Migration und keine Loginumschaltung.**
Basis ist Main `165ce38008c3a4a54bc2b4fca4fa2c63160d8a78` vom 27.10.2026.
Vier neue Dateien, keine Änderung an nativen Einstiegen, Authkonfiguration,
Providern, Middleware, Routen, Composerdateien, Konten oder Schlüsseln.

## Unterstützte Grenze, nicht aktivierter Laufweg

`ExistingLdapUserProvider` erweitert Laravels vorhandenen
`EloquentUserProvider` und erfüllt dessen `UserProvider`-Vertrag. Es gibt keinen
neuen Guard, Passwortprüfer, Sessionhandler oder Berechtigungsmotor. Laravels
[Provider-/Authenticatable-Verträge](https://laravel.com/docs/13.x/authentication#adding-custom-user-providers)
bleiben die Integrationsstelle.

| Baustein | Vertrag / tatsächlicher Nachweis |
|---|---|
| Abruf nach Username | Ausschließlich existierende `users` über `LegacyUser`; `auth_source=ldap` und Rolle `user/supervisor/admin` sind feste Providerkriterien. Nur skalare `username/password`-Strings werden angenommen; keine eingereichten Spalten, Arrays oder Querycallbacks. Username wird wie nativ getrimmt, nicht kleingeschrieben. Gültige DB-Abfrage hier **nicht ausgeführt**. |
| Wiederabruf nach ID | Standard-Eloquent-Abruf mit anschließender Prüfung auf existierendes `LegacyUser`-Modell, LDAPquelle und bekannte Rolle. Kein Identitätsmapping, keine neue ID. Gültiger Wiederabruf hier **nicht ausgeführt**. |
| Credentialgrenze | Nur passendes existierendes LDAPmodell darf den injizierten `LdapCredentialVerifier` erreichen. Lokale/unbekannte Quelle, unbekannte Rolle, anderes Modell, fehlende ID und noch nicht persistierte Modelle werden verweigert. Ablehnung bleibt `false`; Betriebsfehler propagieren ohne lokalen Passwortfallback. Reiner Fake-Nachweis, kein Bind. |
| Schreibgrenze | Keine Änderungen an Hash, ID, Quelle, E-Mail, Rolle, Abteilung oder Supervisor. Erzwungener Rehash ist ebenfalls wirkungslos: kein Directorypasswort als lokaler Hash. Remember-Abruf liefert nichts; Remember-Schreibversuch wird ausdrücklich abgewiesen. |
| Aktueller lokaler Weg | `config/auth.php`, Standardprovider `chrono_users`, `LocalSession`, `RefreshLocalAccount` und sämtliche Policies bleiben unverändert. Insbesondere akzeptiert die lokale Sessionaktion weiterhin nur lokale Konten; diese Datei erweitert sie **nicht** um LDAP. |

Es gibt **keine** `Auth::provider`-Registrierung, keinen Guardnamen für diese
Klasse, keine Containerbindung des Verifiers und keine echte Implementierung
der Directoryschnittstelle. Eine Modell-/Verifierprüfung ist weder eine
Anmeldung noch eine Freigabe, die Grenze als vollständigen Login zu verwenden.

## Gepflegtes Spezialpaket statt eigener LDAPprotokollschicht

Entscheidung für die nächste Connectorstufe: **LdapRecord v4**, nicht eine neue
Implementierung von Bind, TLS, Filtern oder Suchprotokoll. Am 27.10.2026 anhand
der offiziellen Tags geprüft:

- [`directorytree/ldaprecord-laravel` v4.0.5, Commit `e52ee08`](https://github.com/DirectoryTree/LdapRecord-Laravel/releases/tag/v4.0.5),
  veröffentlicht am 10.08.2026. Die
  [taggebundene Composerdefinition](https://raw.githubusercontent.com/DirectoryTree/LdapRecord-Laravel/v4.0.5/composer.json)
  deklariert MIT, PHP `>=8.1`, `ext-ldap`, `ext-json`, LdapRecord `^4.0` und
  Illuminate Support einschließlich `^13.0`; Laravelprovider werden automatisch
  entdeckt. Das ist Versionskompatibilität laut Paketmetadaten, keine bereits
  durchgeführte Installation oder Directoryabnahme unter PHP 8.5.
- [`directorytree/ldaprecord` v4.1.1, Commit `589dddf`](https://github.com/DirectoryTree/LdapRecord/releases/tag/v4.1.1),
  veröffentlicht am 08.10.2026. Seine
  [Composerdefinition](https://raw.githubusercontent.com/DirectoryTree/LdapRecord/v4.1.1/composer.json)
  deklariert MIT, PHP `>=8.1`, LDAP/JSON/iconv und Illuminate Contracts/Collections
  einschließlich `^13.0`. Die aktuellen Releases belegen aktive Pflege; eine
  laufende Directoryintegration oder allgemeiner AD-Support ist damit nicht bewiesen.

Der mitgelieferte
[`DatabaseUserProvider` v4.0.5](https://raw.githubusercontent.com/DirectoryTree/LdapRecord-Laravel/v4.0.5/src/Auth/DatabaseUserProvider.php)
ist **kein ungeprüfter Drop-in** für Chrono: Sein Abruf führt den Synchronizer
aus, seine Validierung speichert das Modell; konfigurierbarer Eloquentfallback
und Rehash sind weitere Seiteneffekte. Diese automatischen Lebenszykluspfade
dürfen nicht die Bestandsregeln ersetzen. Das begrenzte Chrono-Providerstück
liefert nur die bestehende Identität an Laravel und delegiert Directoryprüfung.

Ein gesondert reviewter `LdapRecord`-Adapter soll `LdapCredentialVerifier`
implementieren: Paket-Connection/Auth und sichere DN-/Filter-APIs verwenden,
den exakten UID-Benutzerbind mit anschließender erfolgreicher eindeutiger
UID-Suche prüfen und Ressourcen zuverlässig freigeben. Keine freie
Username-/UPN-/AD-Umdeutung, lokale Ersatzprüfung, Passwortsynchronisation,
Provisionierung oder automatische Gruppenzuordnung. Die
[Paketkonfiguration](https://ldaprecord.com/docs/core/v4/configuration) und
[Bind-/Rebind-Semantik](https://ldaprecord.com/docs/core/v4/authentication)
müssen dabei ausdrücklich übersetzt werden: LDAPv3, bestehender 5-Sekunden-
Timeout, LDAPS oder **zwingendes erfolgreiches StartTLS**; Paketdefaults ohne
TLS sind nicht zulässig. Zertifikatsprüfung nicht abschalten, keine neuen
Hosts, Referrals oder Failoverziele einführen. Ein erfolgreiches Bind allein
ist noch nicht der vollständige bisherige Bind-und-Suche-Vertrag.

**Hier keine Installation**, keine Autodiscovery, kein `ext-ldap`-Workaround
und kein `--ignore-platform-reqs`. Composerlock und Plattformanforderungen
bleiben unverändert. Ohne Connector kann dieser Teil weder TLS noch Escaping,
Timeout, Suchergebnis oder Geheimnisübergang tatsächlich prüfen.

## Bestandssemantik und ausdrücklich offene Aktivierungsgates

Der native [`login.php`](../../login.php) versucht zuerst den LDAPbenutzerbind
`uid={DN-escaped username},{baseDN}`, sucht nach Filter-escaped `uid` und
verwendet `mail`. Er legt fehlende Benutzer an, übernimmt bereits vorhandene
gleichnamige Konten als `auth_source=ldap` und erneuert bei jedem LDAPerfolg
einen zufälligen lokalen Platzhalterhash. Native Sessions und JWTs binden an
diesen Hash; ein erneuter LDAPlogin widerruft dadurch bisherige Sitzungen.
Lokaler Passwortfallback bleibt ausschließlich `auth_source=local`; ein
StartTLSfehler kann den nativen Login vorher mit 503 beenden. **Keine dieser
Laufzeiteigenschaften wird hier heimlich geändert oder als bereits migriert
behauptet.** Die Grenze nimmt nur schon als LDAP markierte Konten an; neue und
lokale Namen sind kein Enrollmentweg.

1. **#29 – bereits angeforderte Versionskorrektur, nicht erfundene neue Politik:**
   [CH-23](https://github.com/storminator89/Zeiterfassung/issues/29) verlangt
   Entkopplung von Authversion und zufälligem LDAPhash oder ein bewusst
   beschlossenes Single-Session-Modell sowie getrennte Abnahmen für parallele
   Anmeldung und echten Rechteentzug. Ein reviewtes Versions-/Widerrufsmodell
   ist noch nicht integriert. Vor Aktivierung gemeinsam mit Session/JWT und
   aktuellen Rollen nachweisen; weder heutige Widerrufswirkung entfernen noch
   Hashrotation in dieser read-only Grenze nachbauen.
2. **#30 – zentraler Kontostatus ist bereits beauftragt:**
   [CH-24](https://github.com/storminator89/Zeiterfassung/issues/30) verlangt
   konsistente Deaktivierung von Login, Session, JWT und Terminalgrant und
   verhindert stilles Reaktivieren durch Sync. Aktuell fehlt ein freigegebenes
   zentrales Statusschema samt Durchsetzung. Kein geratenes `active`-Feld und
   kein nur LDAPseitiger Ersatz. Historie erhalten und den gemeinsamen
   Deaktivierungsweg vor Gesamtcutover integrieren und abnehmen.
3. **Kollisionen und Enrollment:** #80 fordert bereits, unbeabsichtigte
   Kontoübernahme zu vermeiden. Wie ein bestehendes lokales Konto bewusst an
   eine nachgewiesene Directoryidentität gebunden und ein neues Konto
   aufgenommen werden darf, ist nicht als reviewter Ablauf festgelegt.
   Gleichheit des Usernamens allein autorisiert hier keine Konvertierung oder
   Provisionierung. IDs, Rollen und aktuelle Teamzuordnung bleiben erhalten.
4. **Konfiguration/Import:** Neuester Datensatz nach `id DESC`; vorhandenes
   `v2:`-Format aus [`admin.php`](../../admin.php) bewahren: Base64 von
   12-Byte-IV, 16-Byte-Tag und AES-256-GCM-Ciphertext mit
   `hex2bin(encryption.key)`. Nicht-v2-Daten bleiben bewusst gesperrt;
   `ENCRYPTION_METHOD='aes-256-cbc'` ist kein Formatbeleg. Kein Laravel-Crypt-
   Ersatz, Schlüsselwrite oder Entschlüsseln realer Binddaten. Der gemergte
   #28-Preflight aus PR #138 ist Voraussetzung, nicht neu zu implementieren;
   Gesamtimportatomarität/Recovery bleiben dort offen. Vorhandene
   [Managerplanung](../LDAP-MANAGER-MAPPING.md) mit vollständigen DNs und
   Konflikterhalt übernehmen, keine Rollenbeförderung oder neue OU-Regel.
5. **Gemeinsamer Auth-/UI-Cutover:** Separat zugelassene Bestandsverbindung,
   Laravel-Standard-Session/CSRF/Throttle, frische Identität/Policy und
   Versionswiderruf gemeinsam mit #76 integrieren. Aktuelles
   `RefreshLocalAccount` akzeptiert bewusst kein LDAP. Keine native
   Sessionkopie, kein Token-/Sessioneinschleusen und kein Aktivieren dieser
   Klasse allein durch Austausch des Providerdrivers.
6. **Reales Testdirectory:** Erst nach ausdrücklicher Freigabe tatsächliche
   TLS-/Bind-/Suchfehler, lokale Wiederherstellung, parallele Browseranmeldung,
   Rechteentzug und repräsentativen Import abnehmen. Ohne diese Freigabe
   bleiben #80 und die zugehörigen Laufzeitkriterien offen.

## Tatsächlich ausgeführter kleiner Nachweis

```sh
cd framework
php tools/prove-ldap-provider-contract.php
```

PHP **8.5.12** / unverändert gelocktes Laravel **13.38.0**: Definitionen des
Standardprovidervertrags, anonyme nicht gespeicherte Modell-Fixtures und
injizierter Fake-Verifier. Drei grobe Grenzen: LDAPidentität/Bestandsattribute;
Quellen-/Rollen-/Inputverweigerung ohne lokalen Hashfallback; Directoryablehnung
oder Exception ohne Rehash/Rememberwrite. Ein werfender Connectionresolver
belegt **null DB-Auflösungen**. Syntax und `git diff --check` geprüft.

**Nicht ausgeführt:** gültiger DB-Abruf, echte Accounts oder Accountanlage,
Laravelbootstrap/-Guard/-Session, Login, Tokenausgabe, LDAP/Netzwerk/Bind,
Import/Konfigurationsschreiben, echte Geheimnisse, HTTP/Server/Browser,
zusätzliche CI, Aktivierung oder Deployment. Das ist eine Providergrenzen-
Kompatibilitätsprobe, kein lokaler Funktionstest des vollständigen Logins.
