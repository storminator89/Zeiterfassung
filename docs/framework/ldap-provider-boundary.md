# FW-09 / #80 – bestehende Providergrenze und optionaler realer Verifier

**Weiterhin keine Loginumschaltung oder abgeschlossene LDAP-Migration.**
Die ursprüngliche Providergrenze wurde auf Main `165ce38008c3a4a54bc2b4fca4fa2c63160d8a78`
vorbereitet. Der folgende Vierpfad-Schritt auf Main
`bdbce814a49077b741839a0804ab98a38b8b355c` ergänzt einen konkreten optionalen
PHP-LDAP-Verifier und einen echten synthetischen Protokollnachweis. Keine Änderung
an nativen Einstiegen, Authkonfiguration, Providern, Middleware, Routen,
Composerdateien, Konten, Schlüsseln oder tatsächlicher Produktionseinrichtung.

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
| Credentialgrenze | Nur passendes existierendes LDAPmodell darf den injizierten `LdapCredentialVerifier` erreichen. Lokale/unbekannte Quelle, unbekannte Rolle, anderes Modell, fehlende ID und noch nicht persistierte Modelle werden verweigert. Ablehnung bleibt `false`; Betriebsfehler propagieren ohne lokalen Passwortfallback. Der ursprüngliche Provider-Fakenachweis bleibt unverändert; der konkrete Verifier wird unten separat wirklich am synthetischen Directory geprüft. |
| Schreibgrenze | Keine Änderungen an Hash, ID, Quelle, E-Mail, Rolle, Abteilung oder Supervisor. Erzwungener Rehash ist ebenfalls wirkungslos: kein Directorypasswort als lokaler Hash. Remember-Abruf liefert nichts; Remember-Schreibversuch wird ausdrücklich abgewiesen. |
| Aktueller lokaler Weg | `config/auth.php`, Standardprovider `chrono_users`, `LocalSession`, `RefreshLocalAccount` und sämtliche Policies bleiben unverändert. Insbesondere akzeptiert die lokale Sessionaktion weiterhin nur lokale Konten; diese Datei erweitert sie **nicht** um LDAP. |

Es gibt **keine** `Auth::provider`-Registrierung, keinen Guardnamen für diese
Klasse und keine Containerbindung des Verifiers. Die konkrete optionale
Implementierung aktiviert diese Bindungen ausdrücklich nicht. Eine Modell-/Verifierprüfung ist weder eine
Anmeldung noch eine Freigabe, die Grenze als vollständigen Login zu verwenden.

## Konkreter optionaler PHP-8.5-Verifier statt ungeprüftem Paket-Drop-in

`PhpLdapCredentialVerifier` implementiert die vorhandene Schnittstelle mit der
gepflegten offiziellen PHP-LDAP-Extension/OpenLDAP, **nicht** mit einer eigenen
LDAP-/TLS-Protokollimplementierung und nicht als Ersatz für Laravel. Keine neue
Composerabhängigkeit/Autodiscovery oder globale Extensionpflicht für den lokalen
Frameworkbetrieb. LDAP bleibt optional; ein aktivierter Verifier ohne Extension
oder erforderliche Optionen wirft einen festen Betriebsfehler.

Die frühere Auswahl eines LdapRecord-Adapters wird für diesen begrenzten Schritt
bewusst ersetzt: Die tatsächlich geprüfte **Version v4.1.1** ruft in
[`Ldap::bind`](https://github.com/DirectoryTree/LdapRecord/blob/v4.1.1/src/Ldap.php#L258-L262)
`html_entity_decode($password)` auf. Ein wörtliches `&amp;` würde dadurch andere
Passwortbytes ergeben. Das ist ein versionsgebundener Kompatibilitätsbefund,
keine pauschale Aussage über das Projekt oder andere Versionen. Seine
[Composerdefinition](https://github.com/DirectoryTree/LdapRecord/blob/v4.1.1/composer.json)
verlangt zudem `ext-ldap`; eine ungeprüfte Pflichtinstallation würde den bisher
LDAPfreien lokalen Frameworkweg koppeln. Ein späterer Paketadapter bleibt separat
reviewbar. Der Laravel-LdapRecord-Databaseprovider mit automatischem Synchronisieren/
Speichern ist ebenfalls kein zugelassener Chrono-Lebenszyklus.

### Explizite Konfiguration, keine automatische Freigabe

Der Konstruktor nimmt nur vertrauenswürdig explizit übergebene Werte entgegen:
`enabled=false` als Standard (nur literales `true` aktiviert, keine PHP-Weak-Type-
Umwandlung von `"false"` oder `1`), eine vollständige kanonische `ldap://host:port`-URI,
BaseDN und optional eine absolute vorhandene lesbare kanonische CA-Datei. Keine
Request-/DB-/Environmentquelle, Service-Bind-Credentials, Entschlüsselung,
Keydatei, persistente Konfiguration oder Netzwerkaktion beim Konstruieren.
Nur `verify` mit ausdrücklich aktiviertem Objekt darf verbinden. Fehlende/ungültige
Konfiguration wird nicht durch native Defaults oder andere Hosts repariert.

Dieser erste praktisch geprüfte Transport unterstützt **ausschließlich StartTLS**,
nicht LDAPS, UPN-/AD-Mapping, SASL, anonyme Suche oder Failover. Die URI enthält den
Port; keine deprecated Zweiargument-`ldap_connect`-Variante, URI-Credentials,
Pfad/Query/Fragment oder Endpunktliste. Der gebundene DN bleibt die bewusst enge
Bestandsabbildung `uid=<DN-escaped username>,<baseDN>`; keine automatische OU-Suche.

Vor jedem Bind: LDAPv3, fünf Sekunden Netzwerk-/Operations-/Suchlimit, Referrals aus,
`TLS_DEMAND` und die optionale CA **auf genau dieser Verbindung**. Effektive
Zertifikatspflicht und explizite CA werden zurückgelesen; fehlende Unterstützung
verweigert. Zwingend erfolgreiches `ldap_start_tls`, keine Klartextwiederholung,
keine Zertifikat-/Namensprüfung abschalten und kein `ldap_set_option(null, ...)`.
Ohne explizite CA bleibt die Zertifikatspflicht mit dem verwalteten Systemtrust;
eine tatsächliche Trust-/CA-Einrichtung ist separat freizugeben. Die geprüfte
PHP-8.5.12-Extension erneuert den Verbindungskontext beim StartTLS, ohne einen
nicht exponierten `LDAP_OPT_X_TLS_NEWCTX`-Aufruf zu erfinden.

Passwortbytes werden weder getrimmt, HTML-dekodiert, normalisiert, gespeichert
noch gehasht; leere Passwörter werden vor Verbindung verweigert. Username und
Passwort sind im konkreten Verifier `SensitiveParameter`. Nur LDAPfehler49
(ungültige Credentials) ergibt nach Bind `false`; andere Betriebs-/TLS-/Suchfehler
werfen feste Exceptions ohne Rohdiagnose, DN, Username, Passwort, CA-Pfad oder
vorherige Exception. Kein lokaler Passwortfallback oder neuer Logger.

Nach dem Bind folgt die getrennt **filter-escaped** UID-Suche: vollständiger
LDAP-Ergebniscode0, keine Referrals/Teilresultate, genau ein Eintrag mit genau einer
UID, exakt gleich dem übergebenen Username. Zusätzlich muss eine BASE-Scope-Lesung
des tatsächlichen Bind-DN dieselbe UID belegen; keine erfundene case-folded
DN-Äquivalenz. Nur UID wird gelesen, keine Mail-/Manager-/Gruppen-/Rollenattribute.
Beide Resultate und die Verbindung werden unabhängig auch nach Fehlern freigegeben;
ein Cleanupfehler bestätigt keine erfolgreiche Prüfung. Keine Account-/Import-/
Rollen-/Passwort-/Token-/Sessionoperation und kein Aufruf des schreibenden
`chrono_ldap_import_locked`.

### Tatsächlicher begrenzter synthetischer Protokollnachweis

`framework/tools/prove-ldap-verifier.php` ist ausschließlich eine explizite
Labprobe mit festem Loopback-Ziel `ldap://127.0.0.1:1389`, BaseDN
`dc=chrono,dc=test` und privater neuer synthetischer State. Kein frei wählbarer
Server, echtes Credentialfile, Laravelbootstrap, App-PDO, Provider/Guard oder
Session-/Loginaufruf. Sie wird nur innerhalb des separat freigegebenen vorhandenen
Lab-Lifecycles aufgerufen; Server und Client müssen im selben Executor laufen.

Auf PHP **8.5.12** mit der offiziellen ABI-passenden LDAP-Extension und lokal
verifizierten Debian-OpenLDAP-Artefakten bestand ein zusammenhängender Ablauf:
validiertes StartTLS, Benutzerbind mit **wörtlichem `&amp;` im Passwort**, eindeutige
UID und tatsächlicher Bind-DN; verändertes/leeres Passwort und deaktivierter
Standard verweigert. Eine fremde CA wurde verweigert, anschließend dieselbe richtige
private CA wieder akzeptiert. Prozessglobale TLS-Optionen blieben vorher/nachher
unverändert. Das belegt auch die geprüfte verbindungslokale Trust-Isolation,
nicht LDAPS oder jede mögliche Bibliotheks-/Serverkonfiguration.

Der Runner verweigerte vorhandene State, erzeugte nur neue erfundene Labdaten,
startete genau seinen eigenen Loopback-Listener und stoppte/awaitete ihn; sämtliche
neuen Zertifikat-/Credential-/Directorydaten wurden am Ende entfernt. Keine
Systeminstallation, globale Truständerung, vorhandene Directory-/App-Daten,
Browser-/Appcredential-Eingabe, Accounts, Sessions, Listenerpersistenz oder
Produktionseinrichtung. Nur dieser eine grobe echte Protokollablauf plus Syntax/
Quellerhalt, keine zusätzliche kleinteilige Testmatrix. **Kein Laravel-Weblogin-
oder vollständiger #80-Nachweis.**

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
   Die native Importeinheit kapselt inzwischen Konfiguration, Identitäten und
   Beziehungen in einer eigenen Transaktion nach abgeschlossenem Transport;
   siehe [Transaktionsgrenze und Risiken](../LDAP-MANAGER-MAPPING.md). Das ist
   keine Providerbindung oder tatsächliche LDAP-/Recoveryabnahme. Insbesondere
   bleiben lokale Username-Konvertierung/Enrollment, Break-glass, dauerhafte
   Quittungen und reale Fehler-/Wiederanlauffälle offen. Vorhandene
   [Managerplanung](../LDAP-MANAGER-MAPPING.md) mit vollständigen DNs und
   Konflikterhalt übernehmen, keine Rollenbeförderung oder neue OU-Regel.
5. **Gemeinsamer Auth-/UI-Cutover:** Separat zugelassene Bestandsverbindung,
   Laravel-Standard-Session/CSRF/Throttle, frische Identität/Policy und
   Versionswiderruf gemeinsam mit #76 integrieren. Aktuelles
   `RefreshLocalAccount` akzeptiert bewusst kein LDAP. Keine native
   Sessionkopie, kein Token-/Sessioneinschleusen und kein Aktivieren dieser
   Klasse allein durch Austausch des Providerdrivers.
6. **Abnahmegrenze:** Das ausdrücklich freigegebene neue synthetische Directory
   hat Transport/Bind/Suche und den konkreten Verifier wie oben belegt. Es
   belegt weder LDAP-Anmeldung in Laravel noch parallele Browseranmeldung,
   Rechteentzug, reale Directory-/Hostkonfiguration oder Produktion. Diese
   Integrations-/Betriebsgates und #80 bleiben offen.

## Ursprünglicher reiner Provider-Vertragsnachweis (unverändert)

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
