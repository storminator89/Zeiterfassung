# Time Tracking Tool

This tool assists you in tracking your working hours.

![Main Screen](/assets/mainPage_Screenshot.png)
![Main Screen](/assets/mainPage_Screenshot2.png)

## Browser Plugin

![Browser Plugin](/assets/erweiterung_edge.png) 
Located in the `time-tracker-extension_edge` folder.

### Dashboard

![Dashboard](/assets/Dashboard_Screenshot.png)

### User Management

![User Management](/assets/user_management.png)

### Dark Mode

![Dark Mode](/assets/darkmode.png)

## Main Features

- ⏰ **Work Time Tracking:** Users can enter start time, end time, and break time (manually or using a timer).
- 📍 **Location Selection:** Users can select whether they are working in the office, from home, or on a business trip.
- ✏️ **Add Descriptions:** Options like vacation, holiday, and sickness are available.
- 📊 **Statistics:** Displays work time statistics for the current month and year on the dashboard.
- 📋 **Work Time Overview:** A detailed table of all work times entered by the user.
- 🧮 **Automatic Calculation** of workdays in the current month, including **holidays**.
- 📅 **Calendar View** for a better overview of all work times.
- 🔑 **Login and Logout/ Registration**
- ⚙️ **Admin Area**

## Dependencies and Resources

- **Bootstrap:** For styling and the user interface.
- **jQuery:** For interaction and functionality.
- **DataTables:** For rendering and managing tables.
- **Chart.js:** For drawing charts (if implemented).
- **PDFMake and JSZip:** For creating PDF files and zipping data.
- **SQLite:** For data storage.

## Installation

- Copy all files to your desired directory.

## Database Configuration

Ensure write permissions on the main directory to allow the SQLite file to be created.

# REST API

The REST API is available at `/api.php`

## Endpoints

- **POST /api.php/login:** User login. Requires `username` and `password`.
- **POST /api.php/workentry:** Create a new work entry. Requires `startzeit`, `endzeit`, `pause`, `beschreibung`, and `standort`.
- **POST /api.php/setendzeit:** Set end time for a specific work entry. Requires `id`.
- **GET /api.php/users:** Get a list of all users.
- **GET /api.php/timeentries:** Get all time entries for the authenticated user.
- **DELETE /api.php/timeentry/{id}:** Delete a specific time entry.

## Authentication

The API uses JWT (JSON Web Tokens) for authentication. Upon successful login, a token is returned which must be included in the `Authorization` header of subsequent requests.

## Example Usage

```sh
# Example of logging in and receiving a JWT token
curl -X POST -H "Content-Type: application/json" -d '{"username":"your_username", "password":"your_password"}' https://yourdomain.com/api.php/login

# Example of creating a new work entry
curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer your_jwt_token" -d '{"startzeit":"2023-11-15T08:00:00", "endzeit":"2023-11-15T16:00:00", "pause":30, "beschreibung":"Project work", "standort":"Home Office"}' https://yourdomain.com/api.php/workentry
