# FW-06 / #79 — bestehende Vorgesetztenansicht, noch inaktiv

Basis: Main `729250b0c866fcfc899eff47b052997144cf3842`.
Dieses Paket portiert **die vorhandene `supervisor.php`-UI**, kein neues Dashboard.
Es registriert keinen Einstieg und schaltet keine Funktion frei.

## Enger Quellumfang

- `lib/supervisor_projection.php`: mechanisch übernommene native Berechnung,
  Gruppierung und vorbereitete Anzeige; bestehende `report_*`- und
  `planned_minutes()`-Helfer bleiben die Fachautorität.
- `ChronoReads::teamReport()` / `LegacyChronoReads::teamReport()`: ausschließlich
  additive Lesemethode; bestehende Methoden und ihr Snapshot-Lebenszyklus bleiben
  unverändert. Die separat vorbereitete `calendarWeek()`-Erweiterung ist bei
  Integration **additiv zu erhalten**, nicht durch diese Basis zu ersetzen.
- Neuer `LocalWebSupervisorController`, `SupervisorPage` als **Inhalts-DTO**, eine
  Blade-Ansicht und zwei inerte de/en-Labeldateien mit exakten Bestandswerten.
  Kein paralleles Chrome-Modell: der Controller verwendet `AttendancePage`.

`supervisor.php` bleibt **byte-identisch**, ebenso die übrigen nativen Quellen,
Routes, Standard-Chrome/Layout, `AttendancePage`, Auth/Policies/Provider,
Konfiguration und Abhängigkeiten. Die Berechnung ist eine **vorübergehende
Kopie aus dem nativen Bericht**, keine unabhängig neu entworfene Formel.
Erst ein separat geprüfter Legacy-Cutover darf die native Stelle durch den
Helfer ersetzen. Dieses Paket behauptet diese Zusammenführung noch nicht.

## Herkunft und bewahrte Eigenheiten

Aus der unveränderten `supervisor.php` dieser Basis:

1. Zeilen 38–110: Zeilenvorbereitung und vollständige Überstunden-/Modalwerte.
2. Zeilen 306–360: Gruppierung, Mitarbeitersuche vor Paging, zehn **Gruppen** je
   Seite und begrenzte Seitenwahl. Native rohe `page`-String-/Arraysemantik
   bleibt erhalten; der Controller liest dafür den originalen Querystring.
3. Zeilen 367–385 und 406–419: numerische Tabellen-/Detailformatierung wird nur
   aus dem Renderpfad in vorbereitete Anzeigewerte verschoben.
4. Vorhandene Klassen, IDs, zwei Chartdefinitionen, vorzeichenbehaftete
   **Ganzzahl-Minuten** für Chart/Kartenfilter, Kartensuche, native
   Detaildialog-/Expandierungsskripte und native Sprachkonstanten.
   Chartdaten bleiben Zahlen, nicht aus `HH:MM` zurückgerechnete Werte.

Bewusst keine neue Fachpolitik: Überstunden gelten weiter für erfasste
abgeschlossene Starttage, nicht für sämtliche Kalendertage. Vortragsstunden,
Versionsmodelle und Feiertage verwenden weiter die Bestandslogik. Offene
Einträge erhalten keine abgeschlossene Zeitgutschrift; bestehende offene
Tabelleneinträge zeigen weiter ihre native Null-Dauer. Das Regelarbeitszeit-Chart
zeigt weiterhin `users.regelarbeitszeit`, nicht ein neu berechnetes Tagesmodell.
Suche/Paging beeinflussen nur die Tabelle, nicht die vollständigen Karten- und
Modalwerte. Keine Offboarding- oder neue Rollenlogik.

### Zeitzonenkontext, keine globale Umstellung

Native `config.php` setzt `Europe/Berlin`; die Foundation bleibt `UTC`.
Die Extraktion verändert **keinen** globalen Zeitzonenwert:

- Der native Gruppen-Ausdruck `date('Y-m-d', strtotime(startzeit))` unter Berlin
  wird durch den bereits vorbereiteten kanonischen `report_day()`-Wert ersetzt.
- Native `new DateTime(raw)` erhält explizit den bisherigen Standardkontext
  `new DateTimeZone('Europe/Berlin')`. Ein im Wert gespeicherter expliziter
  Offset bleibt dabei **unverändert wirksam**, wie nativ.
- Daher können Detail-Wanduhrzeit und Berlin-Gruppendatum bei expliziten
  Fremdoffsets weiter unterschiedlich aussehen. Das ist bewahrtes Verhalten,
  **keine behauptete allgemeine Normalisierung**.

Der grobe Äquivalenznachweis vergleicht originale native Ausdrücke in einem
separaten Berlin-CLI-Prozess mit dem Helfer in einem UTC-CLI-Prozess, einschließlich
Offset über die Tagesgrenze, offsetfreier Werte und DST. Keine globale
Zeitzonenmutation im Helfer oder Testskript.

## Autorisierter Umfang und nicht abschließende Ergebnisse

Nur die bestehende Guard-Identität bestimmt den Akteur. Der unveränderte
admittierte Lesesnapshot lädt dessen aktuellen Account/Rolle neu. Vor jeder
Berichtsabfrage muss diese Rolle `admin` oder `supervisor` sein; sonst wird
verweigert. Die SQL-Abfrage entspricht dem nativen Bericht:

```sql
WHERE u.supervisor_id = ? OR ?='admin'
ORDER BY z.startzeit DESC
```

Admins sehen den nativen gesamten Join-Umfang; Vorgesetzte ausschließlich ihre
direkt zugeordneten Accounts. Keine implizite Aufnahme eigener Einträge,
transitiven Teams, Request-Identität/-Rolle oder frei wählbaren Personen.
Die bestehende Rolle-`user`-Warnung bleibt im Inhaltsmarkup erhalten, ist aber
kein Ersatz für diese serverseitige Lesesperre.

**Vor** Suche/Paging prüft der gemeinsame Helfer alle freigegebenen Zeilen mit
`report_record_calculation()` und `report_absence_conflicts()`. Jeder ungültige
Eintrag oder Arbeitszeit-/Ganzabwesenheitskonflikt macht diesen ganzen Bericht
absichtlich nicht verfügbar. Das ist die freigegebene vorsichtige
Migrationsausgabe: keine numerischen Teilkarten, Charts, Modaldaten oder
Tabellensummen. Kein Löschen, Kürzen oder Ersetzen ungültiger Werte durch null
Minuten. Historie, eigene Arbeitszeitliste und Korrekturanträge bleiben verlinkt;
die betroffene Person klärt per bestehender Vier-Augen-Korrektur. Dies ändert
nicht die noch unveränderte native Ausführung.

## Noch zwingend offen, nicht aktiviert

1. Separat geprüfte Aufnahme von `supervisor.php` in die **Standard-**
   `AttendancePage`-Activepage-Whitelist, gegebenenfalls Sidebar-Markierung und
   einen geschützten GET-/HEAD-Einstieg mit vorhandener Accountaktualisierung.
   Der neue Controller ist bis dahin absichtlich nicht dispatchbar; keine
   Parallel-Chrome- oder Whitelist-Umgehung.
2. Explizite Zulassung des **vorhandenen** Chart-Assets im bestehenden
   Sicherheits-/Assetkonzept. Dieses Paket fügt keinen externen ausführbaren
   CDN-Script und keinen Tailwind-JS-Compiler hinzu. `initializeCharts()` behält
   den nativen `typeof Chart`-Schutz; Chart-Funktionsabnahme steht aus.
3. Geschützte UI-/Integrationsabnahme im dafür autorisierten veröffentlichten
   Ziel. Kein Browser-/Auth-/Serverergebnis wird hier behauptet.

## Nur grober Quellnachweis

PHP-8.5-Lint, statische Syntaxprüfung der gerenderten Inline-Skripte und ein
zusammenhängender grober Nachweis mit anonymem `sqlite::memory:` für bestehende
Modell-/Feiertagsleser, originaler Formeläquivalenz und reinem de/en-/leer-/
Datenqualitäts-/Konflikt-Inhaltsrendering. Das Chrome wird dabei durch ein
**inertes Proof-Layout** ersetzt und nicht ausgeführt. Keine Anwendungsboot-,
Controller-, Auth-/Sessioninjektion, echte DB-, HTTP-, Server-, Browser- oder
JavaScript-Funktionsprüfung; keine neue CI und keine Veröffentlichung/Deployment.
