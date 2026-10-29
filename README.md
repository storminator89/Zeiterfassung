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

Standard ist **Laravel 13 mit SQLite und dem zugelassenen NEW-V5-Profil**. Das Root-`Dockerfile` baut die Laravel-Anwendung mit digest-gepinntem PHP **8.5.12/Apache** und den gesperrten Composer-Abhängigkeiten; Root-`compose.yaml` startet standardmäßig deren HTTPS-Webdienst. Native PHP wird nicht mehr implizit durch diesen Docker-Start gewählt.

```sh
git clone https://github.com/storminator89/Zeiterfassung.git
cd Zeiterfassung
docker build -t chrono-laravel:local .
```

Vor dem Start bereitet ein berechtigter Betreiber einmal die **neue private Instanz**, den App-Key und bereits vertrauenswürdige TLS-Dateien vor. [Kurzer Standard-Installationsweg](docs/framework/laravel-default-installation.md) · [vollständiger Operator-/Betriebsvertrag](docs/framework/installation-docker.md). Keine bestehenden Datenbanken übernehmen, keine Schlüssel in Repository/Chat legen. Ohne diese Voraussetzungen verweigert der Start; ein Build richtet noch kein nutzbares Konto ein.

Mit der vollständig vorbereiteten privaten Konfiguration:

```sh
docker compose --env-file /absoluter/privater/pfad/chrono-local.env up -d
```

**Erster Admin im Browser:** Die installierte vertrauenswürdige HTTPS-Adresse öffnen. Login/Registrieren führt zum vorhandenen Einrichtungsformular: `admin` übernehmen oder ändern, E-Mail und eigenes Passwort mit Bestätigung eingeben. **Kein Setup-Token und kein Terminalschritt für den Benutzer.** Der erste erfolgreiche Registrierungsvorgang wird Admin; danach schließt die Ersteinrichtung und die normale Anmeldung folgt. **Jeder, der die noch nicht eingerichtete lokale App erreicht, könnte zuerst Admin werden:** nur im vertrauenswürdigen lokalen Umfeld einrichten, nicht öffentlich weiterleiten. Lokale Erreichbarkeit ist kein Identitätsnachweis. [Ablauf und Grenzen](docs/framework/browser-first-user-admin.md).

**Betriebsgrenzen:** einzelner unterstützter Linux-Docker-Host, gleiche nicht-root UID/GID und zugelassene private Datei-/Mountidentität; nur Loopback-Portbindung und echtes HTTPS, kein Zertifikatswarnungs-/HTTP-Fallback. API, LDAP und Terminal sind nicht durch den Standardstart aktiviert. Native Bestandsmigration, beliebige Backup-Adoption, anderer Datenträger und Mehrfachreplikate werden nicht automatisch zugelassen. Vollständige private Ablage einschließlich Schlüssel, Manifest und Setup-Abschluss kalt sichern; [V5-Runtime-/Restoregrenze](docs/framework/account-lifecycle-v5-runtime-profile.md) · [Sessionlocks und gestoppte Recovery](docs/framework/v5-nonexpiring-session-locks.md).

**Nachweise:** ausgewählte Komponenten- und PHP-CGI/Laravel-HttpKernel-Pfade sind isoliert geprüft, die Browser-Ersteinrichtung mit Wegwerfdaten und kompilierten Ansichten. Das ist **keine vollständige End-to-End-, Browser-, TLS-, Docker- oder Produktionsabnahme** dieser Standardkomposition. Die Browserabnahme übernimmt der Benutzer. Die Quellumstellung erstellt keine echte Instanz, Zugangsdaten, Zertifikate oder laufende Anwendung.

### Native Kompatibilitätsvariante

Für bestehende native Installationen bleiben Quellcode und ursprünglicher Docker-Build ausdrücklich verfügbar:

```sh
docker build -f deploy/native/Dockerfile -t chrono-native .
```

Die bisherige private Ablage/Konfiguration unverändert weiterverwenden, bis eine gesondert geprüfte Migration vorliegt; niemals beide Anwendungen auf dieselbe DB richten. Der native Erstsetup-Tokenpfad bleibt unverändert. [Native Sicherheit, Betrieb und Upgrade](SECURITY.md#native-compatibility-operation) · [PHP-Betrieb und Rollback](docs/PHP85.md).

## REST-API

Die API ist im Laravel-Standardstart **ausgeschaltet**. Ihre NEW-Schlüssel-/Profilvorbereitung und Freigabe nach geschlossenem Erstsetup sind separat erforderlich: [Laravel-API-Vertrag](docs/framework/fresh-api-key.md). Die folgenden kompatiblen Endpunkte liegen unter `/api.php`; im nativen Kompatibilitätsbetrieb gelten dessen [API-Vertragsgrenzen](docs/API-CONTRACT.md). Geschützte Aufrufe verwenden `Authorization: Bearer <token>`.

| Endpoint | Zweck |
| --- | --- |
| `POST /api.php/login` | Anmeldung mit Benutzername und Passwort |
| `GET /api.php/attendance` | Eigener verbindlicher Stempelstatus |
| `POST /api.php/attendance` | `start`, `pause_start`, `pause_end`, `end` mit eindeutiger `event_id` |
| `GET /api.php/timeentries` | Eigene Einträge, paginiert |
| `GET /api.php/users` | Eigenes Konto als einelementige Liste |

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
