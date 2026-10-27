# CH-29 / #35: Nachtschicht und Abwesenheit — sichere erste Phase

## Freigegebener Konfliktweg

Am 27.10.2026 wurde ausdrücklich freigegeben: tatsächliches Arbeitsende weiter
speichern, eine Überschneidung mit Urlaub/Krankheit sichtbar markieren und
betroffene Summen bis zur manuellen Klärung **nicht als abschließend** ausweisen.
Nichts automatisch löschen, kürzen oder als null bewerten. Die bisherigen
Vier-Augen-Korrekturen entscheiden weiter über Änderungen der Berichtsprojektion.
Es wird keine neue Entgelt-/Urlaubsanspruchsregel eingeführt.

## Umsetzung

- Der gemeinsame Anwesenheitskern schließt tatsächliche Arbeit und eine
  bestätigte aktive Pause weiterhin in derselben bestehenden Transaktion.
  Bei einer Überschneidung des abgeschlossenen Intervalls mit ganztägiger
  Abwesenheit enthält die ursprüngliche Endbestätigung einen deutlichen Hinweis.
  Kein Überlappungsfehler rollt das tatsächliche Ende zurück.
- Konflikte werden schreibfrei aus den **aktuellen effektiven Buchungszeilen**
  abgeleitet, nicht aus unveränderlichen Originalintervallen allein. Eine
  freigegebene Korrektur/Stornierung kann den aktuellen Konflikt dadurch klären,
  ohne ursprüngliche Stempelzeiten oder Ereignisse zu überschreiben. Antrag und
  Ablehnung allein ändern den Konflikt nicht.
- Die Erkennung trennt Personen, respektiert halboffene Intervalle und den
  ganzen Berliner Kalendertag einer Gutschrift einschließlich 23/25-Stunden-
  Tagen. Tatsächliche Pausen bleiben unverändert. Die bestehende Zuordnung
  vollständiger Nachtschichten zum Startdatum wird nicht aufgeteilt.
- Eigenbericht und Dashboard zeigen betroffene Tages-/Wochen-/Monats-/Jahres-
  und Gesamtkennzahlen als **„Klärung offen“**. Unabhängige Zeiträume und
  Sollwerte bleiben unverändert. Rohdauer und tatsächliche Zeitpunkte sind keine
  abschließende Gutschriftenentscheidung und bleiben in den Details sichtbar.
- Die bestehende Buchungssteuerung aktualisiert diese Kennzeichnung auch nach
  AJAX-Buchungen sowie bei Fokus-/Statusabgleich mit einem anderen Tab.
  Native Statusantworten enthalten dafür getrennte aktuelle Zeitraumdiagnostik
  aus demselben eigenen Lesesnapshot; rohe API-/Erweiterungszeilen ändern sich
  nicht. Eine ursprüngliche oder wiederholte Endwarnung macht alte Zahlen
  sofort als **„Aktualisierung nötig“** kenntlich, bevor ein Statusabgleich
  fehlschlagen kann. Auch eine ausgebliebene/ungültige Endbestätigung oder ein
  wiederaufgenommener offener Ende-Vorgang kennzeichnet Zahlen so vorsorglich,
  ohne dessen Pending-/Retry-ID zu löschen. Diese generische Veraltungsanzeige
  kann zunächst alle vorhandenen Kennzahlen betreffen; sie behauptet keinen
  aktuellen Konflikt in unabhängigen Zeiträumen. Erst der aktuelle Snapshot
  kennzeichnet tatsächliche Konflikte präzise nach Zeitraum. Nach einer späteren
  Klärung werden keine alten Zahlen aus
  dem Browser wiederhergestellt. Eine bewusste Seitennavigation lädt neue
  Zahlen; davor können ungespeicherte Formulareingaben gesichert werden.
  Es gibt kein automatisches Neuladen und keinen Eingriffsweg in Pending-/
  Wiederholungs-/401-/403-Buchungszustände.
- Die Zehn-Rohzeilen-Paginierung bleibt bestehen. Seitendaten, vollständiger
  eigener Kontext und Anzahl stammen aus einem Lesesnapshot. Eine Gegenbuchung
  auf einer anderen Seite oder einem anderen Starttag geht nicht verloren.
- Nicht auf Konfliktdiagnostik umgestellte gemeinsame Summenpfade verweigern
  eine abschließende Berechnung. Der inaktive Framework-Gesamtbericht bleibt
  so gesperrt; seine Zeitraumabfrage verweigert nur einen Zeitraum, der einen
  der beiden beteiligten Berichtsstarttage umfasst. Die vorhandene strengere
  Verweigerung bei fehlerhaften Bestandsdaten bleibt erhalten.
- Der alte Führungsbericht besitzt mehrere getrennte Zahlen-/Diagrammpfade.
  In dieser ersten Phase hält eine klare Warnung den gesamten betroffenen
  autorisierten Berichtsumfang zurück, statt nur einzelne Zahlen scheinbar
  abschließend weiterzugeben. Eigene Rohdaten, Historie und Korrekturanträge
  bleiben zugänglich. Eine reichere personenbezogene Konfliktansicht ist nicht
  Bestandteil dieser Phase.
- CSV prüft genau die vollständigen eigenen Zeilen, die andernfalls exportiert
  würden, vor CSV-Headern/-Bytes. Bei Konflikten antwortet der Export mit **409**
  und Klärungshinweis, nicht mit scheinbar abschließenden Zahlen. Unbetroffene
  Exporte behalten ihren bisherigen Inhalt und ihre Sortierung.

## Wiederholung und Datenverträge

Der Hinweis wird nur bei der ursprünglichen Endbestätigung erzeugt und mit dem
unveränderlichen Ereignis gespeichert. Eine identische Wiederholung liefert
weiter die gespeicherte Antwort, auch nach einer späteren manuellen Klärung.
Dieser **historische Hinweis** ist kein erneuter Nachweis eines aktuellen
Konflikts. Die aktuelle Anzeige wird aus den aktuellen effektiven Zeilen
abgeleitet. Alte Ereignisantworten werden weder ergänzt noch umgeschrieben.

Keine neue Tabelle/Migration, kein neuer Anwesenheitsstatus, kein neues
Auth-/Rollen-/Teamrecht und keine Änderung der Writer-/Audit-/Retry-Lebenszyklen.
Die bestehenden nativen und Framework-API-Listen liefern weiterhin rohe
Quellzeilen; sie bescheinigen keine abschließende berechnete Gesamtsumme.
Fehlerhafte Bestandsdaten bleiben die separate CH-11-Datenqualitätsdiagnostik.

## Begrenzter Nachweis

`tests/overnight_absence.php` ist **ein grober synthetischer In-Memory-Ablauf**:
Vortag 22 Uhr am echten Start-Überlappungsschutz prüfen, erreichbare historische
Sitzung als Fixture einsetzen, mit tatsächlicher Serverzeit und bestätigter
Pause beenden, getrennte Konfliktdiagnostik und seitenübergreifende Tagessumme
prüfen, expliziten Antrag durch eine andere berechtigte Fixture-Person freigeben
und unveränderte Originalintervalle/Ereigniswiederholung nachweisen. Eine kleine
Zeitraummatrix umfasst Arbeitsstarttag, Abwesenheitstag, beide Tage und einen
unabhängigen Zeitraum; Personen-/Grenz-/Datenqualitätskontrollen gehören zu
demselben groben Ablauf. Keine feinmaschige neue Testsuite.

Laufzeit: PHP 8.5.12 / SQLite 3.46.1. PHP-Syntax und dieser grobe Ablauf lokal
geprüft. Ein zusätzlicher grober Lauf der tatsächlichen Buchungssteuerung in
einem synthetischen DOM mit ausschließlich lokalen Fetch-Antworten prüft die
sofortige Kennzeichnung bei ausgefallenem Statusabgleich, historische
Warnwiederholung, tabübergreifenden Fokusabgleich und unveränderte Eingaben ohne
Neuladen. Dies ist keine Browser-/HTTP-/Auth-Abnahme. Bestehende HTTP- und
Framework-Fixtures wurden ausschließlich statisch
auf absichtlich widersprechende Arbeits-/Abwesenheitsdaten geprüft; keine
vorhandenen Erwartungen wurden abgeschwächt oder Daten dafür umgeschrieben.

**Nicht geprüft:** historischer Start mit echter damaliger Serveruhr, tatsächliches
Ende genau um 06:00, HTTP/Auth, Browser/Layout/Mehrtab, reale Datenbank,
Framework-Laufzeit/Writer, Geräte, Produktion oder Deployment. Reguläre CI am
exakten veröffentlichten Commit und unabhängiges Quellreview sind separate
Gates. Dieses Paket ist eine sichere erste Phase und keine vollständige Abnahme
aller Oberflächen oder ein Nachweis produktiver Ausrollung; #35 nicht allein
wegen dieses Nachweises schließen.
