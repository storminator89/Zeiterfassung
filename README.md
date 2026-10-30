# Quodara Chrono · Zeiterfassung

[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4)](docs/PHP85.md)
[![Lizenz: MIT](https://img.shields.io/badge/Lizenz-MIT-green)](LICENSE)

Selbst gehostete Zeiterfassung für Beschäftigte, Führungskräfte und Verwaltung: Arbeitszeit, tatsächliche Pausen, Abwesenheiten und nachvollziehbare Korrekturen in einer Webanwendung.

## Video

[![Quodara Chrono Tutorial](https://img.youtube.com/vi/vh1hj4rqv88/0.jpg)](https://www.youtube.com/watch?v=vh1hj4rqv88)

## Funktionsumfang

- **Kommen, Pause, Gehen:** serverseitige Zeitstempel, bestätigte Buchungen und Schutz vor Doppelbuchung bei Wiederholung derselben `event_id` mit identischem Inhalt; Arbeitsort und Notiz optional beim Start. Keine automatisch abgezogenen Mindestpausen.
- **Übersicht und Auswertung:** eigene Arbeitszeitliste, Kalender, Wochen-/Monats-/Jahreswerte, Sollabdeckung und getrennte Anzeige von Arbeit und Abwesenheitsgutschrift; eigener CSV-Export vollständig oder für einen Zeitraum.
- **Arbeitszeitmodelle und Abwesenheiten:** versionierte Sollminuten je Wochentag und Gültigkeitsdatum; Urlaub und Krankheit mit modellbasierter Gutschrift, unter Berücksichtigung freier Tage und hinterlegter Feiertage.
- **Korrekturen und Historie:** begründete Nachträge, Änderungen und Stornierungen mit Freigabe durch eine andere berechtigte Person; Änderungsjournal mit Vorher-/Nachher-Werten und Hinweisen auf offene Klärungen.
- **Team und Verwaltung:** Benutzer, Rollen, Abteilungen und Vorgesetztenzuordnung; berechtigte Teamberichte, aktueller Stempelstatus und datensparsame Abwesenheitswochenansicht.
- **Zugang und Oberfläche:** lokale Anmeldung, eigene Passwortänderung für lokale Konten, optional LDAP-Anmeldung und -Synchronisation; Deutsch, Englisch und Chinesisch sowie Hell-/Dunkelmodus.
- **Schnittstellen:** separat freizuschaltende JWT-REST-API und mitgelieferte [Edge-](time-tracker-extension_edge/) und [Firefox-Erweiterung](firefox_add_on/). Browserinstallation und Integrationsabnahme sind separat erforderlich; siehe [Clientvertrag](docs/extension-contract.md).
- **Getrennte Terminal-Integration:** Geräteprotokoll und lokaler Python-Kiosk in [terminal-agent/](terminal-agent/); im Standardprofil nicht aktiviert, seine Geräteseite ist dort nicht eingebunden. Native Einrichtung und reale NFC-Hardware benötigen gesonderte Freigabe/Prüfung; [Betriebsanleitung](docs/TERMINAL.md).

**Grenzen:** keine Offline-Buchungen, automatische Geolocation-Buchung, Urlaubsanspruchsverwaltung oder Lohnabrechnung. Feiertage sind für den eigenen Standort zu prüfen. Die native Kompatibilitäts-Kontenverwaltung bietet noch keine zentrale Sperre sämtlicher Zugangsarten; [Zugriffsgrenzen](docs/native-admin-account-access-limits.md). Das Journal ist gegen Änderungen durch die Anwendung geschützt, nicht extern gegen Betreiberzugriff versiegelt.

## Installation

Docker Engine und Docker Compose ab Version 2.20 auf Linux genügen. Der Standard startet Laravel mit SQLite über **HTTP im lokalen Netzwerk**, ohne TLS-Zertifikate oder manuell erzeugte Schlüssel:

```sh
git clone https://github.com/storminator89/Zeiterfassung.git
cd Zeiterfassung
./start.sh
```

Der Start baut das Image, erstellt einmal eine neue private Instanz samt App- und Terminalschlüsseln und zeigt die Browseradresse an. Standardport ist **8080**; als Adresse wird die lokale IPv4-Adresse verwendet, ersatzweise `localhost`. Im Browser den ersten Admin mit eigener E-Mail und eigenem Passwort einrichten. Danach ist das Terminal-Modul standardmäßig aktiv. Unter **Praxis-Terminals** Standorte, Geräte und persönliche PIN-/Karten-Zugänge verwalten; jedes Gerät muss ausdrücklich gekoppelt werden.

HTTP ist für `localhost`, Loopback und private IPv4-Netze (10/8, 172.16/12, 192.168/16) zugelassen. Anmeldung, PINs und Buchungen werden dabei unverschlüsselt übertragen. Nur in einem vertrauenswürdigen LAN nutzen, keine öffentliche Portweiterleitung einrichten. Für öffentliche Adressen HTTPS verwenden. Die erste Admin-Einrichtung zeitnah selbst abschließen: Wer die noch leere Instanz erreicht, könnte den ersten Admin registrieren.

Optionale Einstellungen **beim ersten Start**:

```sh
CHRONO_HOST=192.168.1.20 CHRONO_HTTP_PORT=8080 ./start.sh
# Nur auf diesem Rechner erreichbar:
CHRONO_HOST=localhost CHRONO_BIND_ADDRESS=127.0.0.1 ./start.sh
```

Private Daten liegen standardmäßig unter `~/.local/share/quodara-chrono`, außerhalb des Checkouts. `CHRONO_DATA_DIR=/absoluter/privater/pfad` wählt eine andere Ablage; diese muss dem eigenen Benutzer gehören und Modus 0700 haben. Vorhandene Instanzen werden wiederverwendet, niemals automatisch zurückgesetzt oder importiert. Bei unterbrochener Erstinstallation stoppt der Installer zur Prüfung. Der Checkout merkt sich die gewählte Ablage in der ignorierten lokalen Datei `.chrono-data-dir`. Konfiguration später privat in `local.env` bearbeiten; die Browseradresse muss exakt mit `CHRONO_LARAVEL_ORIGIN` übereinstimmen.

```sh
./stop.sh             # Stoppen, Daten behalten
./start.sh            # Wieder starten / aktualisierten Quellstand bauen
```

HTTPS bleibt mit `compose.https.yaml`, vorhandenen Zertifikaten und derselben privaten Instanz verfügbar. [Startdetails und HTTPS](docs/framework/laravel-default-installation.md). API und LDAP bleiben separat aktivierbar. Vollständige private Ablage einschließlich Schlüssel, Manifest und Setup-Abschluss kalt sichern.

### Native Kompatibilitätsvariante

Für bestehende native Installationen bleiben Quellcode und ursprünglicher Docker-Build ausdrücklich verfügbar:

```sh
docker build -f deploy/native/Dockerfile -t chrono-native .
```

Die bisherige private Ablage/Konfiguration unverändert weiterverwenden, bis eine gesondert geprüfte Migration vorliegt; niemals beide Anwendungen auf dieselbe DB richten. Der native Erstsetup-Tokenpfad bleibt unverändert. [Native Sicherheit, Betrieb und Upgrade](SECURITY.md#native-compatibility-operation) · [PHP-Betrieb und Rollback](docs/PHP85.md).

## REST-API

Die API ist im Laravel-Standardstart **ausgeschaltet**. Nach der Browser-Ersteinrichtung kann lokale Administration unter **Administration → API verwalten** mit eigenem aktuellem Passwort und ausdrücklicher Bestätigung einen unabhängigen privaten Signierschlüssel anlegen und die API einschalten – ohne Environmentdatei oder Neustart. Ausschalten sperrt den Kanal, rotiert aber keine Schlüssel/Tokens; noch nicht abgelaufene Tokens können beim Wiedereinschalten wieder gelten. Kein Schlüssel wird angezeigt, kein persönlicher Bearer-Token hier ausgegeben. [UI-Ablauf und Betriebsgrenzen](docs/framework/api-admin-ui.md) · [separate ältere NEW-CLI-Alternative](docs/framework/fresh-api-key.md). Die folgenden kompatiblen Endpunkte liegen unter `/api.php`; im nativen Kompatibilitätsbetrieb gelten dessen [API-Vertragsgrenzen](docs/API-CONTRACT.md). Geschützte Aufrufe verwenden `Authorization: Bearer <token>`.

| Endpoint | Zweck |
| --- | --- |
| `POST /api.php/login` | Anmeldung mit Benutzername und Passwort |
| `GET /api.php/attendance` | Eigener verbindlicher Stempelstatus |
| `POST /api.php/attendance` | `start`, `pause_start`, `pause_end`, `end` mit eindeutiger `event_id` |
| `GET /api.php/timeentries` | Eigene Einträge, paginiert |
| `GET /api.php/users` | Eigenes Konto als einelementige Liste |
| `GET /api.php/work-model` | Eigene gespeicherte Modellversion für einen Berlin-Tag, keine Soll-/Saldo-Neuberechnung |

`workentry` und `setendzeit` bleiben Start-/Endaliase. Clientzeiten sind nicht maßgeblich; historische Nachträge, direkte Änderungen und Löschungen gehören in den Korrekturworkflow. Bei unsicherem Ausgang dasselbe Ereignis mit unverändertem Inhalt wiederholen.

[OpenAPI-Schema](swagger.json) · [API-Dokumentation](apidoc.html) · [Nativer API-Vertrag](docs/API-CONTRACT.md) · [Pagination](docs/CH18-API-PAGINATION.md) · [Optionale Workflow-Hinweise](docs/native-attendance-workflow.md).

## Dokumentation und Ressourcen

- [Praxisfunktionen und Fachregeln](docs/PRAXISFUNKTIONEN.md) · [Terminal-Konzept](docs/PRAXIS-TERMINAL-PLAN.md)
- [Sicherheit und Betrieb](SECURITY.md) · [Sicherheitsreview und Grenzen](SECURITY-REVIEW.md) · [MIT-Lizenz](LICENSE)

## Screenshots

### Startseite

![Startseite](assets/mainPage_Screenshot.png)
![Weitere Ansicht der Startseite](assets/mainPage_Screenshot2.png)

### Browsererweiterung

![Browsererweiterung](assets/erweiterung_edge.png)

### Dashboard

![Dashboard](assets/Dashboard_Screenshot.png)

### Benutzerverwaltung

![Benutzerverwaltung](assets/user_management.png)

### Vorgesetztenansicht

![Vorgesetztenansicht](assets/supervisor.png)

### Einstellungen

![Einstellungen](assets/settings.png)

### Dunkelmodus

![Dunkelmodus](assets/darkmode.png)
