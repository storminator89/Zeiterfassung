# Arbeitszeiterfassung

Das Tool unterstützt Sie bei der Erfassung Ihrer Arbeitszeiten. 
![Main Screen](/assets/mainPage_Screenshot.png)

## Hauptfunktionen

- **Erfassung von Arbeitszeiten:** Nutzer können die Startzeit, Endzeit und Pausenzeit eingeben.
- **Auswahl des Standortes:** Nutzer können auswählen, ob sie im Büro, im Home Office oder auf Dienstreise arbeiten.
- **Beschreibung hinzufügen:** Optionen wie Urlaub, Feiertag und Krankheit sind verfügbar.
- **Statistik:** Es werden Statistiken über die Arbeitszeit für den aktuellen Monat und das Jahr angezeigt.
- **Übersicht der Arbeitszeiten:** Eine detaillierte Tabelle mit allen Arbeitszeiten, die der Benutzer eingibt.

## Abhängigkeiten und Ressourcen

- **Bootstrap:** Für das Styling und die Benutzeroberfläche.
- **jQuery:** Für die Interaktion und Funktionsfähigkeit.
- **DataTables:** Für das Rendern und Managen von Tabellen.
- **Chart.js:** Für das Zeichnen von Diagrammen (wenn implementiert).
- **PDFMake und JSZip:** Für das Erstellen von PDF-Dateien und das Zippen von Daten.

## Installation

- Kopieren Sie alle Dateien in Ihr gewünschtes Verzeichnis.
- Fügen Sie die benötigten externen Bibliotheken hinzu. Diese werden im Haupt-HTML-Dateikopf referenziert.
- Stellen Sie sicher, dass Sie die lokale Datei `functions.php` haben, da sie notwendige Funktionen enthält.

## Datenbank Konfiguration

Bevor Sie beginnen, müssen Sie sicherstellen, dass Sie eine Datenbankverbindung eingerichtet haben. 

Erstellen Sie eine `.env`-Datei im Hauptverzeichnis Ihrer Anwendung mit folgendem Inhalt:

```bash
DB_SERVER=YOUR_SERVER_NAME
DB_NAME=YOUR_DATABASE_NAME
DB_USER=YOUR_DATABASE_USERNAME
DB_PASS=YOUR_DATABASE_PASSWORD
``` 


Ersetzen Sie `YOUR_SERVER_NAME`, `YOUR_DATABASE_NAME`, `YOUR_DATABASE_USERNAME` und `YOUR_DATABASE_PASSWORD` durch Ihre eigenen Datenbankinformationen. Zum Beispiel:

## Hinweis

Halten Sie Ihre `.env`-Datei sicher und teilen Sie sie nicht, da sie vertrauliche Informationen enthält!

