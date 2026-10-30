# Vorgesetztenbericht: Detailuhrzeiten in Berlin

Die Detailzeilen des nativen Vorgesetztenberichts und der gemeinsamen
Berichtsprojektion zeigen Anfang und Ende mit dem vorhandenen
`booking_time()`/`booking_local_date()`-Paar in `Europe/Berlin` an. Ein bereits
unterstützter Zeitstempel mit `Z` oder explizitem Offset wird damit auch für die
Uhrzeitanzeige nach Berlin umgerechnet; ein Zeitstempel ohne Zone behält die
bisherige Berlin-Auslegung. Beispiel: `2026-07-01T08:00:00+00:00` erscheint als
`10:00`, passend zum bereits vorhandenen Berlin-Berichtstag.

Nur die beiden formatierten Uhrzeitfelder ändern sich. Gespeicherte Werte,
Parser, Prozesszeitzone, Datenauswahl und Berechtigungen, Tages-/Wochenzuordnung,
Dauer, Pausen, Soll-/Saldo-/Abwesenheitsberechnung, Suche, Seitennavigation und
Datengüteverweigerung bleiben unverändert. Ein fehlendes Ende bleibt „Läuft“.

## Grober Nachweis und Grenzen

Mit PHP 8.5.12/SQLite 3.46.1 wurde die tatsächliche gemeinsame Projektion mit
dem vorherigen Stand sowie das extrahierte native Detailfragment verglichen:
je zehn künstliche Fälle bei UTC-, Berlin- und Los-Angeles-Prozesszeitzone,
darunter explizite Offsets, Berlin-Mitternacht, Sommer-/Winterzeitwechsel,
zonenlose Zeitwerte und fehlendes Ende. Der vorherige Projektionsstand zeigte
in 18 Fällen die falsche Uhrzeit. Nach der Änderung stimmen die Berlin-Uhrzeiten;
außer den beiden Anzeigefeldern bleiben die Projektionsdaten identisch. Suche,
Pagination, Summen, ungültige Zeitwerte, Abwesenheitskonflikte und HTML-Escaping
wurden in diesem begrenzten Vergleich mitbetrachtet. Die künstliche Memory-DB
blieb ab dem Beginn der Vergleiche query-only; Schema, Änderungszähler und
Prozess-/Sessionzustand blieben unverändert.

Das ist kein Nachweis des vollständigen Handlers, Controllers, Authentifizierungs-
oder Berechtigungspfads, HTTP-, Browser-, Produktions-, Last- oder Betriebsablaufs.
Keine echten Daten, Konten, Schlüssel, Datenbankdateien oder Laufzeit-/Hoständerungen;
kein Deployment und keine vollständige Browserabnahme. Die Browserabnahme bleibt
beim Benutzer.
