# FW-08 / #79 — eigener CSV-Export in der vorhandenen UI

Basis: Main `9ca501a58e14d704ac0c2c2b504743d493645cb2` nach der optionalen
Dashboardverdrahtung. Dieser Source-Schritt ergänzt den fehlenden bestehenden
Export, keine neue Reporting-/Payroll-/Teamfunktion und kein Redesign.

## Exakter Zwölfpfad-Umfang

Neu: `lib/csv_safety.php`, `lib/csv_export.php`,
`LocalWebCsvExportController.php` und dieses Dokument.
Geändert: ausschließlich der native `csv_safe()`-Wrapper in `security.php`,
`LocalWebRoutes`, `LocalWebHomeController`, `TimeRecordsPage`, die vorhandenen
Arbeitszeitfragment-/Standardlayout-Vorlagen, `LocalWebFixture` und ihr fester
Router. Kein neuer Containervertrag, Reader, Writer oder Paketinstall.

Im ursprünglichen Zwölfpfad-Paket blieb der aktive native Endpoint
`export_csv.php` **byte-identisch**; der begrenzte native #16-Nachtrag unten
beschreibt seine spätere gesonderte Korrektur. Alle
anderen Security-/CSRF-/Session-/Throttle-/Schlüsselbytes, Fachhelfer,
Auth-/Policy-/Provider-/Bootstrap-/Konfigurationsdateien sowie Kalender- und
Terminalquellen bleiben erhalten. Die vorhandenen Registrarparameter und
Asset-/Sessionzulassungen werden nicht ersetzt.

## Eine gemeinsame unveränderte CSV-Formelschutzregel

Der bisherige reine Funktionskörper aus `security.php:74–77` wird exakt als
`csv_safe_text($value): string` in `lib/csv_safety.php` weiterverwendet. Der
native Name und die Signatur `csv_safe($value): string` bleiben am bisherigen
Deklarationsort erhalten; der Wrapper lädt nur diesen reinen Helfer lazy und
delegiert. Seine String-/Nullkoerzierung, Unicode-Regulärausdruck und das
vorangestellte Apostroph bleiben gleich. Keine doppelt neu entworfene
Tabellenkalkulations-Formelerkennung.

Laravel lädt diesen reinen Helfer und die vorhandenen definitions-only
`attendance.php`-/`home_projection.php`-Berichtsfunktionen, **niemals**
`security.php`, `config.php` oder `functions.php`. Das schließt unbeabsichtigte
native Session-, CSRF-, Datenverzeichnis-, Schlüssel- oder Kalenderaktionen aus.

## Vollständiger eigener Snapshot, kein freier Personenwähler

Der neue Controller ruft genau einmal den bereits bestehenden
`ChronoReads::reportRows(chronological: true)` auf. Die unveränderte
`LegacyChronoReads`-Implementierung lädt den Guardaccount innerhalb desselben
admittierten Lesesnapshots neu und liest **nur dessen** gesamte Rohzeilen in
`chrono_report_start_epoch(startzeit) DESC, id DESC`-Reihenfolge.
Auch Admins erhalten hier nur eigene Zeilen. Es gibt keinen Request-,
`user_id`-, Team-, Such-, Datums- oder Seitenparameter an diesem Aufruf.

Das vollständige materialisierte Array wird vor der Serialisierung mit dem
bestehenden `report_absence_conflicts()` geprüft. Ein Arbeitszeit-/ganztägiger
Abwesenheitskonflikt liefert den **exakten nativen 409-Text** mit
`text/plain; charset=UTF-8`, ohne Attachmentheader und ohne CSV-Teilinhalt.
Ein Gegenstück auf einem anderen Starttag oder jenseits einer Tabellenseite
wird dadurch nicht abgeschnitten. Dieselben Snapshotzeilen bestimmen Prüfung
und Ausgabe; es gibt keine zweite spätere Abfrage.

Der reine Serializer erwartet diesen bereits freigegebenen/vollständig
geprüften Input; er ist selbst **kein** Auth-/Personen-/Konfliktgate. Er
serialisiert jede übergebene Zeile und führt weder Queries noch Filter aus.
Ungültige Zeilen werden nicht heimlich ausgelassen oder in neue Nullminuten
umgedeutet. Die bestehenden `booking_*`, `calculateDuration()` und
`report_*`-Regeln gelten weiter, einschließlich ihrer bisherigen `-`-/Fehler-
Semantik für offene bzw. zuvor ausgeschlossene Zeilen.

## Bytevertrag und Antwortgrenze

Aus den unveränderten nativen Formatierungsausdrücken übernommen:

- UTF-8-BOM `EF BB BF`, Semikolon, `"` als Enclosure und explizit `\\` als
  fputcsv-Escape; dieselbe abschließende UTF-8→UTF-8-Konvertierung.
- Genau neun Spalten in bestehender Reihenfolge: `Datum`, `Start`, `Ende`,
  `Pause (Min)`, `Arbeitszeit / Gutschrift`, `Standort`, `Beschreibung`, `Art`,
  `Gutschrift (Min)`. Keine neue Sprache oder zusätzliche Spalte.
- Berlin-Datum und -Wanduhrzeit über die vorhandenen Buchungshelper, echte
  Dauer über `calculateDuration()`, keine neue DST-/Nacht-/Pausenformel.
  Abwesenheitsgutschriften behalten leere Start-/Endspalten und unveränderte
  gespeicherte `planned_minutes`. Pause null/0 bleibt nativ leer.
- Der gemeinsame Formelschutz bleibt auf genau den bestehenden Textfeldern
  aktiv. Keine Umdeutung in Excel-/Payroll-/Arbeitszeitkontofachlichkeit.

Die CSV wird **vollständig** in `php://temp` materialisiert und als kompletter
String gelesen, bevor eine gewöhnliche Symfony-`Response` mit Attachmentheadern
konstruiert wird. Schreib-/Lese-/Rewindfehler werden verweigert, der temporäre
Stream wird per `finally` geschlossen. Kein `echo`, `header()`, `readfile()` oder
später `StreamedResponse`-Callback, der nach Headerausgabe noch an einer späteren
Zeile scheitern könnte. `php://temp` kann wie nativ bei größeren Inhalten in eine
temporäre Datei auslagern; es handelt sich nicht um einen Live-DB-Download.

Erfolg: `text/csv; charset=UTF-8`, Attachment
`Arbeitszeiterfassung_YYYY-MM-DD.csv`. Das Dateidatum wird wie nativ zwischen
Konfliktprüfung und Serialisierung erfasst; dafür explizit `Europe/Berlin`
statt Veränderung von Laravels globalem UTC-Standard.

**Bewusste Framework-Transportabweichung:** Ein technischer Lese-/
Serialisierungsfehler liefert generischen **500-Text**, keine rohe Exception,
kein erfolgreiches CSV-Attachment und keinen bereits geschriebenen Zeilenrest.
Der native Endpoint hatte dafür einen rohen `die()`-Fehlertext, gegebenenfalls
noch unter CSV-/Erfolgsheadern; im ursprünglichen Framework-Paket wurde dieser
aktive Endpoint nicht geändert. Der gesonderte native Nachtrag folgt unten.
Eine `AuthenticationException` wird dagegen nicht abgefangen/umgedeutet, sondern
an den vorhandenen Authfehlerpfad weitergereicht.

## Optionaler geschützter Einstieg und bestehender Exportknopf

`LocalWebRoutes::register(..., bool $includeCsv = false)` hängt den neuen
Parameter nach allen vorhandenen optionalen Parametern an. Nur explizites
`includeCsv: true` ergänzt den benannten `GET|HEAD export_csv.php`-Einstieg
`chrono.export-csv` innerhalb des unveränderten Websession-/CSRF-/Sessionblocking-
Stacks und `RefreshLocalAccount` → `auth:web` → `auth.session`. Kein POST,
Schreibpfad, allgemeiner Downloadrouter oder Cookie-/Authwechsel. Nur dessen
opt-in Authfehler wird als Seitennavigation zu `login.php` weitergeleitet.
Defaultbootstrap, `routes/web.php`, API und Terminal bleiben unberührt.

Der bestehende Homefragment-Controller fragt ausschließlich Stock-
`Router::has('chrono.export-csv')` ab und reicht diesen tatsächlichen
Registrierungszustand als angehängten, standardmäßig falschen Anzeigewert an
`TimeRecordsPage` weiter. **Kein** Requestparameter oder paralleles
Interaktionsflag darf den Link behaupten. Wenn die Route fehlt, bleibt der
bisherige Exportknopf exakt deaktiviert; wenn sie existiert, bleiben dessen
Klassen, Icon und Beschriftung erhalten, als normaler fester Link
`href="export_csv.php"`. Keine neue UI oder Abhängigkeit von zusätzlichem JS.
Die Standard-Chromenotiz entfernt nur die nun unzutreffende pauschale
Behauptung, CSV sei in jeder Vorschau unverbunden.

Nur die bereits explizit gestartete synthetische Composition erhält den neuen
Opt-in; ihr fester Router erlaubt genau zusätzlich `/export_csv.php`. Sie wurde
für diesen Source-Schritt **nicht** gestartet oder authentifiziert. Keine
Defaultaktivierung, Produktionsdaten-/Konten-/Host-/Schlüsseländerung oder
Umgehung bestehender Browser-/Auth-/WIP-Holds.

## Grober Nachweis und offene Grenzen

Ein zusammenhängender erfundener 13-Zeilen-Bytevertrag vergleicht BOM, neun
Spalten, alle Zeilen/reine Inputreihenfolge, DST/Nacht/offsetfreie Werte,
Gutschrift/offene Zeile, Quotes/Mehrzeiler/Unicode und den unveränderten
Formelschutz mit den originalen nativen reinen Formatierungsausdrücken.
Ein später fehlerhafter Datensatz erzeugt keinen ausgegebenen Teilinhalt;
Konflikte werden anhand des kompletten erfundenen Starttagkontexts erkannt.
Das sind keine Live-Daten, keine echte CSV-/Browserdownload-Abnahme und keine
Konten-/Auth-/Middleware-Simulation.

Zusätzlich nur PHP-8.5-Lint, statische Blade-Kompilierung und fragmentreine
Knopfvarianten sowie Quell-/Bytebewahrung einschließlich komplettem Reverse-
Tree-Nachweis. Keine Anwendungs-/Routerboots, Controller-/HTTP-/Auth-/Session-/
DB-/Server-/Browser-/JavaScript-Funktionstests, neue CI oder Veröffentlichung.
Geschützter grober Download, Header-/Browser-/Berechtigungsablauf und operative
Speicher-/Fehlergrenzen bleiben separat offen; dieses Paket schließt #79/#78
oder den vollständigen Laravel-Cutover nicht.


## Gesonderter nativer #16-Nachtrag: vollständige Bytes vor Attachment

Basis: Main `fd166a2d3e78be9d40a873f93ad0c2c398a6561f`, Tree
`31d9c067297778e47f84911f98e9c7d98c6d1dec`. Genau zwei vorhandene Dateien:
`export_csv.php` und dieses Dokument. Keine Framework-/Helper-/Schema-/Routen-/
Defaultaktivierungsänderung, keine neue Fachformel oder Datenkorrektur.

Der native Export verwendet jetzt denselben unveränderten reinen
`report_csv_document()`-Serializer. `config.php`, `security.php` und die dortige
aktuelle Account-/Passwortversionsprüfung bleiben unverändert. Der bisherige
**wirksame** unauthentifizierte `302 Location: login.php` bleibt vor der Abfrage
erhalten: zuvor führte `functions.php` diesen Redirect aus, bevor das alte
`Not authorized`-`die()` des Exports überhaupt erreichbar war. Kein neuer
Admin-/Team-/Personenwähler und keine Methoden-/Session-/CSRF-/Authregel.

Nur das für diese Ausgabe unbenötigte `functions.php`-Include entfällt zugunsten
der definitions-only CSV-Datei. Damit führt der Export nicht mehr dessen
zusätzliche Dashboardabfragen, Feiertags-Netzzugriff/-INSERT oder dortige rohe
SQL-`die()`-Fehler aus. Der native Konfigurationsbootstrap selbst wird nicht
umgebaut, umgangen oder als nebenwirkungsfrei behauptet; seine bereits eigenen
Fehler-/Migrations-/Sessiongrenzen bleiben außerhalb dieses Nachtrags.

Die exakt bisherige eigene Materialisierungsabfrage und Instant-/ID-Reihenfolge
bleiben gleich. Ein Admin exportiert weiterhin nur seine eigenen Zeilen.
Die unveränderte Konfliktprüfung bleibt zuerst und liefert denselben festen
409-Text ohne Attachment. Danach prüft `report_record_calculation()` **jede**
Zeile desselben vollständigen Arrays. Ein kanonisch ungültiger Start, Endwert,
Pausenwert oder rückläufiger/negativer Zeitraum verweigert den gesamten Export
mit ausdrücklichem `409 text/plain; charset=UTF-8` und Verweis auf eigene Liste/
Korrekturanträge. Keine IDs, Originalwerte oder rohen Diagnostikdetails im Fehler.
Keine still ausgelassene Zeile, Ersatz-Null oder teilweise fertige CSV.
Gültige offene Zeilen sind nicht ungültig: leere Endspalte und bisherige
`-`-Dauer bleiben erhalten; keine neue Endzeit oder laufende Gutschrift.

Erst nach bestandenem Gate wird das Dateidatum wie bisher vor der eigentlichen
Serialisierung unter der nativen Berlin-Konfiguration erfasst. Erst wenn der
bestehende Serializer den **gesamten** String fertiggestellt hat, werden
`Content-Type: text/csv; charset=UTF-8` und der unveränderte Attachmentname gesetzt
und genau diese Bytes ausgegeben. BOM, neun Spalten, Semikolon, Quotes/Escape,
Formelschutz und offene/Gutschrift-/Nacht-/Offsetsemantik bleiben unverändert.
Ein technischer Fehler innerhalb dieser Exportgrenze wird als fester generischer
`500 text/plain; charset=UTF-8` ohne Attachment beantwortet, nie mit roher
Exceptionnachricht, SQL, Pfad oder Zeilenrest. `Throwable` schließt hier auch
technische Type-/Formatfehler ein; es gibt kein automatisches Retry.

**Materialisierungsgrenze:** `php://temp` kann bei größeren Ausgaben wie bisher
auf temporären Speicher auslagern; vollständige Zeilen und Dokumentbytes brauchen
Speicher. Es gibt absichtlich kein stilles Limit, Paging oder Kürzen. Der reine
Serializer prüft seine normalen Schreib-/Lese-/Rewindfehler und schließt den
Stream in `finally`. Fatales Speicherende, Prozessabbruch, nachgelagerte
SAPI-/Netzfehler und alle früheren Bootstrapfehler werden dadurch nicht zu einer
beweisbar abfangbaren oder betrieblich freigegebenen Fehlergrenze.

Ein einziger grober erfundener Source-/Byte-/Antwortgrenzenvertrag vergleicht den
originalen Formatierungsblock mit dem gemeinsamen Serializer, gültige offene/
Gutschrift-/Textwerte, eine späte ungültige Zeile, den unveränderten Konflikttext
und einen synthetischen technischen Fehler. Der Authfluss wird ausschließlich an
den Originalquellgrenzen geprüft, nicht durch Session-/Logininjektion oder echte
Anfragen. Laufzeit: PHP **8.5.12**, Prozesszeitzone **Europe/Berlin**; vier
erfundene gültige Zeilen ergeben bytegleich 367 Bytes, Antwortfragmente
200/409/409/500. Zusätzlich PHP-Syntax und exakter Quellerhalt aller anderen Dateien.
Keine echte CSV, Buchung, Konten-/Saldoaktion, DB, Credentials, Header-/SAPI-/HTTP-/
Browser-/Downloadabnahme oder neue CI. Laravel behält seinen bisherigen
Materialisierungs-/generischen500-Vertrag unverändert; dieser Nachtrag erklärt
keine vollständige #16-/#79-/Cutover- oder Produktivabnahme für bestanden.
