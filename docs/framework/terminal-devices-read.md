# #79 / #81 – vorhandene Terminal-Geräteliste, nur lesend

Begrenzte Migration der vorhandenen **Geräte**-Karte aus `terminals.php` mit
bestehendem Header, Standortspalte, Reihenfolge, Tabellen-/Kartenklassen und
Beschriftungen. Keine zweite Terminalverwaltung und keine neue URL. Die Ansicht
kennzeichnet sich ausdrücklich als nur lesend. Kein Koppeln/Sperren, Modulschalter,
Standortanlage, Personalcode, PIN, NFC-Zuordnung, Token oder Geräteschlüssel.
Keine inaktiven Scheinaktionen; die bisherige Sperr-Formularspalte fehlt bewusst.
Auch native `saved`-/Pairing-Banner werden nicht imitiert, und der native
`terminal_pairing`-Sessionwert wird weder gelesen noch verbraucht.

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
