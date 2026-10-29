# #53: eigener nativer CSV mit Zeitraumwahl

Additiver Quellkandidat auf Main `6414c99462851a977b02e6269b1a6e0758c21a30`,
Baum `1cc0622c5858dd3a4b226efe3168a106049c4b85`. Der bereits vorhandene
Laravel-Vertrag aus [CH47-OWN-CSV-PERIOD.md](CH47-OWN-CSV-PERIOD.md) wird in der
nativen eigenen Arbeitszeitliste angeboten, nicht neu fachlich definiert.
Kein Team-/Lohnsoftwarevertrag oder vollständiger Abschluss von #53.

## Vorhandene Bedienung und gemeinsame Definition

Der bisherige Voll-CSV-Button bleibt unverändert. Direkt darunter steht dieselbe
standardmäßig eingeklappte Von/Bis-Auswahl wie in der vorhandenen Laravel-Liste,
mit denselben Hinweisen zum Berliner Starttag und zur vollständigen
Qualitätsprüfung. Kein neuer Dialog, JavaScript, Menü, Schreibvorgang oder
Personen-/Teamschalter. Ein gültiger leerer Zeitraum liefert den bisherigen Kopf.

`period=1&from=JJJJ-MM-TT&to=JJJJ-MM-TT` ist derselbe explizite Rohquery-Vertrag:
Jahr 0001–9999, echte Daten, einschließlich beider Grenzen, Von nicht nach Bis.
Fehlende, doppelte, arrayförmige oder ungültige neue Parameter ergeben 400 mit
festem Text statt eines scheinbar gültigen Downloads. Ein später Marker bleibt
auch jenseits von `max_input_vars` erhalten. Ohne Marker bleiben bislang
ignorierte Querys ignoriert; der vollständige eigene CSV bleibt bytegleich.

Es gibt keinen zweiten Parser oder Zeitfilter: Die bisherigen beiden reinen
Methoden von `CsvExportPeriod` stehen jetzt als Definitions-only-Funktionen in
`lib/csv_period.php`. Nur DTO-Rückgabe und Member-/Argumentzugriff unterscheiden
sich; die bestehende Laravel-Klasse behält ihren privaten Konstruktor,
Readonly-Felder und öffentlichen Methoden als dünner Adapter. Weder diese Datei
noch der neue Helper bootstrappen eine App, öffnen eine Verbindung oder Session.

## Qualitäts- und Tagesvertrag unverändert

Die native SQL-Abfrage liest weiterhin ausschließlich den vollständigen eigenen
Bestand und behält ihre tatsächliche Instant-/ID-Sortierung. Auch Administratoren
können keine fremde Person auswählen. Konflikt- und Qualitätsprüfung finden auf
demselben vollständigen Own-Snapshot **vor** der neuen Auswahl statt. Fehler und
ungeklärte Überschneidungen außerhalb des Zeitraums können weiterhin dieselbe
409-Antwort auslösen; es gibt keinen Teilbestands- oder Rohdatenfallback.

Erst danach wählt derselbe bestehende Selector anhand des Berliner Startdatums.
Eine Nachtschicht bleibt vollständig am Starttag, auch über Monats-/Jahres- und
DST-Grenzen. NULL/offene Endzeiten, Gutschriften und bestehende Grenzen bleiben
unverändert; kein Clipping, keine Tagesaufteilung und keine neue Soll-/Saldoformel.
Der bestehende Serializer einschließlich Spalten, BOM, Trennzeichen, Escaping und
Formelschutz bleibt unberührt. Erst vollständige Bytes werden als Anhang versandt.
Der markierte Dateiname nennt Von/Bis, der alte behält sein heutiges Datum.

## Begrenzter Nachweis

PHP 8.5.12: ein grober isolierter Journey führt den tatsächlichen nativen
Exportquelltext mit genau den beiden Bootstrap-Includes ausgelassen gegen eine
erfundene anonyme In-Memory-DB aus. Synthetische User-ID statt Anmeldung, keine
native App oder echte Session. Geprüft sind unveränderte Voll-CSV-Bytes,
Own-only, offene NULL-Zeile, Berliner UTC-Tagesgrenze, Herbststunde, ganze
Nachtschicht, Gutschrift, Formelschutz, leerer Jahr-0001-Bereich, fehlerhafte/
doppelte/arrayförmige Parameter, später Marker und unveränderte 409-Verweigerung
bei ungültiger Zeile oder Konflikt außerhalb des Bereichs. Wiederholtes Lesen
ändert weder Quellzeilen noch Änderungszähler oder Transaktionszustand;
`query_only` ist eingeschaltet. Die Formbytes entsprechen der bestehenden Blade.

Der vorhandene grobe Laravel-Reader-/Router-/Controller-/CSV-/Blade-Journey läuft
zusätzlich mit dem dünnen Adapter unverändert durch (Laravel 13.38.0). Hinzu
kommen Syntax, vollständige Quellerhaltung und statische unabhängige Prüfung.
Keine neue Repository-Testsuite, Workflowänderung oder manuell gestartete CI.

Nicht geprüft sind echte Middleware/Login/Berechtigungen, Cookies/CSRF,
Native-Appbootstrap, tatsächliche HTTP-Header/Browser/Download/Layout, betriebliche
Dateien/Schlüssel/DB, Multiworker oder Performance. Keine Runtime-/Host-/Security-
Konfiguration, externe Dienste oder Deployment. Teamnachweise, Soll-/Saldoexport,
PDF/XLSX und Empfängerschema bleiben offen; keine neue Sicherheits- oder
Produktivabnahme.
