# #78 / #79 – gemeinsame Home-Leseprojektion

Begrenzte Voraussetzung für die vorhandene Home-Ansicht, **kein Cutover und
keine vollständige #78/#79-Abnahme**. `ChronoReads::homeSummary()` ist parameterlos
und liest ausschließlich den aktuellen Guard-Nutzer innerhalb der bestehenden
gleich-PDO-`DEFERRED`-Lesetransaktion. Benutzerrolle und Identität werden wie bei
den bisherigen Reads im Snapshot erneut geladen. Keine zusätzliche Verbindung,
Migration, Transaktion, Uhr aus dem Request oder schreibende Kalenderpflege.

## Aktive gemeinsame Bestandslogik

`lib/home_projection.php` lädt nur Definitionen aus `practice.php` und
`reporting.php`. `functions.php`, `config.php`, Sessions und deren ausführbare
Kalender-/Bootstraplogik werden vom Framework **nicht** includiert.

- `getWorkingDays` und `fetchFeiertageDieseWoche` behalten ihre nativen Signaturen
  und Rückgabeformen und delegieren an dieselben neuen Helfer wie der Read.
- Die native Überstundensumme delegiert an `home_total_overtime`; bestehende
  Globals wie `workHoursByDate` und alle anderen Wochen-/Monats-/Jahreswerte
  bleiben erhalten. `fetchFeiertageDB` samt bestehendem Download/Insert-Aufruf
  bleibt ausschließlich im nativen Ablauf unverändert aktiv.
- Die native heutige Sollanzeige in `attendance.php` delegiert dieselbe vorhandene
  `report_format_hours(planned_minutes(...)/60)`-Formatierung. `calculateDuration`
  wurde unverändert in die Definitionsdatei verschoben; CSV und neuester Eintrag
  behalten damit denselben öffentlichen Helfer.

Die Home-Summe ist exakt der Bestand: `users.ueberstunden ?? 0.0` plus für **alle**
abgeschlossenen Bericht-Starttage tatsächliche Minuten minus historisches Soll.
Kein Nur-Jahreswert, kein kontinuierliches Zeitkonto und kein Abzug für fehlende
Werktage. Bereits gespeicherte Abwesenheitsgutschriften werden nicht durch ein
späteres Modell neu berechnet. Arbeitstage zählen das **ganze aktuelle Berliner
Monat**, jeweils nur bei positivem Soll; heute, Feiertage und Modellhistorie
kommen aus den vorhandenen Helfern. Überstundenfarbe bleibt strikt `> 0`.

Roh/unescaped: `current_month` (`m`), `current_date`, `working_days_this_month`,
`total_overtime_hours`, `total_overtime_formatted`, `positive_overtime`,
`daily_hours_formatted`, `holidays_this_week` (`date,formatted_date,name`). Es
werden ausschließlich gespeicherte Wochenfeiertage gelesen, nicht nachgeladen.
Kalenderabdeckung und ihre bestehenden Grenzen bleiben offen.

## Gemeinsame Tabellen-Datenaufbereitung

`report_time_record_groups(array $pagedRows)` hat keine SQL-, HTML-, Session-
oder Escapinglogik. **Zuerst zehn rohe Zeilen** in bestehender roher
`startzeit DESC`-Reihenfolge paginieren, erst danach Berliner Starttage gruppieren.
Der native `get_time_records.php` ruft den Helfer aktiv auf; SQL, Echo-Markup,
Klassen, Links und `h()`-Grenzen bleiben erhalten. `outputRecordRow($record)`
akzeptiert weiterhin native Rohzeilen und delegiert seine Anzeigelabels.

Gruppenfelder: `work_date,formatted_date,total_duration,total_pause,entry_count,
records`. Ein Eintrag behält native Rohschlüssel und ergänzt die Anzeigeprojektion
`id,week_number,start_label,end_label,duration_label,pause,location,comment`.
Blade verwendet nur diese Anzeigeprojektion und escaped selbst.

Die Tageszusammenfassung verwendet weiterhin rohe Zeitdifferenzen mit **floor pro
Zeile**, ganze gespeicherte Pausen und den nativen negativen Modulo-/Formatpfad.
Sie wird nicht durch `report_days` oder `intdiv` ersetzt. Einzelne normale
Detaildauern verwenden weiterhin `calculateDuration`/Berichtsvalidierung;
Gutschriften zeigen die gespeicherten Sollminuten. Ein ungültiges negatives
Intervall kann daher weiterhin einen negativen Tageswert und `-` im Detail zeigen.
Explizites Berlin bei rohen DateTime-Konstruktoren erhält die native Zeitzone,
ohne Laravels globale UTC-Zeitzone zu ändern; gespeicherte Offsets bleiben gültig.
Geteilte Tage, seitenlokale Summen, Starttagzuordnung und bekannte Reporting- sowie
Kalendergrenzen werden hier ausdrücklich nicht korrigiert.

## Begrenzter Nachweis

```sh
cd framework
php tools/rehearse-home-projection.php
```

Ein grober synthetischer Memory-Nachweis: Carry-in, Vorjahr, abgeschlossene/offene
und negative Zeilen, historisches Modell, gespeicherte Gutschrift, Feiertag,
rohe Zehnerpagination und Seitenlokalität. SourceSnapshot inklusive Journal und
Sequenzen, native Audit-/Sessionglobals, PDO-Transaktionsende und Zeitzone bleiben unverändert.
Kein HTTPserver, Port 18083/18090, realer Datenbestand, Credential oder CI-Lauf;
keine Mikrotests, erschöpfende Matrix oder neue fachliche Fehlerkorrektur.

Am 27.10.2026 unter PHP 8.5.12 / Laravel 13.38.0 bestanden: dieser grobe
Memory-Nachweis, PHP-Syntax aller sieben betroffenen PHP-Dateien,
Composer-Validierung/Plattformanforderungen und `git diff --check`.
Mechanische Quellgegenprüfung erhält sämtliche nativen Echo-Markup-/SQL-/Link-
und Escapingbereiche bytegenau außerhalb der bezeichneten Datenaufbereitung;
`calculateDuration` wurde bytegenau einmal verschoben. Keine Web-/CSV-/Rollen-
oder vollständige #79-Abnahme wird damit behauptet.
