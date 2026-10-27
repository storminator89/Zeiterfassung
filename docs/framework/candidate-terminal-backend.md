# FW-10/#81: konkretes Laravel-Terminalbackend, weiterhin ohne Route

Basis: Main `91880d9377306ec0d5a73354ba73c56ea4efee94`, Tree
`f9b09e2bf0e718c96704c46677702e34d2d7991a`, 27.10.2026.
[FW-10/#81](https://github.com/storminator89/Zeiterfassung/issues/81) bleibt offen.
Dieses Paket implementiert den bislang fehlenden Backendpfad mit dem gemeinsamen
nativen Regelwerk und dem vorhandenen Laravelwriter. Es aktiviert weder einen
Endpunkt noch Schlüssel, Geräte, Konten oder Produktionskonfiguration.

## Genau fünf Dateien

- `AttendanceWriter.php`: ausschließlich zwei zusätzliche terminalspezifische
  Methoden mit demselben gefangenen PDO und dem vorhandenen privaten
  `executeLocked()`-Lebenszyklus. Bestehende Methoden und dessen
  Commit-/Rollback-/Poisoninglogik bleiben unverändert.
- `Terminal/LegacyTerminalProtocol.php`: konkrete `TerminalProtocol`-Implementierung,
  die genau einen injizierten Writer für `requireEnabled()` und `handle()` behält.
- `Terminal/AdmittedTerminalKeys.php`: zwei explizite vertrauenswürdige verzögerte
  Quellen für die vorhandenen getrennten Terminalroots; kein Dateiloader.
- `ChronoDomainServiceProvider.php`: lazy/scoped Fabrik für das konkrete Backend.
  Sie verlangt zuerst ein ausdrücklich gebundenes `AdmittedTerminalKeys` und einen
  bereits zugelassenen `AttendanceWriter`. Erst nach Auflösung/Typprüfung des
  Schlüsselquellenobjekts wird genau einmal ein Writer aufgelöst. Ohne diese
  Bindungen wird vor Datenbanköffnung verweigert. Registrierung öffnet weder
  Datenbank noch Schlüssel und liefert keine Rootquelle oder Route.
- Diese Dokumentation.

Native Endpunkte, gemeinsam extrahierter Kern, Transportkapseln/-controller,
Auditklassen, Runtimeadmission, UI, Routen, Config und Bootstrap bleiben unverändert.
Keine zweite SQLconnection, kein PDOgetter, kein öffentlicher beliebiger
Schreibcallback und keine neue Transaktionsverwaltung.

## Ein PDO; nicht eine große Transaktion

`terminalRequireEnabled()` prüft ausschließlich den Idle-Zustand und den nativen
Modulstatus auf dem bereits vorhandenen Writer-PDO. Wiederholte Aufrufe ändern
keinen Zustand. Der Transport kann damit vor Methode/Body prüfen; `terminal()`
wiederholt danach die bisherige native Eingangskontrolle auf demselben Writer.

| Phase | Vorhandene Regeln / Transaktionsgrenze |
|---|---|
| Pair | IP-Limit autocommit **vor** dem Lock; `executeLocked(terminal_pair_locked)` besitzt den einzigen Laravel-IMMEDIATE-Lock. Erst nach Commit erfolgt die verzögerte Schlüsselableitung der Antwort. |
| Auth | Header-/Zeit-/Nonceform, Enabled-Gerätelookup, beide HMACschichten und Nonceverbrauch über `terminal_auth_core`, **ohne** nachfolgenden Eventlock vorzuverlegen. |
| Identify | Gerätelimit, Form, optionale Kartenquelle/-suche, Personenlimit, Credential-/Standort-/PIN-/Kontoprüfung und Grant/Status bleiben über `terminal_identify_core` außerhalb des Eventlocks. |
| Heartbeat/Event/Unbekannt | Nur dieser nachfolgende Zweig nutzt `executeLocked(terminal_dispatch_locked)`: erneuter Modul-/Generationscheck, Grant/Credential, feste Transition, Grantverbrauch und Lastseen bleiben gemeinsam unter genau einem Lock. |

Ein fehlgeschlagenes Event nimmt den vorher autocommitteten Nonceverbrauch nicht
zurück. Es gibt keinen Handlerretry, keine neue Event-ID und keinen Aufruf von
`terminal_handle()` oder `AttendanceWriter::transition()` innerhalb dieses Locks.
Die bestehenden Laravelguards gegen Nesting, Handleersatz und misslungenen
Commit/Rollback bleiben zuständig.

Der feste Auditrunner reicht nur den im gemeinsamen Kern aus Device/Grant
abgeleiteten Actor und die bisherige Begründung an das vorhandene
`AuditContext::run()` weiter. Er wird erst nach nativer Validation und gespeicherter
Replayentscheidung aktiviert. Wiederholung öffnet keinen neuen Auditscope; die vom
Writer ohnehin injizierte Kontextinstanz wird nicht ersetzt. UDFs und alle
Buchungsschreibvorgänge benutzen denselben PDO; vorhandene Kontext-/Globalrestore-
Grenzen bleiben bestehen. Unvertrauenswürdige zusätzliche Actor-/Sourcefelder
werden nicht übernommen.

## Vorhandene Rootwerte: expliziter externer Vertrauensvertrag

`AdmittedTerminalKeys` nimmt ausschließlich zwei vertrauenswürdige parameterlose
Closures entgegen: die bestehende `terminal-devices`- und die bestehende
`terminal-cards`-Quelle. **Es wird keine solche Quelle mitgeliefert oder beim Boot
gebunden.** Ihre Identität, Berechtigung, bestehende Dateiprovenienz, Trennung und
operationslokale Lebensdauer müssen vor einer Aktivierung separat zugelassen
werden. Die Klasse kann diese Herkunft nicht aus einem zurückgegebenen Wert
beweisen; Formatprüfung ist keine Dateiprovenienzprüfung.

Konstruktion löst keine Quelle auf. Erst am bisherigen nativen Nutzungspunkt wird
die benötigte Quelle synchron aufgerufen und exakt auf 64 kleine Hexzeichen
geprüft. Die Zeichen bleiben HMACtext, keine Binärdekodierung. Nicht benötigte
Kartenquellen werden nicht aufgerufen. Es gibt keine Pfadableitung, Datei-/Env- oder
Framework-/JWTfallbackquelle, Erzeugung, Rotation oder Credentialänderung. Die
Quellen dürfen weder Requestdaten noch Web-/APIidentität als Rootauswahl verwenden
und keinen eigenen Datenbank-/Ausgabe-/Bootstrapnebenpfad öffnen.

Die Klasse speichert nur die Resolver, **keine aufgelösten Rootwerte**. Werte bleiben
lokale Rückgaben am Nutzungspunkt; keine Ablage in Config, Manifest, Log oder
serialisiertem Objekt. Quellen dürfen nicht über eine andere Requestlebensdauer
hinweg ungeprüft weitergereicht werden. Externe Closures können selbst Zustand
halten; das muss deren gesonderte Zulassung prüfen. PHP garantiert keine sichere
Speicherlöschung/Zeroisierung von Strings oder Kopien; eine solche Garantie wird
nicht behauptet. Debugausgabe des Quellenobjekts enthält keine Resolverwerte;
Serialisierung wird verweigert. Quellenfehler werden ohne vorherige Exception
oder sensible Details als technische Admissionverweigerung weitergegeben.

## Ausdrückliche technische Cleanupabweichung beim Pairing

Scheitert die Rootauflösung **nach** erfolgreichem Pairingcommit, bleibt die
Pairingänderung committed und der Transport gibt weiterhin den vorhandenen
unbestätigten Fehler zurück: HTTP 503 und
`{"success":false,"message":"Buchung nicht bestätigt. Verbindung prüfen und denselben Vorgang erneut versuchen."}`.
Keine Erfolgsmeldung, kein Retry und keine Behauptung eines Rollbacks.

Das neue Backend imitiert bewusst **nicht** den nativen ungültigen rohen
`ROLLBACK` nach bereits erfolgtem Commit und dessen Maskierung der ursprünglichen
technischen Ausnahme. Laravels bestehender Writer ist nach seinem Commit bereits
idle. Diese interne Cleanupabweichung verändert weder den committed Zustand noch
das nach außen generische Status-/JSONformat. Native Wrapper bleiben unverändert.

## Eng begrenzte Prüfung und offene Gates

PHPsyntax und ein grober synthetischer Ablauf mit einer neu erzeugten privaten
TEMP-fixture, die **vor** Runtimeöffnung bereits als bestehende zugelassene
Rollback-Journaldatei vorliegt. Der echte vorhandene Writer wird nicht für
`:memory:` oder ungenehmigte Settings aufgeweicht. Künstliche Resolver liefern
nur synthetische Hextexte. Der Ablauf prüft lazy Admission, echten
Provider-/Protocol-/Writerpfad, Modul/Nonce/Limitreihenfolge, Event/Audit/Replay und
einen semantischen Fehlerrollback; kein injizierter Partialwrite-/Commitfault. Es
ist keine vollständige Protokoll-/Writer-/Konkurrenzabnahme.

Kein echtes `app_secret()`, keine Schlüsseldatei, keine realen Daten, Konten,
Credentials oder Geräte; kein Pairing, erfolgreicher Identify/Login, HTTP-,
Netzwerk-, Server-, Browser-, Agent- oder Hardwarelauf. Keine CIanforderung oder
Bereitstellung. Nur synthetische Tempfixtures, keine Betriebsbindungs-/Configänderung.

Echte getrennte Rootquellenzulassung/-bindung, unterstützte Request-/Worker-
Lebensdauer, Rawmethod-/Rawbody-/Middleware-/Responsevorbereitung, veröffentlichte
und Geräte-/Hardwareabnahme sowie Routing/Cutover bleiben offen. Die vorhandene
v1-Signatur umfasst weiterhin ID, Zeit, Nonce und Rawbody, nicht Methode oder Pfad.
Keine neue Authmethode und keine Abnahmebox von #81 wird als erledigt erklärt.
