# CH-11 / #16: native Supervisor-Auswertung bei ungültigen Originalzeiten

Begrenzte Quellkorrektur auf Main `05a7ef29e4cf224a9d56ebd21262601938ff2636`
(Tree `128a69787ef061608ca905a451b5647f92e1548f`), nach #17-Teamreihenfolge.
Kein Framework-/Auth-/Produktiv-Cutover und keine vollständige #16-Abnahme.

## Grenze vor Datum und Summen

`supervisor.php` verwendet nach der unveränderten autorisierten Abfrage für
Admins bzw. direkte Teams zuerst **für jede Originalzeile** die bestehende
`report_record_calculation`. Die ebenfalls bestehende
`report_absence_conflicts` prüft denselben vollständigen Umfang. Erst danach
sind Suche und Seitenschnitt zulässig; sie können einen Befund außerhalb der
aktuellen Seite nicht verstecken. Die bereits eingeführte SQL-Sortierung bleibt
unverändert: Nur nicht durch `booking_time` parsebare Starts erhalten dort `NULL`.
Relative oder datum-only Bestandswerte können einen alten SQL-Sortierschlüssel
behalten, obwohl die strengere kanonische Prüfung sie als ungültig kennzeichnet.
Diese Diagnose hält den Bericht trotzdem vor einer Datumsgruppe oder Summe an;
der Fallback erfindet weder Datum noch Minuten.

Bei mindestens einer ungültigen Zeile oder einem Arbeits-/Abwesenheitskonflikt
ist die **gesamte unabhängige Auswertung nicht verfügbar**: keine Überstunden-,
Tages-, Durchschnitts-, Pausen- oder Diagrammsummen und kein Zahlen-Detaildialog.
Es gibt keine Ersatz-Null, Teilsumme, automatische Reparatur oder Kürzung.
Insbesondere werden rückläufige Intervalle und Pause größer als Bruttozeit
nicht mehr als bloß fehlende Minuten dieses Berichts verschluckt. Ein gültiger
offener Eintrag bleibt `open`, nicht `invalid` und nicht abgeschlossene Arbeit.

Die gemeinsame Berechnung, Berlin-Annahme, Rollen-/Teamscope,
Abwesenheitskonfliktentscheidung und Vier-Augen-Korrektur bleiben unverändert.
Bei ausschließlich gültigen/offenen, konfliktfreien Zeilen bleiben sämtliche
bisherigen Formeln, Rundungen, offenen Darstellungsquirks, Karten, Diagramme,
Details und zehn **Tagesgruppen** pro Seite erhalten. Keine neue Bewertung von
Arbeitsmodellfehlern, keine pauschale Ausnahmebehandlung von SQL-/Systemfehlern.
Der bereits sichere Laravel-Projektionspfad wird nicht verändert.

## Sichtbare Originalwerte statt falscher Datumsgruppen

Auch im nicht verfügbaren Bericht bleiben **alle** bereits autorisierten
Originalzeilen erreichbar, nicht nur die fehlerhaften. Die vorhandene
Tabellen-/Kartenoptik und Benutzernamensuche bleiben; der klar beschriftete
Fallback zeigt bis zu zehn **Originalzeilen** pro Seite statt Tagesgruppen.
Ein ungültiger Start wird weder einem erfundenen Datum/1970 noch einer gültigen
Tagesgruppe zugeordnet. Suche erfolgt weiterhin vor dem Seitenschnitt und
verändert den globalen Hinweis nicht.

Ausgegeben werden nur die bereits in der Übersicht vorhandenen Felder:
Benutzername, unverarbeiteter Start-/Endtext, Pause, Standort und Kommentar.
Kein neuer Benutzer-/Datensatz-ID-Wert, kein fremder Bearbeitungs- oder
Aktionslink. Gültige und offene Zeilen werden nicht aus dem Fallback entfernt.
Ungültige Zeilen tragen ein sichtbares Kennzeichen und die feste Fehlermeldung
der gemeinsamen Berechnung. Alle Originaltexte und Meldungen werden
HTML-escaped; ungültige UTF-8-Ausgabebytes werden nur in der Darstellung ersetzt,
nicht gespeichert. Leere Originalfelder bleiben leer, keine Nullzeit wird
hinzugefügt. Rohe Quelldaten werden nicht in JavaScript serialisiert.

Der vorhandene Konflikthinweis behält seine Bedeutung, beendet aber die Seite
nicht mehr vor den Originalzeilen. Die bestehenden generischen Links zu
`history.php`, `index.php` und `adjustments.php` bleiben erhalten; die betroffene
Person stellt den Korrekturantrag, danach gilt die bestehende Vier-Augen-Freigabe.
Vier kleine neue Hinweise sind in den vorhandenen nativen DE-/EN-/ZH-Dateien
definiert. Bereits deutsche Konflikt-/Linktexte und die vorhandenen festen
Berechnungsfehlermeldungen werden nicht als vollständige Lokalisierung umgebaut.

## Tatsächliche Prüfung und offene Grenzen

Ein grober synthetischer Quell-/Templatevertrag prüft erfundene gültige,
offene, ungültige und konfliktbehaftete Zeilen, rohe Sichtbarkeit und Escaping,
Befund vor Filter/Seite sowie unveränderte gültige Berechnungsblöcke. Zusätzlich
PHP-Syntax, extrahierte unveränderte JavaScript-Syntax und `git diff --check`.
Tatsächlich verwendet: PHP **8.5.12**, Prozesszeitzone **Europe/Berlin**; zwei
gültige/offene Vergleichszeilen sowie ein Zwölf-Zeilen-Kollektiv mit fünf
ungültigen Zeilen und einem Konflikt. Fallbackseiten zehn plus zwei Zeilen,
Benutzernamensuche vier gültige Zeilen bei weiterhin global gesperrten Summen.
Keine Verbindung zu einer Datenbank, kein App-/Header-/Security-Bootstrap,
keine Session-/Authentisierungsinjektion, kein LDAP, HTTP, Browser, Server,
Deployment oder echter Personen-/Buchungsbestand. Die Nachweise benennen
Quellhash und tatsächliche Laufzeit im privaten Paket-Handoff.

Bestehende Tests, Abhängigkeiten, externe native Assets und Framework-Routen
bleiben unverändert. Reale Integrations-/Browserabnahme und weitere #16-Flächen
(z. B. das Verhalten anderer Exporte) werden damit nicht für bestanden erklärt.
Keine automatische Bestandskorrektur oder vollständige Datenqualitätsfreigabe.
