CREATE TABLE IF NOT EXISTS zeiterfassung (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        startzeit TEXT NOT NULL,
        endzeit TEXT NOT NULL,
        pause INTEGER NOT NULL,
        beschreibung TEXT,
        standort TEXT
    )

CREATE TABLE IF NOT EXISTS Feiertage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        Datum TEXT NOT NULL
    )
