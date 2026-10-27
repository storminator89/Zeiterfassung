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

Roh/unescaped: `current_month`, `current_date`, `working_days_this_month`,
`total_overtime_hours`, `total_overtime_formatted`, `positive_overtime`,
`daily_hours_formatted`, `holidays_this_week` (`date,formatted_date,name`). Nur
hinterlegte Wochenfeiertage werden gelesen, nicht nachgeladen.

## Aktueller Diagnosevertrag (#16 / #35)

Auf Main165ce380 wird ausdrücklich `report_days($records, $invalid, $conflicts)`
verwendet, nicht die nichtdiagnostische fail-closed-Variante ohne Ausgabebags.
Die zusätzlichen Felder `invalid_records`, `absence_conflicts`,
`report_finality` gehören ausschließlich zum eigenen Home-Anzeigevertrag.
Ungültige Zeilen/ganztägige Überschneidungen werden durch die vorhandenen
nativen Helfer erkannt. Bei einem solchen Befund sind kumulierte Überstunden
`null` statt einer abschließenden Zahl, mit „Unvollständig“ beziehungsweise
„Klärung offen“ und `positive_overtime=null`. Die opt-in Blade-Ansicht zeigt
die Diagnose und den bestehenden Korrekturweg sichtbar an. Keine Datenreparatur,
kein Löschen, keine neue Zeitkonto-/Konfliktprioritätsregel.

Der native **Web**-Status erhält dieselben `report_finality`-Bools für
all/week/month/year wie `attendance.php?format=status`; aktuelle eigene Zeilen,
Konfliktkontext und Anwesenheitsstatus teilen einen Lesesnapshot. Die separat
vorhandenen API-Status-/Zeiteintrags-/Periodenmethoden bleiben unverändert.

## Gemeinsame Tabellen-Datenaufbereitung

Nur Home ruft `reportRows(chronological: true)` auf: vorhandene native
`report_register_start_order`-UDF, Sortierung nach Zeitpunkt und danach ID,
anschließend zehn Rohzeilen. Der parameterlose Standard behält die bisherige
lexikalische Reihenfolge für Korrekturoptionen.

`report_time_record_groups($pageRows, $completeOwnRows)` bekommt denselben
vollständigen eigenen Snapshot zusätzlich zu den sichtbaren Details. Kein SQL,
HTML, Session oder Escaping im Helfer. Summen umfassen alle Tageszeilen;
`is_partial/day_entry_count` beschreibt sichtbare/alle Einträge. Vollständiger
Kontext erfasst auch off-page- und Übernachtkonflikte vor Datum-/Seitenfilterung.
Ungültige Starts bleiben als getrennte opaque `group_key`-Gruppen ohne erfundenes
Datum sichtbar. Ungültige Tage zeigen `Ungültig` statt Null und `total_pause=null`;
Konflikttage zeigen `Klärung offen`, während Originaleinzelwerte erhalten bleiben.
`is_invalid/validation_error/is_unresolved` begleiten die Einzelansichten.
Gutschriften zeigen weiter ihre gespeicherten Sollminuten und keine Stempelzeit.
Die UI escaped die Anzeigeprojektion und übernimmt dieselben nativen Warntexte.

## Begrenzte Nachweise und offene Abnahme

Die ursprünglichen gemeinsamen Projektionen wurden am 27.10.2026 mit einem
groben synthetischen Memory-Nachweis geprüft. Das beschreibt den historischen
Stand, nicht eine neue Abnahme der aktuellen Diagnose-/Versionsintegration.
Die vorhandenen Tools `rehearse-home-projection.php` und
`rehearse-domain-reads.php` haben notwendige genaue Erwartungsanpassungen für
den zusätzlichen Vertrag; in dieser Integration wurden sie **nicht ausgeführt**.

Aktuell: notwendige Syntax/Composer/Plattform und fünf ganze Bladeansichten
mit reinen synthetischen Arraydaten; dabei DBverbindungen0, keine Guard-/Auth- oder
Credential-/CSRF-Injektion, HTTPanfrage, Fixture oder Server/Browser gestartet.
Keine lokale Funktionsabnahme, neue CI, reale Daten-/Schlüsseländerung oder
Native-Runtimeänderung. Der bestehende Home-Browserloginblock bleibt erhalten.
Draft #110/#111 sind nicht merge-ready; native Seitendarstellung beweist keine
Laravel-UI-Abnahme und ersetzt keine legitime Auth-/Funktionsabnahme.
