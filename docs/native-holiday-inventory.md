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

Der neue Leser führt ausschließlich eine parametrisierte SELECT-Abfrage für
das gewählte Jahr aus. Maximal 366 Zeilen werden angezeigt; eine 367. Zeile
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
