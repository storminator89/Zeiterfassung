# Optionales Praxis-Terminal – Online-Betrieb

Implementiert: gemeinsame Ereignisbuchung für Web/API/Terminal, tatsächliche Pausen, Geräte-Pairing und Sperrung, Personalcode/PIN sowie NFC-Kennung plus PIN, lokaler Gerätedienst mit Kiosk, Korrekturanträge mit Freigabe. Nur die Gerätefunktion ist standardmäßig deaktiviert; Web-Zeiterfassung, echte Pausen, Arbeitszeitmodelle und Historie sind immer aktiv. Ein konkreter NFC-Leser wurde nicht angeschlossen; die Tastatureingabe eines USB-HID-Lesers wurde im Browser simuliert. Kryptografische NFC-Karten und Offline-Warteschlangen sind nicht implementiert.

## Server aktivieren

1. Die vollständige private Datenablage sichern, einschließlich SQLite-Datei und aller `.key`-Dateien. PHP 8.3 mit PDO SQLite ist erforderlich.
2. Den Server mit `APP_TERMINAL_ENABLED=1` starten. Beim Docker-Betrieb `-e APP_TERMINAL_ENABLED=1` ergänzen und das bisherige private Datenvolume weiterverwenden. Es werden versionierte Tabellen hinzugefügt; bestehende Arbeitszeiten bleiben unverändert. Ohne Gerätebetrieb muss die Variable nicht gesetzt werden.
3. Vorhandene offene Arbeitszeiten im bisherigen System beenden. Als Administrator `terminals.php` öffnen (Navigation: Praxis-Terminals) und das Modul aktivieren. Die Aktivierung gilt für die gesamte Installation; Standorte sind keine getrennten Mandanten.
4. Standorte und persönliche Zugänge anlegen: bestehende Person auswählen, eindeutigen Personalcode vergeben, achtstellige PIN festlegen, optional NFC-Kennung einlesen. Die PIN separat und vertraulich übergeben. Das Modul zeigt keine Mitarbeiterliste am Terminal. Beim Ersetzen eines Zugangs werden alte Identifikationsfreigaben ungültig. Eine leere NFC-Kennung entfernt die bisherige Karte.
5. Ein Gerät mit Standort anlegen. Die Verwaltung zeigt einmalig eine Geräte-ID und einen zehn Minuten gültigen Kopplungscode. Nach Verwendung ist der Code ungültig. Bei verlorener Kopplung das Gerät sperren und einen neuen Eintrag anlegen.

Unabhängig vom Gerätemodul führt die Startseite zu `attendance.php`. Kommen und Gehen der bisherigen Web/API-Clients werden ebenfalls durch denselben Buchungsdienst geleitet. Erfasste Zeiten lassen sich auch nach Deaktivierung nicht über alte Schreib-/Löschendpunkte ändern. Historische, vollständige API-Nachbuchungen werden immer mit einem Hinweis auf Korrekturanträge abgewiesen. Der neue API-Endpunkt `POST /api.php/attendance` akzeptiert `action`, `event_id` und optional `confirm_end_pause` unter der bestehenden Benutzer-JWT-Authentifizierung.

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
- Bei einer unbestätigten Anfrage behält der Dienst für den aktuellen Vorgang dieselbe Ereignis-ID zum sicheren Wiederholen. Ein Neuanmelden/Timeout verwirft den lokalen Vorgang; bei unklarem Ergebnis den aktuellen Status prüfen oder einen Korrekturantrag stellen. Es gibt bewusst keine dauerhafte Offline-Queue.
- Geräteanfragen sind einzeln mit HMAC signiert, Zeitfenster und einmaliger Nonce geschützt. Neue Nonce bei Netzwerk-Retry, gleiche Ereignis-ID beim Wiederholen derselben Buchung. Ein anderer Inhalt unter gleicher ID wird abgewiesen. Pro Person kann nur eine Arbeitszeit und eine Pause offen sein.
- Serverzeit ist maßgeblich. Uhrzeit und Kalendertage werden für `Europe/Berlin` dargestellt; die neue Ereignis-/Intervallablage verwendet UTC-Epochen, Berichtswerte enthalten einen expliziten Offset. Alte Zeitangaben ohne Offset behalten ihren Inhalt und werden als Berliner Ortszeit interpretiert. Dies vor Einführung prüfen, wenn eine bisherige Installation eine andere Zeitzone verwendet hat.
- Einzelne Pausen werden sekundengenau gespeichert; die bisherige Berichts-Spalte erhält die abgerundete Summe in ganzen Minuten. Es werden keine nicht genommenen Pausen erfunden. Beim Gehen erscheint ein Hinweis, wenn die konfigurierte Mindestpausensumme unterschritten ist; die tatsächliche Pause bleibt unverändert. Die bestehenden Regeln müssen fachlich für die Praxis geprüft werden.

## Korrekturen, Verlust und Berechtigungen

`adjustments.php`: Beschäftigte beantragen eine Änderung eigener abgeschlossener Einträge oder eine fehlende Buchung. Zeitpunkte explizit mit UTC-Offset angeben, damit die doppelte Herbststunde eindeutig ist. Antrag enthält Zeitraum, tatsächliche Pausensumme und Begründung. Administratoren oder zugeordnete Vorgesetzte können fremde Anträge mit Begründung freigeben/ablehnen. Eigene Freigaben sind nicht erlaubt: für Korrekturen eines alleinigen Administrators wird eine zweite berechtigte Person benötigt.

Freigabe und Berichtsänderung erfolgen in einer Transaktion. Überschneidungen, geänderte Originaleinträge und doppelte Freigaben werden abgewiesen. Ursprüngliche Ereignisse und Pausenintervalle bleiben erhalten; freigegebene Korrekturen sind als zusätzliche Historie maßgeblich für die Berichtsprojektion. Die normale Anwendung überschreibt die Originalereignisse nicht; ein Datenbankadministrator kann die Datenbank weiterhin verändern (kein extern versiegeltes Audit-Log).

Kartenverlust: persönlichen Terminal-Zugang sperren, dann neue PIN/Karte zuweisen. Gerätesperrung macht auch schon ausgestellte Identifikationsfreigaben unbrauchbar. Personensperrung hier betrifft den Terminal-Zugang, nicht das normale Webkonto. Für ausgeschiedene Beschäftigte auch den allgemeinen Kontozugang entziehen. Konten mit Historie können nicht gelöscht werden; ihre Zuordnung muss erhalten bleiben.

PIN-Fehlversuche sind pro Gerät und Person beschränkt. Kopplungsversuche sind pro Peer-IP beschränkt. Ereignisse protokollieren keine PIN, Karten-Rohkennung oder Geräteschlüssel. Der Dienst unterdrückt HTTP-Zugriffslogs mit personenbezogenen Anfragen; betriebliche Überwachung soll Verfügbarkeit und Fehlerzahlen erfassen, keine Anmeldedaten.

## Sicherung, Deaktivierung und Rollback

Der alte partielle Browser-Datenbankimport ist immer gesperrt, damit Arbeitszeitmodelle und Änderungshistorie erhalten bleiben. Ein Browser-Download sichert die gesamte SQLite-Datenbank, aber keine `.key`-Dateien. Für eine Wiederherstellung:

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
