# #53 / CH-47: eigener Laravel-CSV mit Zeitraumwahl

Enger lokaler Quellstand auf Basis Main
`81d582f1d6491b5833dc349b44b3d9bf1110ba92` (nach #51 / PR #243). Eine direkt nutzbare persönliche
Zeitraumwahl, kein Team-/Kanzlei-/Lohnsoftwarevertrag und keine vollständige
Issue-Abnahme. Keine native/API- oder produktive Profilaktivierung.

## Bedienung und Kompatibilität

In der bestehenden eigenen Arbeitszeitliste steht neben dem bisherigen Voll-CSV
unter demselben vertrauenswürdigen `csvExport`-Anzeigeschalter ein aufklappbares
Von/Bis-Formular. Ohne aktivierten CSV-Pfad ist das Formular nicht sichtbar.
Beide Felder sind erforderlich; ein GET-Download schreibt keine Buchung.

Nur der neue explizite Aufruf
`export_csv.php?period=1&from=2026-10-01&to=2026-10-31` wählt den Zeitraum.
Ohne `period` bleiben bisher ignorierte Querys (`from`, `to`, Suche, Seite,
`user_id` usw.) ignoriert und der bisherige Voll-CSV bleibt erhalten. Der alte
Downloadbutton ändert sich nicht. Es gibt keinen neuen Personen-/Teamschalter;
auch Administratoren exportieren ausschließlich ihre eigenen Zeilen.

Der neue Vertrag akzeptiert genau den String `period=1` und skalare, einmalige
`from`-/`to`-Felder im Format `JJJJ-MM-TT`, Jahr 0001–9999, gültige Kalenderdaten,
Von nicht nach Bis. Keine Trimmung oder Reparatur von Rohparametern. Arrayformen,
doppelte neue Felder und ungültige/fehlende Daten führen zu festem Text mit 400,
nie zu einem vermeintlich passenden CSV-Anhang. Die drei eigenen Felder werden
direkt aus der rohen Query gelesen; ein nach `max_input_vars` stehender Marker
kann deshalb nicht verschwinden und versehentlich einen Voll-CSV auslösen.
Andere ignorierte Felder werden nicht als Identität oder Auswahlautorität benutzt.

## Qualitätsumfang: kompletter Own-Snapshot, dann Filter

**Die bisherige Konflikt- und Qualitätsprüfung bleibt auf dem vollständigen
autorisierten Own-Snapshot, VOR der Zeitraumfilterung.** Eine ungültige Zeile
oder ungeklärte Arbeitszeit-/Abwesenheitsüberschneidung außerhalb des gewählten
Zeitraums kann den Download weiterhin mit der bisherigen 409-Antwort blockieren.
Das Formular weist ausdrücklich darauf hin. Es gibt keine stille Teilausgabe,
keinen Rohdatenfallback und keine abgeschwächte Qualitätsfreigabe.

Erst nach diesen unveränderten Prüfungen werden die bereits validierten Zeilen
anhand ihres **Berliner Startdatums** inklusiv ausgewählt. Wie in der bestehenden
UI bleibt eine Nachtschicht vollständig beim Starttag – auch über Monats- und
Jahresgrenzen sowie 23-/25-Stunden-Tage. Keine Zeitkürzung, tageweise Aufteilung,
neue Soll-/Saldoformel oder Änderung an offenen Buchungen/Abwesenheitsgutschriften.
Sortierung und der bisherige native CSV-Serializer einschließlich Spalten,
BOM, Trennzeichen, Escaping und Formelabsicherung bleiben unverändert.

Der neue Dateiname enthält beide bestätigten Datumsgrenzen:
`Arbeitszeiterfassung_2026-10-01_2026-10-31.csv`. Der unmarkierte Voll-CSV behält
seinen bisherigen heutigen Dateinamen und dieselben Nutzbytes. Ein leerer,
gültiger Zeitraum liefert nur den bisherigen CSV-Kopf. Antwortbytes werden
weiter vollständig erzeugt, bevor ein erfolgreicher Anhang zurückgegeben wird.

## Nachweis und offene Grenzen

PHP **8.5.12**, Laravel **13.38.0**: ein grober Durchlauf nutzt den echten
Own-Reader, internen Laravel-Router einschließlich Request-Injektion, geänderten
Controller, unveränderten CSV-Serializer und tatsächlich gerenderte Blade.
Erfundene Daten decken Berliner UTC-Datumsgrenzen, DST-Herbststunde, eine ganze
Nachtschicht, Gutschrift, offene Zeile und CSV-Formeltext ab. Der unmarkierte
Voll-CSV ist bytegleich; ein fremder fehlerhafter Datensatz bleibt außerhalb
des Own-Readers. Wiederholter Download schreibt nichts. Ein ungültiger Zeitraum
führt vor Datenqueries zu 400, eine ungültige eigene Zeile außerhalb des Bereichs
weiter zu 409. Der Parser behält einen späten Marker jenseits von 1000 fremden
Parametern; die aktive und deaktivierte Formularansicht wurden gerendert.
`query_only` und Quellsnapshots bestätigen keine Mutation/Transaktionsreste.

Dies ist **keine neue Sicherheitsabnahme**: erfundene In-Memory-DB und injiziertes
bestehendes Guard-Modell, nicht echte Middleware/Login/Cookies/Session/CSRF,
HTTP-Header auf dem Draht, Browser/Download/Layout, V5-Datei-Writer, reale
Betriebsdatenbank oder Konkurrenz-/Performanceprüfung. Keine neuen Abhängigkeiten,
GitHub-CI-Läufe, Remoteänderung oder Veröffentlichung in diesem lokalen Paket.

Teamnachweise, Soll-/Saldoexport, PDF/XLSX und DATEV bleiben bis zum konkret
abgestimmten Empfänger-/Kanzleischema offen. Der eigene Zeitraum-CSV behauptet
keine Kompatibilität mit einer Lohnsoftware und schließt #53 nicht vollständig.
