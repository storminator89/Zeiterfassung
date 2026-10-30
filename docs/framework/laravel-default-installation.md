# Lokaler Standardstart

`./start.sh` ist der Standardinstaller für neue Linux-Docker-Installationen. Er baut das Root-Image, erstellt eine neue private V7-Instanz über `chrono:prepare-fresh --new-only --local-browser-first-admin`, erzeugt den App-Key über den bestehenden Producer und legt zwei unabhängige Terminalschlüssel mit exakten Datei-/DB-Zulassungsmetadaten an. Schlüsselwerte werden nicht ausgegeben. Die Ablage liegt außerhalb des Checkouts; ein unvollständiger Start wird nicht automatisch erneut provisioniert.

## HTTP im LAN

Standard: `http://<lokale IPv4>:8080`, Bindung `0.0.0.0:8080`. HTTP-Origin-Zulassung bleibt auf localhost/`.localhost`, Loopback und RFC1918-IPv4 beschränkt. Öffentliche HTTP-Adressen, Origin-Aliasse, Benutzerinformationen, Query und Unterpfade werden abgewiesen. Der konfigurierte Origin muss exakt zur Browseradresse passen. Keine Proxy-Header-Vertrauensstellung wird hinzugefügt.

HTTP verschlüsselt weder Passwort/PIN noch Buchungsdaten. Für ein vertrauenswürdiges privates LAN vorgesehen; öffentliche Dienste benötigen HTTPS. Der Installer ändert keine Firewall, DNS oder Zertifikats-Vertrauensspeicher.

Die Sitzung bleibt verschlüsselt, HttpOnly und SameSite=Lax; `Secure` ist bei HTTPS aktiv und bei lokalem HTTP deaktiviert. Der CSRF-Token bleibt für Änderungen verpflichtend. Konten-, Generations-, Sperr-, Geräte-Signatur- und Replay-Prüfungen bleiben erhalten.

## Browser-Einrichtung und Terminal

Beim ersten Besuch Admin selbst registrieren. Vor Abschluss bleibt der Terminal-Protokollzugang gesperrt. Im selben ersten Admin-Commit wird das Terminal-Modul für den Standardstart aktiviert; beim nächsten Request werden die bereits privat vorbereiteten Terminalschlüssel und der Geräte-Endpunkt zugelassen. Späteres Ausschalten in der UI bleibt gespeichert und wird durch einen Neustart nicht überschrieben. Geräte und persönliche Zugänge werden niemals automatisch angelegt.

Unter **Praxis-Terminals** Standort anlegen, neues Gerät mit Einmalcode vorbereiten und persönliche PIN-/Karten-Zugänge einrichten. Das lokale `terminal-agent/agent.py` akzeptiert HTTP für dieselben lokalen IPv4-Netze und HTTPS für andere Server. Geräteseitige Kopplung und tatsächliche Hardwareabnahme bleiben notwendig.

## Wiederholter Start und Daten

`./stop.sh` stoppt die Webanwendung. `./start.sh` baut den aktuellen Quellstand und verwendet die vorhandene private Instanz unverändert. Standardablage: `~/.local/share/quodara-chrono`; Änderung über `CHRONO_DATA_DIR`. Keine native Bestandsmigration, kein Reset und keine automatische Backup-Adoption. Unvollständige Erstinstallation privat prüfen, nicht die Zulassungsdateien von Hand korrigieren. Alle Worker müssen bei Wiederherstellung oder Schlüsselarbeiten gestoppt bleiben.

## Optionales HTTPS

Nach der automatischen Vorbereitung in der privaten `local.env` die HTTPS-Origin-Adresse, `CHRONO_LARAVEL_HTTPS_PORT`, `CHRONO_LARAVEL_TLS_CERTIFICATE` und `CHRONO_LARAVEL_TLS_PRIVATE_KEY` setzen. Die Dateien müssen zum Browsernamen passen und vom Browser vertraut werden. Den laufenden HTTP-Dienst stoppen und anschließend aus dem Checkout starten:

```sh
docker compose --env-file /absoluter/privater/pfad/local.env -f compose.https.yaml up -d
```

Für ein vorhandenes benanntes Compose-Projekt zusätzlich dessen Namen mit `-p` beibehalten. Nie HTTP- und HTTPS-Worker parallel auf dieselbe private Instanz starten. Ohne passende Zertifikate verweigert dieser HTTPS-Weg den Start. Die historischen Operatorverträge in `installation-docker.md` beschreiben den getrennten manuellen HTTPS-Weg, nicht die Voraussetzungen des neuen HTTP-Installers.
