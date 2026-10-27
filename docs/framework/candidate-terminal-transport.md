# FW-10/#81: inaktive Terminal-Transportvoraussetzung

Basis: Actual Main `729250b0c866fcfc899eff47b052997144cf3842`, Tree
`6921e82dec1e36dd5896c7bcf3ddae4a5f14eddb`, 27.10.2026.
[FW-10/#81](https://github.com/storminator89/Zeiterfassung/issues/81) bleibt
**offen**. Dieses Paket ist weder eine implementierte Protokoll-/Backendmigration
noch eine Terminal-, Geräte-, NFC-, Routing- oder Betriebsfreigabe.

## Genau fünf neue, standardmäßig unerreichbare Dateien

- `framework/app/Terminal/TerminalProtocol.php`: verpflichtender Vertrag für einen
  vertrauenswürdig bereitgestellten, operationslokalen nativen Protokolldienst.
  **Keine Implementierung und kein Containerbinding vorhanden.**
- `framework/app/Terminal/LegacyTerminalRequest.php`: unveränderliche Kapsel
  nichtvertrauenswürdiger Transportdaten; keine Geräte-/Mitarbeiteridentität.
- `framework/app/Terminal/LegacyTerminalResponse.php`: bestehendes JSONformat mit
  Symfony/Laravel-Response, nicht Laravels Standard-422-/Redirectformat.
- `framework/app/Http/Controllers/Terminal/LegacyTerminalController.php`: dünner
  Controller mit Laravel-Request und zwingender Vertragsinjektion.
- Diese Dokumentation.

Kein Provider, Route-Registrar, Bootstrap, Defaultbinding, Fallback oder neuer
Konfigurationsschalter. Ohne ausdrücklich zugelassenen Dienst scheitert bereits
die Containerauflösung; sie ruft keinen Legacy-Bootstrap auf und öffnet weder
Datenbank noch Schlüsseldatei. Die bestehenden PHPendpunkte, Agent/Kiosk, UI,
Guards, Sitzungen, Host-/Securitykonfiguration und Daten bleiben unverändert.

## Transportgrenze, nicht Sicherheitsnachweis

Der Controller ruft zuerst `requireEnabled()` im selben operationslokalen Dienst
auf. Erst danach prüft er wie `terminal_api.php`: rohes `REQUEST_METHOD` exakt
`POST`, deklarierte Länge >8192, tatsächliche Rohdatenlänge >8192 und natives
`json_decode(..., true)`/`is_array`. Auch JSONlisten werden deshalb nicht neu
verboten. Er liest über `Request::getContent(true)` höchstens **8193 Bytes**;
keine unbeschränkte Bodyabfrage, Form-/Queryzusammenführung oder Neuserialisierung.
Ein schon vorgelagert gepufferter Requestbody wird damit nicht nachträglich
entpuffert; dies ersetzt kein späteres Webserver-/Middleware-Bodylimit.

Die Kapsel bewahrt Bodybytes sowie die Serverwerte für ID, Zeit, Nonce, Signatur,
Methode, `REQUEST_URI` und `REMOTE_ADDR`. Kein Proxy-IPresolver und keine
Methoden-/Pfadnormalisierung. Geparstes JSON dient nur der Fachdisposition, nie
als Ersatz der signierten Rohdaten. Der native v1-HMAC lautet weiterhin genau:

```text
terminal-v1\n{id}\n{timestamp}\n{nonce}\n{rawBody}
```

**Methode und Pfad sind im Istprotokoll NICHT signiert.** Die Kapsel macht sie
nicht zu signierten Feldern. Keine Ergänzung ohne versionierten Clientplan.
Der Controller erzeugt/prüft selbst weder HMAC noch PIN, Kartenhash oder Grant;
ein künstlicher Dienst ist kein Nachweis dieser Prüfungen.

Normale Erfolge verwenden natives `json_encode(..., JSON_THROW_ON_ERROR)`.
Native `AttendanceError`-Status/Meldungen werden im vorhandenen
`{success:false,message}`-Format weitergegeben. Technische Fehler und gescheiterte
Erfolgskodierung liefern dieselbe generische unbestätigte 503-Antwort; keine
sensiblen Ausnahme-, Header- oder Bodydaten werden hier geloggt. Der fehlende
Dienst scheitert vor Controlleraufruf, nicht als behauptete Protokollantwort.

## Nächste getrennt zu prüfende Arbeit: echter nativer Dienst

1. **Mechanische native Kernauskopplung** aus `lib/terminal.php`, ohne
   Protokollfork. Bestehende PHPwrapper müssen dieselben Funktionen verwenden.
   Explizite Transport-/Secretabhängigkeiten ersetzen versteckte `$_SERVER`- und
   `app_secret()`-Zugriffe, ohne Nutzereingaben zur vertrauenswürdigen Identität
   oder zum Auditkontext zu machen. Nicht den heutigen `terminal_handle()` mit
   seinem eigenen `BEGIN IMMEDIATE` in den Frameworkwriter einschachteln.
2. **Ein-PDO-Integration in #75 `AttendanceWriter`** statt öffentlicher beliebiger
   Schreibcallbacks, zweiter Connection oder doppelter Transaktionsverwaltung.
   Event-Lock enthält aktuelle Enabled-/Generationsprüfung, Gerät-/Sitebindung,
   Grant-/Credentialversionsprüfung, `attendance_transition_locked`, Grantverbrauch
   und Lastseen gemeinsam. Dessen `terminal:{device}`-Idempotenznamensraum,
   gespeicherte Wiederholungsantwort, Serverzeit, Konflikte und Pausenrückmeldung
   bleiben unverändert. Scoped Audit muss auf genau diesem PDO die bestehende
   Identität/Begründung liefern; rohe Auditglobals allein reichen dafür nicht.
3. **Bisherige Reihenfolge bewahren:** Nonceprüfung/-verbrauch vor dem
   Identify-/Eventpfad, Zeitfenster ±90 Sekunden, Nonceablauf +180 Sekunden.
   Persistente Limits wie bisher außerhalb des nachfolgenden Eventlocks:
   Pairing IP20, Identify Gerät60/Person10, 600-Sekunden-Buckets. Nicht den gesamten
   Handler in einen neuen Lock wickeln oder technische Fehler still wiederholen.
   Pairing-Einmalcode/Expiry, Generationswiderruf, achtstellige PIN,
   Karten-/Credentialhashes, zehnsekündige Grants, Standort-/Kontoprüfungen und
   erlaubtes Retry nur derselben Event-ID bleiben native Fachregeln.
4. **Explizite bestehende Schlüsselzulassung:** getrennte bestehende
   `terminal-devices.key`-/`terminal-cards.key`-Geheimnisse im vorhandenen
   64-kleinen-Hexformat; keine automatische Pfadableitung/Dateianlage, Erzeugung,
   Rotation, Kopie in Git oder Wiederverwendung von Framework-/JWTschlüsseln.
   `security.php`/`config.php` sind keine Frameworkloader: `app_secret()` nutzt
   `c+` und kann Geheimnisse erzeugen. Dieses Paket includiert beides nicht.
5. **Erst danach Container-/Routingabnahme:** ausdrücklich zugelassene
   operationslokale Implementierung, Runtime-/Writer-/Auditadmission und
   unveränderter `/terminal_api.php`-Transport; keine Websession-/CSRF-/Bearer-
   Ersatzanmeldung. Laravel-Routenauswahl, Methodenoverrides und spätere
   Responsevorbereitung sind hier nicht ausgeführt oder normalisiert und müssen
   vor Aktivierung Ende-zu-Ende geprüft werden. Ebenso vorgelagertes
   SAPI-/Middlewarepuffern oder JSONbag-/TrimStrings-Zugriffe, Bodylimits,
   Fehlerpräzedenz und vorhandene Sicherheitsheader ohne Host-/Securityänderung
   nachweisen. Nicht schon aufgrund dieses Controllers routen oder freigeben.

## Tatsächlich begrenzte Prüfung dieses Pakets

Nur PHPsyntax der vier neuen Klassen und ein kleiner speicherinterner
Fake-Dienst-Nachweis: Byte-/Serverwertweitergabe, Modul-vor-Methode/Body,
8192-/8193-Grenze, JSON-/Statusformat und verweigerte ungebundene Auflösung.
Keine Protokoll-/Writer-/Audit-/Konkurrenzabnahme, keine HMAC-/PIN-/Grantprüfung,
kein Pairing, kein Token, keine Schlüsselauflösung und keine Datenbank.
Kein HTTP-/Netzwerk-/Browser-/Agent-/NFC-/Hardwarelauf, kein Sign-in, keine
Konto-/Geräteerstellung, Sessioninjektion, CI-Anforderung oder Bereitstellung.
Die Abnahmeboxen von #81 werden damit **nicht** als erfüllt markiert.
