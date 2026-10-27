# FW-10/#81: mechanisch geteilter nativer Terminalkern

Basis: Main `c086daad1e45277214f69cb4a28d139accc30158`, Tree
`6c0d614dd891564c1bae922453fc3cb3e3630120`, 27.10.2026.
[FW-10/#81](https://github.com/storminator89/Zeiterfassung/issues/81) bleibt offen.
Dies ist eine native Abhängigkeitsauskopplung, **keine aktivierte Laravel-
Terminalmigration** und keine Geräte-, Schlüssel-, Routing- oder Betriebsfreigabe.

## Genau vier Dateien; ein Regelwerk statt Protokollkopie

- `lib/terminal_core.php` enthält die tatsächlichen gemeinsamen Definitionen:
  Schlüssel-/Kartenableitung, Credentialversion, Limits, Authentifizierung,
  Identify sowie die gesperrten Pairing-/Heartbeat-/Eventfragmente. Kein
  Security-/Configbootstrap, Schlüsseldateizugriff, Verbindungsaufbau oder
  Transaktionsmanager. `attendance.php` wird nur für die gemeinsamen Definitionen
  geladen; kein Schema oder Datensatz wird beim Include angelegt.
- `lib/terminal.php` behält die bestehenden öffentlichen Signaturen und die native
  Disposition mit `BEGIN IMMEDIATE`, `COMMIT` und `ROLLBACK`. Die bestehenden
  Endpunkte und `terminals.php` verwenden dieselben Wrapper; insbesondere bleibt
  `terminal_card()` für die Credentialverwaltung verfügbar.
- `lib/attendance.php` ergänzt ausschließlich einen optionalen, hinten angefügten
  `?Closure $auditRunner=null` an `attendance_transition_locked()`. Die bestehenden
  Aufrufer benötigen keine Änderung. Der feste Mutationsrest wird am bisherigen
  Auditpunkt gekapselt; seine Fachregeln und Rückgaben bleiben unverändert.
- Diese Dokumentation.

## Explizite, weiterhin verzögerte Schlüsselabhängigkeiten

`terminal_key_from_source()` und `terminal_card_from_source()` erhalten getrennte
vertrauenswürdige, parameterlose Closures. Sie wählen weder Dateiname noch
Schlüssel anhand von Anfragedaten aus. Die nativen Wrapper verwenden weiterhin
exakt `app_secret('terminal-devices')` beziehungsweise `app_secret('terminal-cards')`;
dessen vorhandenes, möglicherweise erzeugendes Verhalten wird hier nicht geändert.
Der gemeinsame Kern lädt `security.php`/`config.php` jedoch nicht und ruft selbst
kein `app_secret()` auf.

Die vorhandenen 64 kleinen Hexzeichen werden **als Text** verwendet, nicht mit
`hex2bin()` dekodiert. Geräteableitung `id:generation`, Kartenbehandlung
`strtoupper(trim(card))`, Credential-JSONreihenfolge und beide v1-HMACschichten
bleiben gleich. Der Geräteschlüssel wird erst nach Header-/Zeit-/Nonceform und
Enabled-Gerätelookup angefordert; ohne Gerät wird keine Quelle aufgerufen. Der
Kartenschlüssel wird erst nach Gerätelimit und Identifyformprüfung angefordert,
und nur bei einer nichtleeren Karte. Es gibt keinen neuen Loader, keine Dateianlage,
Erzeugung, Rotation oder Framework-/JWTschlüsselwiederverwendung.

**Besonders unverändert:** Pairing aktualisiert unter dem nativen Lock, führt
`COMMIT` aus und leitet erst danach im bisherigen `try` den Antwortschlüssel ab.
Auch die vorhandene technische Fehler-/Rollbackgrenze nach diesem Commit wird
hier nicht repariert oder vorverlegt.

## Transaktions- und Auditvertrag

`terminal_pair_locked()` und `terminal_dispatch_locked()` sind interne
Domänenfragmente: Der vertrauenswürdige Aufrufer besitzt bereits genau eine aktive
IMMEDIATE-Transaktion auf genau diesem PDO. Sie beginnen, committen und rollen
keine Transaktion zurück. Der native Wrapper bleibt ihr Transaktionseigentümer.
Authentifizierung mit Nonceverbrauch sowie persistente Pairing-/Identifylimits
bleiben an ihren bisherigen Stellen **außerhalb** des nachfolgenden Eventlocks.

Das gesperrte Dispatchfragment prüft erneut Modul, aktuellen Gerätestatus und
Generation. Geräte-/Standortbindung, Grantablauf und erlaubte Eventwiederholung,
Credentialversion, `attendance_transition_locked`, Grantverbrauch und Lastseen
bleiben zusammen unter diesem Lock und in derselben Reihenfolge. Der
Idempotenznamensraum bleibt `terminal:{device}`; vorhandene Antwort, Serverzeit,
Konflikte, Pausen- und Überschneidungsrückmeldung werden nicht neu implementiert.

Der optionale Auditrunner ist **vertrauenswürdige interne Komposition**, kein
Requestfeld und keine öffentliche beliebige SQL-/Schreibschnittstelle. Er erhält
nur den intern abgeleiteten Actor `terminal:{device}:user:{grantUser}`, die bisherige
Begründung `Stempelereignis {action} / {eventId}` und die feste synchrone
Mutationsfortsetzung. Diese muss genau einmal ausgeführt und ihre Rückgabe
unverändert weitergegeben werden. Actor, Quelle und Callback dürfen niemals aus
zusätzlichen Anfragedaten übernommen werden.

Die Runnergrenze liegt in `attendance_transition_locked()` exakt am bisherigen
Zuweisungspunkt der Auditglobals: **nach** Event-ID-/Aktions-/Kontoprüfung und
Idempotenzvergleich beziehungsweise gespeicherter Wiederholungsantwort. Eine
verweigerte oder wiederholte Anfrage ruft den Runner nicht vorzeitig auf. Ohne
Runner setzt der native Fallback beide Auditglobals wie bisher; auch ihr bisheriges
Weiterbestehen nach Erfolg/Fehler wird nicht verändert. Mit Runner kann ein späterer
Frameworkwriter denselben fest abgeleiteten Kontext an `AuditContext::run()`
binden, ohne sich auf Globals als Ersatz für `AuditFunctions` zu verlassen.

## Offene getrennte Freigaben

Es gibt weiterhin **keine** `TerminalProtocol`-Implementierung, Containerbindung,
Route, Bootstrap-/Provideränderung oder Frameworkwriterverdrahtung. Der heutige
`terminal_handle()` darf weiterhin nicht innerhalb von `AttendanceWriter`
aufgerufen werden. Der spätere terminal-spezifische Writer muss Laravels bereits
vorhandene Connection-/Transaktions-/Fehlerlebensdauer auf demselben zugelassenen
PDO nutzen; kein zweiter Writer und keine öffentliche beliebige Schreibclosure.

Bestehende, getrennte `terminal-devices.key`-/`terminal-cards.key`-Zulassung,
operationslokaler Runtime-/Audit-/Writerkontext, Rawmethod-/Rawbody-/Middleware-
und Antwortvorbereitungsabnahme sowie veröffentlichte/hardwarebezogene
Ende-zu-Ende-Abnahme bleiben offen. Das native v1-Protokoll signiert weiterhin
ID, Zeit, Nonce und rohe Bodybytes, **nicht** Methode oder Pfad. Keine neue
Sicherheitsbehauptung und keine Abnahmebox von #81 wird daraus abgeleitet.

## Eng begrenzte Nachweise

PHPsyntax, quellbasierte Auskopplungs-/Unverändertheitskontrolle und ein grober
synthetischer Vorher-/Nachher-Nachweis im Speicher: verzögerte künstliche
Schlüsselquellen, ausgewählte HMAC-/Nonce-/Limit-/Evententscheidungen, native
Auditwerte, Wiederholung und Rollback; zusätzlich die neue Runnergrenze. Für den
Vorher-/Nachher-Vergleich ersetzen ausschließlich isolierte Supportkopien den
nativen Schlüsselaufruf durch eine künstliche In-Memory-Quelle. Weder echte
`app_secret()`-Aufrufe noch Security-/Configbootstrap oder Schlüsseldateien werden
ausgeführt. Kein vollständiger Protokoll-, Writer-, Konkurrenz- oder
Sicherheitsnachweis.

Keine realen Schlüssel, PINs, Karten, Grants, Geräte oder Produktivdaten; kein
Pairing, Login, HTTP-/Netzwerk-/Server-/Browserlauf, Live-DBzugriff, CIstart oder
Deployment. Nur künstliche In-Memory-Fixtures; keine Betriebskonfiguration geändert.
