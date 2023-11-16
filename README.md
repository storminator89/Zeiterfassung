# Arbeitszeiterfassung

Das Tool unterstützt Sie bei der Erfassung Ihrer Arbeitszeiten. 
![Main Screen](/assets/mainPage_Screenshot.png)

### Dashboard
![Main Screen](/assets/Dashboard_Screenshot.png)

## Hauptfunktionen

- **Erfassung von Arbeitszeiten:** Nutzer können die Startzeit, Endzeit und Pausenzeit eingeben.
- **Auswahl des Standortes:** Nutzer können auswählen, ob sie im Büro, im Home Office oder auf Dienstreise arbeiten.
- **Beschreibung hinzufügen:** Optionen wie Urlaub, Feiertag und Krankheit sind verfügbar.
- **Statistik:** Es werden Statistiken über die Arbeitszeit für den aktuellen Monat und das Jahr angezeigt mit Dashboard
- **Übersicht der Arbeitszeiten:** Eine detaillierte Tabelle mit allen Arbeitszeiten, die der Benutzer eingibt.
- **Automatische Berechnung** der Arbeitstage in diesem Monat und **Feiertage** weren berücksichtigt
- **Kalenderansicht**

## Abhängigkeiten und Ressourcen

- **Bootstrap:** Für das Styling und die Benutzeroberfläche.
- **jQuery:** Für die Interaktion und Funktionsfähigkeit.
- **DataTables:** Für das Rendern und Managen von Tabellen.
- **Chart.js:** Für das Zeichnen von Diagrammen (wenn implementiert).
- **PDFMake und JSZip:** Für das Erstellen von PDF-Dateien und das Zippen von Daten.
- **SQL Lite** Umgesetzt mit SQLLite

## Installation

- Kopieren Sie alle Dateien in Ihr gewünschtes Verzeichnis.
- Fügen Sie die benötigten externen Bibliotheken hinzu. Diese werden im Haupt-HTML-Dateikopf referenziert.


## Datenbank Konfiguration

Schreibrechte auf Hauptverzeichnis, damit die sqllite Datei angelegt wird

# REST API
an /api.php POST Request
{
  "action": "createNewWorkEntry",
  "startzeit": "2023-11-15T08:00:00", 
  "endzeit": "2023-11-15T16:00:00", 
  "pause": 30
}

# Dockerfile
Alle Dateien in gleiches Verzeichnis wie Dockerfile
`docker build -t zeitwerk .`

z.B. `docker run  --name zeitwerk -d -p 8000:80 -v /root/Docker/zeitwerk/db:/var/www/html/timetracking zeitwerk`

manuelles Kopieren der SQL-Lite DB:
`docker cp zeitwerk:/var/www/html/timetracking.sqlite /root/Docker/zeitwerk/db`

und andersherum von Docker zu Host:
`docker cp /root/Docker/zeitwerk/db/timetracking.sqlite zeitwerk:/var/www/html/timetracking.sqlite`





