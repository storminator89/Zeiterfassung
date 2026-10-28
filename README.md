# Time Tracking Tool Quodara Chrono
![Version](https://img.shields.io/badge/version-0.3-blue)
![License](https://img.shields.io/badge/license-MIT-green)

This tool assists you in tracking your working hours.

# Contents

- [Time Tracking Tool Quodara Chrono](#time-tracking-tool-quodara-chrono)
  - [Main Features](#main-features)
  - [Screenshots](#screenshots)
    - [Main Screen](#main-screen)
    - [Browser Plugin](#browser-plugin)
    - [Dashboard](#dashboard)
    - [User Management](#user-management)
    - [Supervisor](#supervisor)
    - [Settings](#settings)
    - [Dark Mode](#dark-mode)
  - [Dependencies and Resources](#dependencies-and-resources)
  - [Installation](#installation)
  - [Database Configuration](#database-configuration)
  - [REST API](#rest-api)
    - [Endpoints](#endpoints)
    - [Authentication](#authentication)
    - [Example Usage](#example-usage)

---

## Tutorial Video

Watch our tutorial video to learn more about Quodara Chrono:

[![Quodara Chrono Tutorial](https://img.youtube.com/vi/vh1hj4rqv88/0.jpg)](https://www.youtube.com/watch?v=vh1hj4rqv88)

Click on the image above to view the video on YouTube.


## Main Features

⏰ **Work Time Tracking:** Users can enter start time, end time, and break time (manually or using a timer). This allows
    for precise tracking of working hours and breaks.

📍 **Location Selection:** Users can select whether they are working in the office, from home, or on a business trip. This
    helps document and analyze the work environment.

✏️ **Add Descriptions:** Options like vacation, holiday, and sickness are available. Users can add descriptions to better
    categorize their working hours.

📊 **Statistics:** Displays work time statistics for the current month and year on the dashboard. This includes total
    working hours, averages, and more.

📋 **Work Time Overview:** A detailed table of all work times entered by the user. This table provides an easy overview and
    management of work time entries.

🧮 **Automatic Calculation:** Automatic calculation of workdays in the current month, including holidays. This simplifies
    planning and monitoring.

📅 **Calendar View:** For a better overview of all work times. The calendar view allows users to see their work entries in
    a familiar calendar format.

🔑 **Login and Logout/ Registration:** Secure user authentication with the ability to register new accounts, log in, and log out.

⚙️ **Admin Area:** An administration area for managing users and settings. Admins can oversee user activities and configure
    system settings.

🌐 **Multilingual Support:** Currently supports both German and English languages. Users can switch between languages based
    on their preference.

🌍 **Geolocation webhook:** Not implemented in the current `/api.php` router. Location selection is a separate
    reporting field; automatic booking on entering or leaving a location is not available.




# Screenshots
![Main Screen](/assets/mainPage_Screenshot.png)
![Main Screen](/assets/mainPage_Screenshot2.png)

## Browser Plugin

![Browser Plugin](/assets/erweiterung_edge.png) 
Located in the `time-tracker-extension_edge` folder.

### Dashboard

![Dashboard](/assets/Dashboard_Screenshot.png)

### User Management

![User Management](/assets/user_management.png)

### Supervisor

![Supervisor](/assets/supervisor.png)

### Settings

![Settings](/assets/settings.png)
### Dark Mode

![Dark Mode](/assets/darkmode.png)


## Dependencies and Resources

- **Bootstrap:** For styling and the user interface.
- **jQuery:** For interaction and functionality.
- **Chart.js:** For drawing charts (if implemented).
- **PHP 8.5:** Current supported PHP 8.5 patch release (Docker and CI: **8.5.12**).
- **PHP extensions:** PDO SQLite, SQLite3, mbstring, OpenSSL, ctype, fileinfo and iconv. LDAP is optional and must be installed separately before enabling directory login.
- **SQLite:** For data storage.

## Installation

Use the Docker setup and migration steps in [SECURITY.md](SECURITY.md). Do not expose a fresh setup publicly.
The Docker build uses the official `php:8.5.12-apache-trixie` image; the existing integration workflow uses the matching `php:8.5.12-cli-trixie` runtime.
For the runtime upgrade and rollback checklist, see [PHP 8.5 upgrade](docs/PHP85.md).

## Database Configuration

Set `APP_DATA_DIR` to a private writable directory outside the web root (Docker: `/var/lib/chrono`). Legacy databases under `assets/db` must be migrated before startup.

# REST API

The REST API is available at `/api.php`

## Endpoints

- **POST /api.php/login:** User login. Requires `username` and `password`.
- **POST /api.php/workentry:** Start work with server time (historical entries require correction). Send a unique `event_id` for safe retries; client timestamps are not authoritative.
- **POST /api.php/setendzeit:** Set end time for a specific work entry. Requires `id`.
- **GET /api.php/attendance:** Get authoritative own attendance as `{success:true,state,record_id}`. `state` is `absent`, `working` or `paused`; `record_id` is an integer for an active record, otherwise `null`. Requires the existing bearer token; no user selector or booking is accepted.
- **GET /api.php/users:** Get the currently authenticated user as a one-element array.
- **GET /api.php/timeentries:** Get a bounded page of own time entries (`limit` default 100, maximum 1000; nonnegative integer `offset`). [Pagination contract and migration](docs/CH18-API-PAGINATION.md).
- **DELETE /api.php/timeentry/{id}:** Disabled (409); use an approved cancellation request.

## Authentication

The API uses JWT (JSON Web Tokens) for authentication. Tokens expire after one hour and are invalidated by password changes or account deletion. Upon successful login, a token is returned which must be included in the `Authorization` header of subsequent requests.

## Example Usage

```sh
# Example of logging in and receiving a JWT token
curl -X POST -H "Content-Type: application/json" -d '{"username":"your_username", "password":"your_password"}' https://yourdomain.com/api.php/login

# Start work with an event ID (reuse it only when retrying this exact action)
curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer your_jwt_token" -d '{"action":"start", "event_id":"example-start-000001"}' https://yourdomain.com/api.php/attendance
```

## Optionales Praxis-Terminal

Der Online-Terminal-Modus bietet PIN/NFC-Kennung plus PIN, Geräteverwaltung, tatsächliche Pausen und Korrekturanträge. Er ist standardmäßig deaktiviert. [Einrichtung und Betrieb](docs/TERMINAL.md) · [ursprünglicher Ausbauplan](docs/PRAXIS-TERMINAL-PLAN.md).

Für den Pilotbetrieb `APP_TERMINAL_ENABLED=1` setzen und das Modul als Administrator unter `terminals.php` aktivieren. Der lokale Python-Gerätedienst mit Kiosk liegt in `terminal-agent/`. Ein realer NFC-Leser muss noch mit der gewählten Hardware geprüft werden; Offline-Buchungen sind nicht implementiert.

## Arbeitszeiten, Abwesenheit und Historie

Die Startseite erfasst Kommen, tatsächliche Pausen und Gehen mit Serverzeit – auch ohne Terminal. Alle direkten Änderungen/Löschungen, einschließlich alter API-Endpunkte, sind durch begründete Korrekturanträge mit Freigabe ersetzt.

Unter **Arbeitszeitmodelle** hinterlegt die Verwaltung Sollminuten je Wochentag und Gültigkeitsdatum. Urlaub/Krankheit erhalten diese Gutschrift; freie Tage und hinterlegte Feiertage werden ausgelassen. Unter **Historie** stehen alte/neue Werte und Freigaben. Upgrade-, API- und Betriebsdetails: [Praxisfunktionen](docs/PRAXISFUNKTIONEN.md), [Terminaleinrichtung](docs/TERMINAL.md).
