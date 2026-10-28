# #79 / #81 – vorhandene Terminal-Geräteliste, nur lesend

Begrenzte Migration der vorhandenen **Geräte**-Karte aus `terminals.php` mit
bestehendem Header, Standortspalte, Reihenfolge, Tabellen-/Kartenklassen und
Beschriftungen. Keine zweite Terminalverwaltung und keine neue URL. Die Ansicht
kennzeichnet sich ausdrücklich als nur lesend. Kein Koppeln/Sperren, Modulschalter,
Standortanlage, Personalcode, PIN, NFC-Zuordnung, Token oder Geräteschlüssel.
Keine inaktiven Scheinaktionen; die bisherige Sperr-Formularspalte fehlt bewusst.
Das ursprüngliche nur lesende Profil imitiert weder native `saved`- noch
Pairing-Banner; der native `terminal_pairing`-Sessionwert wird weiterhin weder
gelesen noch verbraucht. Die unten getrennt beschriebene optionale Standortanlage
verwendet nur für ihren eigenen Erfolg das vorhandene `saved`-Banner.

## Ein begrenzter Snapshot, keine Geheimnisse

`ChronoReads::adminTerminalDevices()` verwendet den vorhandenen DEFERRED-
Lesesnapshot und verlangt nach erneutem Laden des Kontos einen aktuellen lokalen
Admin. Erst dann liest die feste Projektion Geräte-ID/-Name, Freigabewert und
Kontaktzeit sowie den Standortnamen über den nativen **INNER JOIN**. Sortierung
bleibt `t.id DESC`, ohne neue Seitengrenze oder erfundene Standorte. Das ist eine
Geräteliste mit Standort, keine eigenständige Verwaltung aller Standorte.

Kein `t.*`: `generation`, rohe `pairing_hash`-Werte, Einmalcodes, Beschäftigten-
Credentials, PIN-/Kartenhashes, Personalcodes, Grants/Nonces und Schlüssel werden
nicht geladen oder an die Vorlage gegeben. Keine Benutzerliste. Die bestehende
Snapshot-Identitätsabfrage für den angemeldeten Admin bleibt erforderlich.

Für das unveränderte dreistufige native **Anzeigelabel** wird ausschließlich eine
nichtgeheime Hash-Präsenz in SQL abgeleitet (NULL, leer und `'0'` sind false).
Dieser Boolean und die intern gelesene Ablaufzeit bestimmen gegen denselben
Snapshotzeitpunkt „Kopplung offen“ versus „Gesperrt / abgelaufen“; bei gesetzter
Freigabe bleibt „Freigegeben“. Präsenz, Ablaufzeit und rohe Freigabe/Kontaktwerte
verlassen den Read nicht: das DTO erhält nur fertige Labels, sichere Geräte-/
Standortanzeige und Kontaktzustand. Das Label ist **keine Berechtigungsprüfung**
und erzeugt oder verlängert keinerlei Freigabe.

Es werden weder `terminal.php`, `config.php`, `security.php` noch der native
Webseitenbootstrap eingebunden. Kein `app_secret`, `AdmittedTerminalKeys`,
`TerminalProtocol` oder Writer wird aufgelöst. Die bereits migrierte öffentliche
Terminal-Ingress-/Geräteprotokollstrecke bleibt unverändert und getrennt.

## Vertrauenswürdige Kontaktanzeige

Exakt `terminal_contact_status()` aus der definitionsreinen
`lib/terminal_status.php`, mit **einmal erfasstem Serverzeitpunkt** für die ganze
Projektion; keine Zeit aus URL/Client. `TERMINAL_CONTACT_MAX_AGE=90` bleibt gleich:
bis einschließlich 90 Sekunden aktuell, danach veraltet. Null/leere/Nullwerte
sind „Noch kein Kontakt“; malformed, negative, zu große oder zukünftige Werte
sind „Kontaktzeit ungültig“. Fehlende Werte werden nie zu jetzt/online gemacht.
Gültige Zeiten werden über das vorhandene `attendance_local()` in Berlin gezeigt.

`last_seen` wird nativ bei Kopplung, akzeptiertem Heartbeat oder bestätigtem
Ereignis aktualisiert, **nicht bei jeder Authentifizierung oder Identifikation**.
30 Sekunden Heartbeatintervall plus bis zu acht Sekunden Requesttimeout erklären
das bestehende Frischefenster; sie garantieren keinen laufenden Kiosk, Leser oder
Netzwerkzugang. Freigabe und Kontakt bleiben unabhängig: auch ein gesperrtes
Gerät kann noch einen jungen historischen Kontakt besitzen. Kein Ping, keine
neue Überwachung, automatische Aktualisierung oder Alarmierung; Stand beim Laden.

## Geschützter, standardmäßig geschlossener Einstieg

Nur `LocalWebRoutes::register(..., includeTerminalDevices: true)` registriert
GET/HEAD `terminals.php` im vorhandenen persistent blockierten Websession-/CSRF-
Stack mit `RefreshLocalAccount` → `auth:web` → `auth.session`. Default ist false,
der normale Bootstrap bleibt `/up`-only. POST und andere Methoden besitzen keinen
Handler und bleiben 405; kein nativer Fallback oder Dispatch nach Aktionsfeldern.
Eine fehlende Anmeldung führt beim ausdrücklich registrierten Lesepfad zum Login.
Die aktuelle lokale Adminrolle wird zusätzlich im selben Snapshot geprüft, bevor
Gerätedaten gelesen werden. Sichtbarkeit des bestehenden Sidebarlinks ist keine
Autorisierung. Bestehender lokaler Frameworkguard bleibt enger als native LDAP-
Admins; keine LDAP-Admin- oder vollständige Terminal-Betriebsabnahme behauptet.

## Begrenzter Nachweis

Nur ein grober erfundener Projektions-/Inhaltsnachweis plus notwendige Syntax-
prüfung. Keine tatsächlichen Geräte-/Credentialdaten, Schlüssel, Geschäftsdaten,
Browser, Hardware, neue CI oder Aktivierung. Geschützte Browser-/SAPI-/Hardware-
Abnahme bleibt offen; eine native Referenzseite beweist keine Laravel-Abnahme.

Der aktuelle grobe Nachweis verwendet ausschließlich erfundene Werte in anonymem
SQLite-Speicher: native Kontakt-/Freigabelabels, sichere feste Ausgabefelder,
Escape-/Inhaltsprüfung des exakten neuen Kartenkörpers ohne Aktionen und erneute
aktuelle Adminprüfung trotz unverändertem erfundenem Cached-Actor. Das ist keine
reale Guard-Anmeldung; kein Login/Sessionwert oder Schlüssel wird eingesetzt.
Nur dieser Kartenkörper wurde gerendert, nicht das gemeinsame Layout. Der normale
CLI-Boot behielt nur `/up`; keine Anfrage, Geräteverbindung oder Hardware wurde
verwendet. Notwendige PHP-/kompilierte-Blade-Syntaxprüfung ersetzt keine offene
Betriebsabnahme. Ein früher Fehler der Autoload-Reihenfolge im Evidence-Helfer
vor Bootstrap ist separat dokumentiert; Anwendungscode blieb dabei unverändert.


## Getrennte Ergänzung: vorhandene Standortanlage, standardmäßig geschlossen

Basis des Zehnpfadpakets ist der tatsächliche Main
`4c2ceb1ba8aa35c9bf0c6f02378669433705616a`, Baum
`278a57667ef1c997c5164b08d80bbcceabf5b0b3`. Bezug #79/#81. Die vorhandene native
Karte **Standort ergänzen** aus `terminals.php` wird mit denselben Feldnamen,
Labels, CSS-Klassen und ihrem normalen POST/Redirect in die bestehende Blade-
Ansicht übernommen. Keine neue Oberfläche, Standortlöschung/-umbenennung,
Geräteanlage/-sperre, Modulfrei­gabe, Beschäftigten-/PIN-/Kartenaktion oder
Terminalprotokolländerung. Die vorhandene Geräteliste bleibt unverändert.

### Getrennte Fähigkeiten, kein schleichender Read-only-Cutover

`LocalWebRoutes::register(..., includeTerminalSites: true)` ist ein **neuer,
standardmäßig falscher** vertrauenswürdiger Schalter. Er verlangt die bereits
bestehende `includeTerminalDevices: true`-Seite; alleine wird er verweigert.
Das vorhandene reine Geräteprofil erhält dadurch ausdrücklich **keine** neue
POST-Route. Nur die separate Freigabe registriert `POST terminals.php` im
bestehenden Session-/`LegacyFormCsrf`-/`RefreshLocalAccount`-/`auth:web`-/
`auth.session`-Stack. Kein neuer öffentlicher Endpoint oder Defaultboot:
gewöhnliches Boot bleibt ausschließlich `/up`.

Zusätzlich bleibt `Interactions::terminalSites` standardmäßig false. Der neue
Anzeigeflag und die DTO-Zusätze besitzen inaktive Defaults; die native Karte ist
ohne ausdrückliche Anzeigefreigabe deaktiviert und erklärt den Hold. Der Flag
ist **keine Berechtigungsprüfung**. Auch eine handgeschriebene POST-Anfrage
benötigt die getrennt registrierte Route, gültige Session/CSRF und den aktuellen
lokalen Admin. Das bisher nur lesende Profil zeigt kein nachgeahmtes Erfolgs-
banner. Wird später nur die Anzeige ohne die passende Route aktiviert, ist das
keine funktionsfähige Schreibkomposition; beide Fähigkeiten gehören explizit
zusammen und werden nicht aus Anfragen oder Umgebungswerten abgeleitet.

Der optionale Raw-Form-Pfad erhält wie nativ die Eingaben ohne globale
Trim-/Leerstringnormalisierung; die fachliche Namensprüfung erfolgt im festen
Writer. Authverlust beim optionalen POST ergibt `401 text/plain`, beim
vorhandenen GET weiterhin den Loginredirect. Fachliche Formularfehler bleiben
wie nativ im HTML; Fehler enthalten keine SQL-, Pfad- oder Constraintdetails.
Nach bestätigtem Erfolg folgt ausschließlich `terminals.php?saved=1`.
Ein technischer Fehler behauptet weder Erfolg noch sicheren Rollback, sondern
fordert zum Neuladen und Prüfen des gespeicherten Stands auf. Kein Auto-Retry
oder neu erfundenes Idempotenzversprechen für Standortanlagen.

### Ein festes Kommando, ein vorhandener Writer

`LocalWebTerminalSites`/`LegacyLocalWebTerminalSites` erlauben genau
`action=site` und `name`, keine Actor-, Role-, Standort-ID-, SQL-, Schlüssel-
oder anderen Terminalaktionsfelder. Controller und Dienst verweigern
unbekannte/gemischte Aktionen; der Dienst verlangt einen lokalen Admin aus dem
bestehenden `web`-Guard, bevor ein Writer aufgelöst wird. Die lazy/scoped Bindung
im bestehenden `ChronoDomainServiceProvider` öffnet selbst keine Verbindung und
verweigert eine fehlende explizite `AttendanceWriter`-Bindung. Keine native
Bootstrapeinbindung, Schlüsselauflösung oder Ersatzverbindung.

Genau die neue feste Methode `AttendanceWriter::createTerminalSite()` verwendet
den unveränderten bestehenden Operation-/IMMEDIATE-/Same-PDO-/Fehlerlebenszyklus.
Innerhalb dieser Sperre wird der aktuelle Akteur erneut aus `users` gelesen und
auf lokale Adminrolle geprüft. Danach gelten die vorhandenen Regeln:
Namensstring trimmen, nicht leer und höchstens **100 Bytes**, fester
`INSERT INTO terminal_sites(name) VALUES(?)`. Literal `"0"` bleibt ein gültiger
nichtleerer Name. Nichtskalare/fehlende Namen werden statt einer PHP-
Array-/Objektkonvertierung kontrolliert als ungültig verworfen. Die Datenbank
behält die vorhandene Unique-Regel; Duplikate ergeben keinen zweiten Standort.

Keine neue Transaktionsverwaltung, frei übergebene SQL-Closure, Migration,
Schema-/Indexänderung, Daten-/Schlüsselkopie oder andere Schreibmethode. Der
Auditkontext heißt fest `web:{aktuellerAdmin}` / `terminals.php`; es wird **kein
neues Terminal-Standortjournal** behauptet oder ergänzt (#32 bleibt getrennt).
Alle bisherigen Writer-, Auth-, Setup-, Aufnahme-/LDAP-, Protokoll-/Geräte- und
Buchungsregeln bleiben unverändert. Vor einer späteren Integration mit anderen
Paketen sind die additiven Writer-/Registrarhunks seriell auf dem tatsächlichen
Main zu übernehmen und gemeinsam erneut zu prüfen.

### Grober synthetischer Nachweis und Grenzen

PHP **8.5.12** / Laravel **13.38.0**: Ein einziger wegwerfbarer privater
known-v2-Fixturebestand aus der bestehenden anonymen Quellfixture wurde mit
dem vorhandenen Offline-Adopter zugelassen. Die tatsächliche Dienst-/Writer-
Grenze prüfte fehlende/nichtadministrative/LDAP-Identitäten und unbekannte
Kommandos vor Writerauflösung, einen nur gecachten Admin gegen den aktuellen
DBrollenstand, ungültige/leere/zu lange Namen und Duplikatrollback. Zwei
rein erfundene Standorte wurden angelegt; Trim und Literal0 sowie der echte
Controller-Erfolgsredirect wurden geprüft. Andere vorhandene Tabellen,
synthetisches Original und Backup bleiben unverändert, die temporären Dateien
wurden anschließend entfernt. Das sind **keine** echten Konten/Standorte.

Zwei getrennte inaktive Prozesse prüfen Read-only-/Site-Routenprofile jeweils
nur einmal: alte Freigabe ohne POST, zusätzliche Route mit unverändertem
Middlewarestack, POST401/GET302 und Raw-Form-Vertrag, ohne DBverbindung oder
Requestdispatch. Drei inaktive Bladefälle prüfen den deaktivierten Default,
freigegebene Karte und escaped Fehlerausgabe. PHP-Syntax einschließlich
kompilierter Bladeausgabe und `git diff --check` sind grün. Keine neue Testsuite
oder GitHub-CI gestartet.

Die erste Nachweishilfe behandelte eine optionale im Fixture nicht vorhandene
Mappingtabelle fälschlich als vorhanden; nur ihr Digestlauf wurde auf dieselbe
Existenzprüfung wie der bestehende SourceSnapshot korrigiert. Ein weiterer
Nachweisversuch registrierte zwei inkompatible Profilcallback-Sätze im selben
Appprozess; sie werden jetzt getrennt gebootet, statt den zuerst registrierten
Callback als spätere Laufzeitkonfiguration zu missdeuten. Anwendungscode und
fachliche Prüfassertionen wurden für diese Nachweisrecovery nicht abgeschwächt.

Keine tatsächlichen Standorte, Geräte, Schlüssel, Zugangsdaten, Einstellungen
oder Runtime aktiviert/geändert. Kein HTTP-/Browser-/Login-/CSRF-Gesamtjourney,
keine visuelle/hardwarebezogene oder produktive Freigabe. Das bestehende
Fixtureprofil wurde nicht aktiviert oder umgestellt; #79/#81 bleiben offen.
Die Standortanlage alleine ist keine vollständige Terminalverwaltung oder
abgeschlossene Laravel-Umstellung.


### Additive Quellintegration auf Main `12a338e`

Das unabhängig quellgeprüfte Paket `a98efb58f82692224be8b63f77bf77513229e108`
wurde auf den tatsächlichen Main `12a338e2f9742fb71de453b5ecc469fb36fc007c`
neu verankert. Seine sieben übrigen Codepfade und der ursprüngliche
Dokumentationsinhalt sind unverändert übernommen; der Writer erhält
ausschließlich dieselbe feste `createTerminalSite()`-Methode.
Im Registrar wird `includeTerminalSites` **nach** den vorhandenen Setup- und
LDAP-Sync-Argumenten angehängt; bestehende positionsbezogene Aufrufe sowie
Setup-/LDAP-/Policy-/Save-Alias-/Verwaltungsänderungen bleiben erhalten.
Die Writer- und Registrarunion wird getrennt bytegenau zurückgerechnet.

Diese Übernahme führt keine lokale Funktionsprüfung aus und wiederholt die
oben ausschließlich historisch beschriebenen synthetischen Nachweise nicht.
Notwendige Syntaxprüfung und Quellerhaltung sind von einer weiterhin offenen
HTTP-/Browser-/CSRF-/Rollen-/Hardwareabnahme zu unterscheiden. Kein
Runtimeprofil oder Defaultschalter wird geändert; die bestehende Fresh-Web-
Komposition schaltet die Standortanlage nicht still ein. #79/#81 bleiben offen.


### Erneute additive Quellintegration auf Main `0264df96`

Das auf `12a338e` unabhängig quellgeprüfte Paket
`7a1438e908ba6a2415bb3dc0196457da5c19a6b0` wird auf Main
`0264df96f20451168632b2ac5259588b1709728d` neu verankert. Dieser tatsächliche
Main enthält die History-Relationen aus #208 und die standardmäßig geschlossene
gemeinsame Loginbudget-Quellunion aus #209. Beide bleiben vollständig erhalten.

Die sieben übrigen Codepfade sind weiterhin bytegleich zum geprüften Paket.
Der Writer erhält nur dieselbe feste `createTerminalSite()`-Methode; entfernt
man sie, bleibt jede aktuelle Main-Writerzeile einschließlich Budgetkonstruktor-/
Cookie-/IMMEDIATE-/Commitgrenze sowie Admin-/Setup-/LDAPoperationen unverändert.
Im Registrar wird der neue standardmäßig falsche Terminalschalter **nach**
`useSharedLoginBudget` angehängt. Die bestehende Budgetauswahlprüfung und der
bedingte Login-Throttle bleiben unverändert; alle acht Terminalergänzungen
werden separat auf den vollständigen aktuellen Main-Registrar zurückgerechnet.
Keine zuvor vorhandene Position oder benannte Option verschiebt sich.

Diese erneute Übernahme ist weiterhin ausschließlich Quellintegration. Es werden
nur notwendige PHP-/kompilierte-Blade-Syntax und statische Hash-/Objekt-/
Patch-/Baumprojektionen geprüft; keine lokalen Funktionstests oder neuen
GitHub-CI-Läufe, Runtime-/Konfigurations-/Konten-/Standort-/Geräte-/Schlüssel-
änderungen. Standard-/FreshWeb-/Loginbudget-Schalter und historische
Abnahmegrenzen bleiben unverändert. Eine unabhängige Prüfung bezieht sich
ausschließlich auf den finalen neuen Baum; Browser-/HTTP-/CSRF-/Hardware-
und Betriebsabnahme bleiben offen. #79/#81 bleiben teilweise offen.


## Ergänzung: vorhandene konfigurierte Terminal-Zugänge, nur lesend

Basis des getrennten Sechspfadpakets ist der tatsächliche Main
`b7c0fcbbbd9df783db88734dfab4eaa0e1f8da30`, Baum
`5ce4b85d88490ed4223960ce5fabe91a899553fd`. Bezug #79/#81. Die vorhandene native
Karte **Terminal-Zugänge** wird als reine Übersicht mit denselben Karten-/
Tabellenklassen und Spalten **Person**, **Personalcode**, **Standort**, **Zugang**
in die bestehende Blade-Seite übernommen. Keine neue Oberfläche, Suche,
Sortierung oder Seitengrenze. Ohne sichtbare Zeilen erklärt ein leerer Zustand
die leere Tabelle. Die nicht angeschlossenen Mutationen werden ausdrücklich
benannt; die native Aktionsspalte und Sperrformulare werden nicht imitiert.

`ChronoReads::adminTerminalCredentials()` ergänzt das Readerinterface und genau
eine Methode im vorhandenen Reader. Die unveränderte Snapshotgrenze lädt den
Akteur aktuell aus der Datenbank; erst danach verlangt die Methode **lokale
Adminrolle**, bevor sie Zugangsdatenmetadaten abfragt. Ein gecachter Admin beim
heute nichtadministrativen Konto, LDAP-Admin, Nichtadmin oder gelöschtes Konto
erhält keine Zeilen. Kein Personen-/Standortselector aus der Anfrage, keine
rekursive oder neue Teamsicht, kein Writer. Die bisherige Geräte-/Kontakt-
Projektion und alle anderen Reader-Methoden bleiben unverändert.

Der native Umfang bleibt ausschließlich die konfigurierten Zeilen aus
`employee_credentials JOIN users JOIN terminal_sites`. Beide bestehenden
INNER JOINs bleiben erhalten: fehlende Personen/Standorte werden nicht durch
neue Platzhalter/LEFT JOINs sichtbar gemacht oder automatisch repariert.
Es gibt keine zusätzliche Sortierung, Paginierung oder neue Vollbenutzerliste.

Die feste Abfrage liest nur Username, Personalcode, Standortname und vorübergehend
Freigabewert plus nichtgeheime Kartenhash-**Präsenz**. Letztere wird unmittelbar
in SQL als Boolean abgeleitet; NULL, leer und String `"0"` bleiben gemäß nativer
PHP-Wahrheit false. Der rohe Kartenhash wird nicht über PDO selektiert,
PINhashes werden überhaupt nicht ausgewählt. Die interne Zuordnung benötigt
User-/Standort-IDs nur für die JOINs, nicht in der Ergebnisliste.

Nur vier fertige Anzeigefelder verlassen den Reader:
`username`, `employee_code`, `site`, `access_label`. Das Label bleibt nativ:
freigegeben ohne Kartenpräsenz **PIN**, mit Präsenz **PIN + Karte**, andernfalls
**Gesperrt**. Das sind Methoden-/Freigabelabels, keine angezeigten PIN- oder
Kartenwerte und kein physischer Funktionstest. Interne Freigabe-/Präsenzwerte,
Hash-/PIN-/NFCwerte, Geräte-/Credential-IDs, Kontopasswörter/-tokens, Grants,
Generationen, Schlüssel und Bindkonfiguration werden nicht an das DTO gegeben.

Person, Personalcode und Standort sind trotzdem betriebliche Informationen.
Die Ansicht gehört ausschließlich in den bereits geschützten Adminbereich;
reale Inhalte nicht in GitHub/Prüfnachweise kopieren. Blade escaped alle Werte,
auch fehlerhafte oder HTML-haltige Altwerte. Keine neue Credentialsession,
Schlüsselauflösung, Native-Bootstrap-/Terminaldatei, Provider oder Route.

Controller und DTO transportieren nur diese ausgewählten Felder. Der neue
optionale DTO-Parameter `credentials` hat einen leeren Default, damit vorhandene
statische Zweiargument-Aufrufer weiter rendern. Der Controller verwendet ein
**benanntes** Argument. Bei serieller Integration mit der separaten Standortanlage
muss `credentials` hinter deren vorhandene optionale DTO-Parameter ergänzt und
der named-Argument-Aufruf erhalten werden; beide Blade-Karten werden getrennt
übernommen und der tatsächliche gemeinsame Baum erneut geprüft. Es wird keine
bereits erfolgte gemeinsame Integration oder Aktivierung behauptet.

### Grober synthetischer Nachweis und Grenzen

PHP **8.5.12** / Laravel **13.38.0**: Ein einziger anonymer MemoryOnly-
`chrono_fixture`-Bestand mit erfundenen Konten/Standorten/Zugangsmarkern prüfte
den tatsächlichen Reader einschließlich seiner Snapshot-/frischen Rollengrenze.
Ein verbindungslokaler Lese-Spy blieb bei allen nicht zugelassenen Identitäten
auf 0; erst der aktuelle lokale Admin las Zugangsdatenmetadaten. Der geschlossene
Standard ohne benannte `chrono`-Konfiguration verweigerte zuvor ohne Verbindung.
Die Guardidentitäten waren synthetisch gesetzt: kein tatsächlicher Login oder
Browser-/Cookie-/Middleware-/LDAP-Lauf wird daraus abgeleitet.

Sechs konfigurierte sichtbare Zeilen liefern genau die vier UI-Felder und die
drei erwarteten Labels; leere/NULL/`"0"`-Kartenpräsenz sowie zwei orphan-JOINfälle
sind enthalten. Synthetische PIN-/Kartenmarker und zusätzliche Credentialspalten
fehlen im Ergebnis. Query-only-Daten, SQL-Transaktionslevel und native Globals
bleiben unverändert. Zwei inaktive Bladefälle (konfigurierte und leere Liste /
alter DTO-Aufruf) rendern ohne DBverbindung: Escaping, keine Credentialmutations-
form und leere Defaults wurden geprüft. PHP-Syntax einschließlich kompilierter
Bladeausgabe sowie `git diff --check` sind grün; keine neue Testsuite/CI.

Keine tatsächlichen Konten, PINs, Karten, Hashes, Schlüssel, Standorte oder
Einstellungen gelesen/geändert. Kein HTTP-/Browser-/visueller-/Hardware- oder
Produktivnachweis, kein Test echter Terminalzugänge. Keine Reparatur verwaister
Beziehungen und keine Behauptung einer vollständigen Terminalverwaltung oder
Laravel-Umstellung; #79/#81 bleiben mit ihren übrigen Gates offen.


### Additive Quellunion auf Main `7fd329ee`

Das oben genannte Sechspfadpaket
`e5ff725f74e43966ce13a212add841ee0bec2067` wird mit unveränderter reiner
Readerlogik auf Main `7fd329eecf5716da5823a6308a164ffcaa2ef785` (Baum
`588b2fcf95a2b347685410ac420d202c7d858504`) neu verankert. Die oben stehende
synthetische Abnahme gehört zum historischen Quellpaket und wird nicht erneut
ausgeführt oder zur Betriebsfreigabe erklärt.

Die gemeinsame Controller-/DTO-/Bladeunion erhält die komplette bereits
gemergte Standortanlage: Dienstinjektion, festes Sitekommando, Fehlerbehandlung,
Redirect, die fünf vorhandenen DTO-Argumente, Defaults und beide bisherigen
Geräte-/Standortkarten bleiben unverändert. Der leere optionale `credentials`-
Parameter steht ausschließlich **danach**; der Controller übergibt ihn benannt.
Die neue read-only Zugangskarte wird bytegleich aus dem geprüften Paket
angehängt, ohne Sperr-/Speicheraktionen oder zusätzliche Formulare. Geräte- und
Zugangsprojektion bleiben getrennt; die neue Methode reautorisiert im vorhandenen
Snapshot, bevor nur vier nichtgeheime Anzeigefelder zurückgegeben werden.

Jede der sechs Main-Dateien wird durch Rücknahme ausschließlich ihrer
additiven Integrationshunks bytegenau auf den aktuellen Stand zurückgerechnet.
Routes, Writer, Provider, Terminal-/Budget-/LDAP-/Setupschalter, native Dateien,
Auth und alle übrigen Main-Einträge bleiben unverändert. Kein neues Profil,
keine Route, kein bestehender Laufzeitbestand und keine tatsächlichen Personen-,
PIN-, Karten-, Hash- oder Schlüsselwerte werden gelesen oder geändert.

Aktuelle Nachweise beschränken sich auf notwendige PHP-/kompilierte-Blade-
Syntax und statische Hash-/Objekt-/Patch-/Baum-/Methodenprojektionen. Keine
lokalen Funktionstests oder neuen GitHub-CI-Läufe; keine echte DB-, Login-,
HTTP-, Browser-, CSRF-, Hardware- oder Runtimeabnahme. Die unabhängige Prüfung
gilt nur für den finalen neuen Quellbaum. #79/#81 bleiben teilweise offen.
