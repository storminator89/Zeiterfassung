# Optionales Praxis-Terminal – Online-Betrieb

Implementiert: gemeinsame Ereignisbuchung für Web/API/Terminal, tatsächliche Pausen, Geräte-Pairing und Sperrung, Personalcode/PIN sowie NFC-Kennung plus PIN, lokaler Gerätedienst mit Kiosk, Korrekturanträge mit Freigabe. Nur die Gerätefunktion ist standardmäßig deaktiviert; Web-Zeiterfassung, echte Pausen, Arbeitszeitmodelle und Historie sind immer aktiv. Ein konkreter NFC-Leser wurde nicht angeschlossen; die Tastatureingabe eines USB-HID-Lesers wurde im Browser simuliert. Kryptografische NFC-Karten und Offline-Buchungswarteschlangen sind nicht implementiert. Eine getrennte, ausdrücklich optionale lokale Klärungsablage für unbestätigte Online-Vorgänge ist als Quellcode vorbereitet; sie sendet alte Ereignisse niemals automatisch erneut (siehe unten).

## Server aktivieren

1. Die vollständige private Datenablage sichern, einschließlich SQLite-Datei und aller `.key`-Dateien. PHP 8.3 mit PDO SQLite ist erforderlich.
2. Den Server mit `APP_TERMINAL_ENABLED=1` starten. Beim Docker-Betrieb `-e APP_TERMINAL_ENABLED=1` ergänzen und das bisherige private Datenvolume weiterverwenden. Es werden versionierte Tabellen hinzugefügt; bestehende Arbeitszeiten bleiben unverändert. Ohne Gerätebetrieb muss die Variable nicht gesetzt werden.
3. Vorhandene offene Arbeitszeiten im bisherigen System beenden. Als Administrator `terminals.php` öffnen (Navigation: Praxis-Terminals) und das Modul aktivieren. Die Aktivierung gilt für die gesamte Installation; Standorte sind keine getrennten Mandanten.
4. Standorte und persönliche Zugänge anlegen: bestehende Person auswählen, eindeutigen Personalcode vergeben, achtstellige PIN festlegen, optional NFC-Kennung einlesen. Die PIN separat und vertraulich übergeben. Das Modul zeigt keine Mitarbeiterliste am Terminal. Beim Ersetzen eines Zugangs werden alte Identifikationsfreigaben ungültig. Eine leere NFC-Kennung entfernt die bisherige Karte.
5. Ein Gerät mit Standort anlegen. Die Verwaltung zeigt einmalig eine Geräte-ID und einen zehn Minuten gültigen Kopplungscode. Nach Verwendung ist der Code ungültig. Bei verlorener Kopplung das Gerät sperren und einen neuen Eintrag anlegen.

Unabhängig vom Gerätemodul führt die Startseite zu `attendance.php`. Kommen und Gehen der bisherigen Web/API-Clients werden ebenfalls durch denselben Buchungsdienst geleitet. Erfasste Zeiten lassen sich auch nach Deaktivierung nicht über alte Schreib-/Löschendpunkte ändern. Historische, vollständige API-Nachbuchungen werden immer mit einem Hinweis auf Korrekturanträge abgewiesen. Der neue API-Endpunkt `POST /api.php/attendance` akzeptiert `action`, `event_id` und optional `confirm_end_pause` unter der bestehenden Benutzer-JWT-Authentifizierung.

## Gerätefreigabe und Kontaktstatus

Die bestehende Geräteübersicht trennt **Freigabe**, **Kontaktstatus** und **Letzter Kontakt**. „Freigegeben“ beschreibt nur die Geräteberechtigung, nicht die Verbindung. Der Agent wartet zwischen Heartbeats 30 Sekunden; eine Anfrage hat bis zu acht Sekunden Zeit. Der letzte serverseitig gespeicherte Kontakt (Kopplung, Heartbeat oder bestätigte Buchung) gilt bis einschließlich 90 Sekunden als aktuell, danach als veraltet. Fehlende oder ungültige/zukünftige Kontaktzeiten gelten nicht als aktueller Kontakt. Die Anzeige ist eine Momentaufnahme beim Laden und garantiert auch bei aktuellem Kontakt weder laufenden Kiosk noch Leser-/Netzfunktion. Nach Neuladen wird sie neu bewertet; automatische Überwachung/Alarmierung und die reale Hardwareabnahme bleiben offen. Freigaben, Gerätesperren und Buchungsprüfungen werden durch diese Anzeige nicht verändert.

## Gerätedienst auf Linux installieren

Voraussetzungen: verwalteter Linux-PC/Touch-Terminal, Python 3.10 oder neuer, Chromium/Firefox im Kiosk-Modus und HTTPS-Verbindung zur App. Für einen USB-HID-Leser muss das Gerät eine Kennung gefolgt von Enter als Tastatureingabe liefern. Browser-Web-NFC ist nicht erforderlich. Die Anwendung unterstützt keine beliebigen proprietären Leser-SDKs automatisch.

Den Ordner `terminal-agent/` separat nach `/opt/chrono-terminal` kopieren. Eigenen unprivilegierten OS-Benutzer und geschütztes Datenverzeichnis verwenden. Beispiel als dieser Benutzer:

```sh
python3 /opt/chrono-terminal/agent.py \
  --config /var/lib/chrono-terminal/device.json \
  --pair --url https://zeit.praxis.example --device-id 1
```

Der Kopplungscode wird verdeckt im Terminal abgefragt, nicht als Kommandozeilenargument übergeben. Die resultierende Datei muss dem Dienstbenutzer gehören und darf nur für ihn lesbar sein (`0600`); Elternverzeichnis `0700`. Der Server benötigt denselben dauerhaft gesicherten privaten Schlüsselbestand. Bei Verlust/Rotation des Geräteschlüssels auf dem Server alle Geräte neu koppeln.

Start:

```sh
python3 /opt/chrono-terminal/agent.py --config /var/lib/chrono-terminal/device.json
```

Den Browser auf **`http://127.0.0.1:8765`** öffnen und im OS-Kiosk-Modus sperren. `localhost` oder fremde Hostnamen sind absichtlich nicht erlaubt. Der Gerätedienst bindet ausschließlich Loopback; er ist kein öffentlicher Webserver und darf nicht per Portfreigabe oder Reverse Proxy im Netz veröffentlicht werden. Lokale Browseranfragen benötigen passenden Host, Origin und einen CSRF-Token. Nur der Gerätedienst kennt den Geräteschlüssel und die kurzlebige Personenfreigabe; im Browser werden keine Geräte- oder Benutzer-JWTs gespeichert.

`chrono-terminal.service.example` ist eine Vorlage für systemd; Benutzer, Pfade, Dateirechte und Browser-Autostart vor Verwendung einrichten. Dienst als unprivilegierter Benutzer starten, automatische Updates und eingeschränkte Systemmenüs konfigurieren. Der PC selbst ist eine Vertrauensgrenze: lokale Administratoren können Eingaben lesen. Ein geschützter Hardware-Schlüsselspeicher/TPM ist eine mögliche spätere Erweiterung; die aktuelle Implementierung verwendet eine private OS-Datei.

Nur für lokale Entwicklung ist `--allow-http-localhost` erlaubt; es akzeptiert HTTP ausschließlich für Loopback-Ziele. Produktionsverbindungen verwenden HTTPS mit normaler Zertifikatsprüfung. Für private CAs die Python/OS-Vertrauenskette korrekt konfigurieren, nicht TLS-Prüfungen abschalten.

## Bedienung und Buchungen

- Chip ins NFC-Feld einlesen oder Personalcode eingeben, anschließend PIN. Eine einfache UID ist kopierbar und genügt allein nicht zur Anmeldung.
- Kommen, Pause beginnen, Pause beenden oder Gehen wählen. Gehen während einer Pause verlangt eine ausdrückliche Bestätigung und schließt beides atomar.
- Nur die bestätigte Serverantwort gilt als Buchung. Die Person wird nach Bestätigung sofort bzw. spätestens zehn Sekunden nach Anmeldung ausgeblendet. Eine abgelaufene Identifikation muss wiederholt werden.
- **Ohne `--pending-dir` (unveränderter Legacy-Modus):** Bei einer unbestätigten Anfrage behält der Dienst für den aktuellen Vorgang dieselbe Ereignis-ID zum sicheren Wiederholen. Neuanmelden/Timeout/Neustart können diesen RAM-Vorgang verwerfen. **Im optionalen Klärungsmodus:** vor dem ersten Versand privat dauerhaft ablegen; unbestätigte Vorgänge bleiben getrennt erhalten, während neue Personen neu anmelden können. Keine Wiederholung alter Events, keine Offline-Buchungen; begrenzte Kapazität kann neue Sendungen vorab verweigern. Bei unklarem Ergebnis den aktuellen Status oder den freigegebenen Korrekturweg nutzen, nicht blind erneut buchen.
- Geräteanfragen sind einzeln mit HMAC signiert, Zeitfenster und einmaliger Nonce geschützt. Neue Nonce bei Netzwerk-Retry, gleiche Ereignis-ID beim Wiederholen derselben Buchung. Ein anderer Inhalt unter gleicher ID wird abgewiesen. Pro Person kann nur eine Arbeitszeit und eine Pause offen sein.
- Serverzeit ist maßgeblich. Uhrzeit und Kalendertage werden für `Europe/Berlin` dargestellt; die neue Ereignis-/Intervallablage verwendet UTC-Epochen, Berichtswerte enthalten einen expliziten Offset. Alte Zeitangaben ohne Offset behalten ihren Inhalt und werden als Berliner Ortszeit interpretiert. Dies vor Einführung prüfen, wenn eine bisherige Installation eine andere Zeitzone verwendet hat.
- Einzelne Pausen werden sekundengenau gespeichert; die bisherige Berichts-Spalte erhält die abgerundete Summe in ganzen Minuten. Es werden keine nicht genommenen Pausen erfunden. Beim Gehen erscheint ein Hinweis, wenn die konfigurierte Mindestpausensumme unterschritten ist; die tatsächliche Pause bleibt unverändert. Die bestehenden Regeln müssen fachlich für die Praxis geprüft werden.

## Native Transportfehler vor und nach dem Bootstrap (#25)

Ausgangsstand dieser begrenzten Ergänzung ist Main
`cb8fc5245e5dc980824fb3152224a499a049d3bd`. Ausschließlich der Einstieg
`terminal_api.php` aktiviert vor `config.php` die streng serverseitige
JSON-Fehlerform des vorhandenen `abort_request`. Kontrollierte Bootstrapfehler
behalten Status und Nachricht, etwa 503 mit
`{"success":false,"message":"Database unavailable."}`. Requestheader,
Querydaten und Scriptname können dieses Opt-in nicht auswählen. Normale
Webseiten behalten ihre bisherige Textantwort; die Sicherheits-/Schlüssel-
und Konfigurationsquellen werden nicht verändert.

Eine lokale `try`/`catch (Throwable)`-Grenze besitzt einen eigenen Antwortpuffer
und unterdrückt angezeigte PHP-Diagnostik nur für diesen Terminaleinstieg.
Fangbare unerwartete Fehler vor dem inneren Transport verwerfen dessen
gepufferte Teilantwort, nicht einen vorherigen äußeren Puffer, und ergeben
**HTTP 503** mit JSON-Content-Type und der vorhandenen Terminalmeldung:

```json
{"success":false,"message":"Buchung nicht bestätigt. Verbindung prüfen und denselben Vorgang erneut versuchen."}
```

Das ist bewusst **nicht** der generische 500-Fallback von `api.php`. Es gibt
keine Exceptiondetails, Signatur-/Body-/PIN-/Karten-/Schlüsseldaten oder neue
Logs in dieser Grenze. Bestehende Loggingquellen bleiben unverändert. Kein
globaler Exception-, Shutdown- oder Warninghandler wird registriert; Warnungen
werden nicht in Exceptions umgewandelt. Nicht fangbare Einstiegskompilierfehler,
Speichermangel, Prozessabbruch, Server-/Proxyantworten und bereits außerhalb
dieses Puffers versendete Bytes bleiben ohne JSON-Garantie.

Der gesamte bisherige Terminal-Anwendungsblock bleibt bytegleich: Bootstrap,
Modulsperre vor Methodenprüfung (deaktiviert 404, sonst Nicht-POST 405),
Längenprüfung 8192 und einmaliges Rohbodylesen bis 8193 Bytes, JSON-Decodierung,
Kopplungszweig, HMAC über **denselben unveränderten Rohbody**, Nonceverbrauch,
Identifikation und bestehende Schreibtransaktionen. Es gibt keine neue
CORS-Freigabe, keinen `OPTIONS`-Kurzschluss und keine neue Authentifizierung.
Die inneren `attendance_json`-Abbrüche behalten ihre kontrollierten Statuswerte
400/401/403/404/405/409/413/429, ihren vorhandenen unerwarteten 503 und gültige
200-Erfolge. Deren Encoder, Meldungen und Exit bleiben unverändert.

**503 bedeutet nicht „nichts gespeichert“ oder „zurückgerollt“:** Nonces und
Identifikationszähler werden außerhalb der späteren Ereignissperre verbraucht;
ein Grant kann schon vor einer späteren Status-/Antwortstörung gespeichert
sein. Kopplung committet vor der Schlüsselantwort; Heartbeat/Ereignis können
vor einem Fehler bei der JSON-Ausgabe bereits committed sein. Die neue Grenze
ändert keine dieser Grenzen und bietet weder Rollback noch automatische
Wiederholung oder neue Ereignis-ID an. Der Legacy-Modus des Agenten hält einen
unbestätigten Ereignispayload bei Status >= 500 im Arbeitsspeicher und signiert
einen vom Benutzer ausgelösten Retry mit frischer Zeit/Nonce, aber identischem
Pendingpayload. Reset/Neuidentifikation/Neustart und die offenen #57-Grenzen
bleiben bestehen; keine dauerhafte Queue oder Recoverybehauptung.

Nachweis: vorhandener bootstrapfreier PHP 8.5.12-Grobnachweis mit unveränderten
drei Gruppen plus einer Terminalgruppe (erfundener Fehler/Teilbody, kontrollierter
Bootstrap-503, exakter innerer 405 und gültiger Erfolg; kein `OPTIONS`-Kurzschluss),
Syntax und statischer Ganzquellerhalt. Nur die exakten inneren Transport-
definitionen, keine Bootstrap-/Signatur-/Schreibfunktion wurden ausgeführt.
Keine tatsächlichen Requests, Geräte, Pairings, Ereignisse, Konten, Schlüssel,
Datei-DBs, Header-/HTTP-/Host-/Hardware-/Browser-/Betriebsabnahme oder Aktivierung.
Teilfortschritt #25, keine vollständige Abnahme.

## Neue Terminalaktionen an die identifizierte Arbeitszeit binden (#36)

Quellstand dieses begrenzten Folgeschritts: Main
`830d070e80ea362f0765cb05e0a3186ba7b72f69`. Gerätefreigabe, PIN-/Kartenprüfung,
Standort, Credentialversion und zehnsekündige Identifikation bleiben unverändert.
Die Freigabe war bisher **nicht** an den bei Identifikation gesehenen Work-Record
gebunden: Eine zwischenzeitlich durch einen anderen Kanal ersetzte Sitzung konnte
sonst das Ziel der ersten Terminal-Pausen-/Endaktion werden. Kein tatsächlicher
Vorfall oder Hardwareversuch wird behauptet.

`identify` ergänzt die vorhandene numerische `record_id` um die exakte
`record_id_string` (positiver Dezimalstring oder null bei `absent`). Beide stammen
aus demselben einmal aufgerufenen Statushelper, nicht aus zwei Statusabfragen.
Der reine Formatter verändert weder vorhandene Werte noch gespeicherte Quittungen.
Der Agent hält diese Bindung neben dem Grant; der Kiosk wählt keine Record-ID.
Nur beim Erzeugen eines **neuen** Nicht-Start-Pendingevents setzt er daraus
`expected_record`. Start bleibt beim vorhandenen Keine-offene-Sitzung-Guard.

Der gemeinsame native/Laravel-Terminalkern nimmt das Feld optional an und nutzt
den vorhandenen positiven ASCII-Stringparser vor dem Integercast (`PHP_INT_MAX`).
Mit übermittelter Bindung prüft der bestehende gemeinsame Buchungsdienst unter
derselben Sperre auf die noch aktive Record-ID; eine andere Sitzung ergibt409,
ohne neue Buchung und ohne Verbrauch des Grants in der zurückgerollten Mutation.
Authentifizierungs-/Nonceverbrauch außerhalb dieses Locks wird dadurch nicht
rückgängig gemacht. Es gibt keine zusätzliche Transaktion, neue Grantspalte,
Session-/Rollenpolitik oder Änderung von HMAC-Nachricht, Ereignisnamensraum,
Payloadhashformat und ursprünglicher Replayentscheidung.

Ein bestehender Pendingpayload wird **nie** umgebunden oder nachträglich ergänzt.
Bei unklarer Antwort bleibt seine identische serialisierte Nachricht erhalten;
der vorhandene Retry signiert sie wie bisher mit frischer Zeit/Nonce. Bereits
verbrauchte Grants gestatten weiterhin nur ihre ursprüngliche Event-ID, deren
alter Hash vor dem aktuellen Sitzungsvergleich wiederholt wird. Bestehende
Credential-/Standort-/Gerätesperren bleiben auch vor einem Replay wirksam.
Definitive Antworten behalten die bisherige lokale Grant-/Pending-Bereinigung;
die neue Bindung wird dabei ebenfalls entfernt. Kein automatisches Neubuchen.

**Bewusste optionale Kompatibilität:** Alte Agenten und alte Pendingevents ohne
Feld bleiben beim bisherigen nullable Serververtrag. Der neue Agent bevorzugt
die explizite Zeichenkette. Nur wenn das neue Feld auf einem älteren Server
wirklich fehlt, darf er dessen positiven Python-JSON-Integer exakt als String
verwenden (`type(value) is int`, kein bool/Float); ein vorhandenes ungültiges oder
widersprüchliches Feld wird nicht durch diesen Fallback repariert. Unbekannte
Bindung erlaubt keine neue Nicht-Start-Aktion. Der ältere Server ignoriert das
Zusatzfeld dennoch: **für Altserverbetrieb und alte ungebundene Clients/Pending-
events wird keine neue Stale-Session-Sicherheit behauptet**. Empfohlen ist die
koordinierte Reihenfolge Server, dann Agent. Eine gemischte Versionsrücknahme
oder tatsächliche Installation ist damit nicht freigegeben.

Genau vier Quellpfade: reine Attendance-Metadaten/Parserbeschreibung,
`terminal_core.php`, bestehender Python-Agent und dieser Abschnitt. Native
Wrapper, Laravel-Terminaladapter/Writer, Kiosk, Schema, Schlüssel- und Default-
Zulassung bleiben unverändert. Nur ein grober erfundener Speicherablauf für
Identifikation, Sitzungswechsel, Konflikt und Replay sowie ein zugehöriger reiner
Python-Pending-/Fake-Transportnachweis und Syntax-/Quellerhaltungsprüfung.
Keine realen Personen, Grants, PIN-/Kartenaktionen, Pairings, Daten oder Schlüssel
verwendet; kein Netzwerk, Listener, Browser, keine Uhrverstellung oder neue CI. Hardware-,
Host-/SAPI- und veröffentlichte Abnahme bleiben offen.

**Historischer Teilstand dieses #36-Schritts:** Der Legacy-Modus hält Pending
nur im Arbeitsspeicher; seine bisherigen Verlustgrenzen bleiben ohne Opt-in.
Die folgende optionale #57-Ergänzung bereitet getrennte dauerhafte Klärung vor,
aber keine Offline-Buchung oder automatische Wiederholung alter Ereignisse.

## Optionale neustartfeste Klärungsablage (#57, nur Quellvorbereitung)

Auf Main `01beb3166408e3fe2a4fdd38d3dfdbb76f8333a6` vorbereitet: explizites
`--pending-dir` und lokale OS-Owner-CLI. Der Standardstart und das aktive
systemd-Beispiel bleiben unverändert. **Keine tatsächliche Einrichtung,
Verzeichnis-/Dienstfreigabe, Gerätekopplung oder Live-Quittungsprüfung ist damit
freigegeben oder durchgeführt.**

Im Opt-in-Modus werden ursprünglicher Grant und exakte Eventbytes vor Versand
privat gespeichert. Reset/Neustart/neue Person verwerfen sie nicht. Kein alter
Vorgang wird automatisch oder durch die Klärungs-CLI als Event gesendet; eine
neue Anmeldung bekommt niemals den alten Grant. Bestehende Bildschirmflächen
zeigen die begrenzten Fehler, keine gemeinsame Personen-/Warteschlangenliste.
1000 offene Vorgänge bzw. 16MiB logische Abrechnung sind endliche Grenzen:
volle/unsichere Ablage verweigert neue Events **vor Versand**. Ein einzelner
ungeklärter Mitarbeiter sperrt nicht das Terminal. Netz-/Speichergrenzen bleiben.

Nur der lokale OS-/Dienstspeichereigentümer kann redaktierte Referenzen auflisten,
mit der ursprünglichen Berechtigung ausschließlich historische Quittungen prüfen
oder eine externe Klärung lokal begründet dokumentieren. Das ist kein App-Admin-
Login und erzeugt keine Buchung/Korrektur. Fehlende/gelöschte/gesperrte Grants,
fehlende oder ungültige Antworten bleiben **ungeklärt**, nicht „nicht gebucht“.
Details, private Speichergrenze, getrennte Setup-Gates und der erfundene
Quellnachweis: [terminal-pending-recovery.md](terminal-pending-recovery.md).

## Korrekturen, Verlust und Berechtigungen

`adjustments.php`: Beschäftigte beantragen eine Änderung eigener abgeschlossener Einträge oder eine fehlende Buchung. Zeitpunkte explizit mit UTC-Offset angeben, damit die doppelte Herbststunde eindeutig ist. Antrag enthält Zeitraum, tatsächliche Pausensumme und Begründung. Administratoren oder zugeordnete Vorgesetzte können fremde Anträge mit Begründung freigeben/ablehnen. Eigene Freigaben sind nicht erlaubt: für Korrekturen eines alleinigen Administrators wird eine zweite berechtigte Person benötigt.

Freigabe und Berichtsänderung erfolgen in einer Transaktion. Überschneidungen, geänderte Originaleinträge und doppelte Freigaben werden abgewiesen. Ursprüngliche Ereignisse und Pausenintervalle bleiben erhalten; freigegebene Korrekturen sind als zusätzliche Historie maßgeblich für die Berichtsprojektion. Die normale Anwendung überschreibt die Originalereignisse nicht; ein Datenbankadministrator kann die Datenbank weiterhin verändern (kein extern versiegeltes Audit-Log).

Kartenverlust: persönlichen Terminal-Zugang sperren, dann neue PIN/Karte zuweisen. Gerätesperrung macht auch schon ausgestellte Identifikationsfreigaben unbrauchbar. Personensperrung hier betrifft den Terminal-Zugang, nicht das normale Webkonto. Für ausgeschiedene Beschäftigte auch den allgemeinen Kontozugang entziehen. Konten mit Historie können nicht gelöscht werden; ihre Zuordnung muss erhalten bleiben.

PIN-Fehlversuche sind pro Gerät und Person beschränkt. Kopplungsversuche sind pro Peer-IP beschränkt. Ereignisse protokollieren keine PIN, Karten-Rohkennung oder Geräteschlüssel. Der Dienst unterdrückt HTTP-Zugriffslogs mit personenbezogenen Anfragen; betriebliche Überwachung soll Verfügbarkeit und Fehlerzahlen erfassen, keine Anmeldedaten.

## Sicherung, Deaktivierung und Rollback

Der alte partielle Browser-Datenbankimport ist immer gesperrt, damit Arbeitszeitmodelle und Änderungshistorie erhalten bleiben. Die Einstellungen bieten deshalb keinen Upload an, sondern verweisen auf das manuelle Offline-Vorgehen. Ein Browser-Download ist ein konsistenter Snapshot der gesamten SQLite-Datenbank, aber keine vollständige Betriebssicherung: Externe `.key`-Dateien, Anwendungscode und Betriebskonfiguration sind nicht enthalten.

Die vollständige private Datenablage gemeinsam sichern und den dazu passenden Code-, Schema- und Konfigurationsstand bereithalten. Ein automatischer Vollrestore wird nicht angeboten; ein vollständiger Wiederanlauf muss separat geprüft werden. Für eine Wiederherstellung:

1. App und Gerätedienste stoppen, damit keine Buchungen während der Wiederherstellung eintreffen.
2. Gesamte private Ablage einschließlich Datenbank, SQLite-Sidecars (falls vorhanden) und Schlüssel aus demselben konsistenten Backup wiederherstellen. Zugriffsrechte prüfen.
3. App mit unverändertem Schema-/Code-Stand starten; Geräteberechtigungen und einen Testvorgang prüfen, dann Terminals freigeben. Nicht einzelne ältere Benutzertabellen mit neuer Ereignishistorie mischen.

Zum normalen Abschalten zuerst alle offenen Sitzungen schließen, dann Modul in der Verwaltung deaktivieren. Historie bleibt lesbar und vor alten Schreibwegen geschützt. Danach darf `APP_TERMINAL_ENABLED` entfernt werden. Beim Entfernen der Servervariable entfallen Gerätebuchungen; offene Arbeitszeiten können weiterhin über das Web beendet werden.

Für einen vollständigen Rollback vor Einführung Code und vollständige Datenablage gemeinsam auf die vorherige Sicherung zurücksetzen. Dadurch würden nach der Sicherung entstandene Buchungen verloren gehen; vorher exportieren und fachlich abstimmen. Automatisches Rückbauen der Ereignishistorie in das alte Schema wird nicht angeboten.

## Tests und Grenzen

- `tests/security_http.py`: bestehende Security-Regressionsprüfungen bei deaktiviertem Modul.
- `tests/terminal_http.py`: nur auf frischer `tests/seed.php`-Testdatenbank; Geräte-Pairing, Signaturen/Replays, Identifikation, Zustandsübergänge, Parallelität, Sperren, Korrekturen und optionale Aktivierung.
- `tests/attendance_time.php`: Frühjahrs-/Herbst-Zeitumstellung, lokale Kalendertage und Schichten über 24 Stunden.
- `tests/kiosk_browser.py`: Playwright gegen einen bereits gekoppelten lokalen Testdienst; PIN `12345678`, Personalcode `P02`, simulierte Karte `AABBCCDD` ausschließlich für Testfixtures. Prüft Bedienelemente, Timeout, Browserablage, Host/Origin-Schutz und mobile Breite.

Nicht validiert: echter USB-Leser, Wandterminal/Tablet, Produktionszertifikate und Praxisnetz. Kein Offline-Sync, keine kryptografische NFC-Kartenprüfung, keine biometrische Erfassung. Vor Praxiseinsatz den konkreten Leser, Datenschutz-/Aufbewahrungsregeln, Lohnabrechnung und einen kurzen Pilotbetrieb abnehmen.

Der aktuelle Prüfstand einschließlich verpflichtender Web-Zeiterfassung, Arbeitszeitmodelle und Datenbank-Historie steht in [PRAXISFUNKTIONEN.md](PRAXISFUNKTIONEN.md). Die GitHub-Actions-Pipeline führt die serverseitigen Tests auf frischen Fixtures aus.
