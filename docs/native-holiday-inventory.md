# Native Feiertagsbestandsanzeige — Teilumfang #41

Ausgangsstand: Remote-Main `c9483797ff8470998fd018042d2e988e3b1f015d`,
Baum `9acde7d4819aee8428e7aa286780964f522537f9`. Für die reine lokale Integration auf Main
`97b64e10e4fbf939c52381f4c713472df124b1d6`, Baum
`f3240ddb8176e26aae1453fcf1cc94aaea3d8556`, erneut geprüft.
Keine Aussage über einen Runtime-Cutover oder ein Deployment.

## Direkter Nutzen und feste Grenze

Vor einer Abwesenheitsgutschrift führt ein knapper Link aus dem vorhandenen
Formular zur geöffneten Bestandsanzeige im bestehenden Dashboard. Der Link
öffnet ausdrücklich einen neuen Tab mit `noopener noreferrer`, damit bereits
eingegebene Abwesenheitsdaten beim Nachsehen nicht verlassen werden. Sie bleibt
sonst eine aufklappbare Zeile statt einer dauerhaften großen Warnbox. Über die
Jahreswahl kann die Person auch Folgejahre prüfen und die tatsächlich
gespeicherten Datum-/Namenszeilen mit der Verwaltung für den Praxisstandort
abgleichen. Die Kalenderwochenauswahl bleibt erhalten; beide Auswahlen ändern
keine Buchung und bestätigen keine Daten.

Ein leerer Bestand heißt nicht „keine Feiertage“, und eine oder viele Zeilen
belegen keine Vollständigkeit. Region und Herkunft einzelner Bestandszeilen
sind unbekannt. Die bisherige automatische Quelle ist fest Baden-Württemberg;
das wird nicht als Herkunftsnachweis für jede Zeile ausgegeben. Auch ein
plausibel gefüllter Jahrgang bleibt ausdrücklich ungeprüft. Es gibt keinen
Prüfhaken, keine neue Genehmigung und keinen Ersatz für betriebliche Prüfung.

Der Leser führt eine parametrisierte SELECT-Abfrage für das gewählte Jahr
sowie die unten beschriebene begrenzte Jahrgangsübersicht aus. Maximal 366 Zeilen werden angezeigt; eine 367. Zeile
oder ungültige Datumswerte führen zu einem sichtbaren Datenhinweis. Werte
werden unverändert und HTML-escaped dargestellt. Fehler der Datenbank werden
nicht in einen leeren/gesunden Bestand umgedeutet. Ungültige Jahreswerte zeigen
einen Fehlerhinweis mit explizitem Rückfall auf das laufende Jahr.

## Nicht geliefert / unverändert

- Keine Änderung an Importer, `functions.php`, Schema, Kalenderprojektion,
  Soll-/Gutschriftberechnung, Buchungs-, Auth-, CSRF- oder Freigabepfaden.
- Kein Nachladen des ausgewählten Jahres oder neue externe Anfrage. Der schon
  vorhandene native Bootstrap kann weiterhin das laufende Jahr importieren;
  diese bekannte Nebenwirkung wird weder neu eingeführt noch behoben.
- Keine regionale Konfiguration, Herkunfts-/Vollständigkeitsmetadaten,
  atomare Teilbestandsreparatur, Quellenprüfung oder Ausfallwiederholung.
- Keine echte Bestandsdaten-, Login-, HTTP-, Browser-, Layout-, CDN- oder
  Produktivabnahme. #41 bleibt offen; eine reine Leseanzeige repariert keinen
  unvollständigen Feiertagskalender und verhindert keine fehlerhafte Gutschrift.


## Gespeicherte Jahrgänge finden — weiterer Teilumfang #41

Quelle Main `2282747054cc7a60181e56eae9ffe6f3dac5df3e`, Baum
`8027e610aba2d5087e253a28267feaabfdc13917`. Ein zusätzlicher aufklappbarer
Abschnitt im bestehenden Inventurpanel listet höchstens die 20 neuesten
erkannten gespeicherten Jahrespräfixe mit ihrer tatsächlichen Zeilenzahl. Ein
fester Dashboardlink wählt den Jahrgang bei unveränderter Kalenderwoche; auch
nicht aufgelistete ältere Jahre bleiben über die bisherige Jahreswahl prüfbar.
Mehr als 20 erkannte Jahrgänge werden ausdrücklich als begrenzte Anzeige
gekennzeichnet. Leerer Bestand ist weder Entwarnung noch Feiertagsfreiheit.

Die zusätzliche reine SELECT-Abfrage gruppiert Textdaten mit genau vier
ASCII-Ziffern und anschließendem Bindestrich (0001 bis 9999). Auch ungültige
Termine innerhalb eines solchen Präfixes zählen als **gespeicherte Zeilen**;
andere fehlerhafte Jahrespräfixe und Nichttextwerte werden nicht aufgelistet.
Die Darstellung sagt das ausdrücklich. Keine gültige-Datums-, Herkunfts-,
Regions- oder Vollständigkeitszusage, keine neue Prüfmarkierung und kein Ersatz
für die Prüfung des tatsächlichen ausgewählten Bestands. Ein Lesefehler wird
nicht als leerer Überblick ausgegeben. Die bestehende Jahresanzeige und alle
bisherigen Diagnosewerte bleiben unverändert; nur zwei DTO-Felder kommen hinzu.

Laravel verwendet denselben bestehenden Inventur-Reader mit demselben bereits
autorisierten Snapshot. Ältere optionale Reader ohne die zwei neuen Felder
zeigen keine Übersicht, aber behalten ihre ursprüngliche Jahresanzeige. Die
native Bootstrap-Lazyimport-Nebenwirkung bleibt unverändert; diese Ergänzung
ruft keine externe Quelle auf und schreibt keine Daten. Keine neuen Rechte,
Routen, Schema-, Writer-, Auth-, CSRF-, Runtime-, Dependency- oder Hoständerungen.

Grober PHP8.5.12/SQLite3.46.1/Laravel13.38.0-Nachweis: tatsächlicher Helfer mit
erfundener query-only Memoryquelle, gültige/ungültige Jahrespräfixe und Termine,
NULL/BLOB, Randjahre, Sortierung/Zählung, leere und über20-Jahrgänge, weiterhin
prüfbares älteres Jahr, unveränderte sechs ursprüngliche Jahresergebnisse,
wiederholte unveränderte Reads. Tatsächliche Native- und kompilierte Bladefragmente
in DE/EN/ZH: begrenzte Links erhalten die Kalenderwoche, leere/alte optionale DTOs,
Native-/Blade-DE-Inhalt und URLs gleich (Intertag-Whitespace normalisiert).
Notwendige Syntax-/Sprachdefinitionsprüfung; Sprachdateien geben keine Bytes aus.

Kein App-/Runtimezulassungs-, Login-/Session-/CSRF-/HTTP-/Browser-/Layout-,
Produktiv-, Last- oder Konkurrenznachweis. Keine realen Daten/Konten/Credentials/
Schlüssel, Dateidatenbank, Aktivierung, neue Testsuite oder manueller CI-Start.
#41 bleibt teilweise/offen; die gespeicherten Jahrgänge sind keine abgenommene
regionale Kalenderdatenbank und reparieren keine fehlenden Daten.

## Nonassignable year prefixes — partial #41, no repair

Source basis: actual Main `d213531db6e93a07a2d884050a370edbd6899ed6`, tree
`8cfef884fd044b6d0f661aa6c710361ee243ec60`. The stored-year list omitted rows
with no recognized text `YYYY-` prefix from 0001 through 9999. A new aggregate
`unassigned_year_rows` counts exactly those omitted rows, independently of the
selected year and the existing 20-year list limit. If positive, a localized
notice in the same inventory panel asks Administration to inspect the originals.
Only a count is shown, not the malformed values or a guessed year. NULL/BLOB and
0000 prefixes are included; an invalid date inside a recognized year remains in
that year's existing count and date diagnostics, not this separate count.

The fixed parameter-free COUNT is SELECT-only. No original, year assignment,
repair, source fetch, calculation, writer, schema, permission, route or Runtime
changes. Both previous queries and every prior DTO value remain unchanged.
Native still has its historical bootstrap importer and separate non-atomic
reads; this does not certify a coherent snapshot there. Laravel still uses its
existing authenticated snapshot and omits the notice for older optional DTOs.
Zero is not a clean-data or calendar-completeness claim. #41 remains open.

Coarse anonymous PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0 Memory journey:
actual helper, original table constraints plus a deliberately permissive
NULL-type fixture, baseline omission, recognized/nonrecognized prefixes,
0001/9999, malformed selected-year dates, BLOB/NULL, more than 20 years,
selected-year parity, query_only/repeated reads, no source/schema/counter change,
and read-error propagation. Actual Native and compiled Blade fragments in
DE/EN/ZH show exact count-only notices, absent for zero or older optional DTOs;
existing year links and German Native/Blade content are unchanged apart from the
notice. Necessary PHP/compiled-Blade lint and diffcheck pass. No new repository
testsuite or manual CI dispatch. No real data/accounts/credentials/keys/file DB,
App/bootstrap/auth/CSRF/HTTP/browser/layout/production/concurrency/performance,
positive Runtime admission or deployment acceptance. Browser acceptance remains
with the user.
