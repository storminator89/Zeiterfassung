# #55: vorhandene Prüfhinweise in der eigenen nativen Liste finden

Quellkandidat auf Main `f1e3f7858b096dcbef2d8703c23154736e6d5240`, Baum
`ddaf0a8b10789a080a2d0eb7f7ad04031cbe2db0`. Enger praktischer Lese-Slice,
kein vollständiger Bestands-/Migrationscheck und kein Ersatz des vorhandenen
privaten CLI-Werkzeugs. #55 bleibt offen.

## Kleiner Ansichtswechsel, keine neue Diagnoseautorität

In der bestehenden eigenen Arbeitszeitliste schaltet eine kleine Auswahl
zwischen **Alle eigenen Einträge** und **Vorhandene Prüfhinweise**. Der neue
Helfer verwendet ausschließlich die schon vorhandenen
`report_record_calculation`-/`report_conflict_lookup`-Ergebnisse: ungültige
Zeitwerte und aktuelle Arbeitszeit-/ganztägige-Abwesenheitskonflikte. Er definiert
keine eigene Zeit-, Konflikt- oder Reparaturregel. Unmarkierte Einträge sind
keine Freigabe und beweisen weder fehlerfreien Bestand noch rekonstruierbare
Altzeiten. Offsetfreie mehrdeutige Herbstzeiten, Work/Work-Überschneidungen,
fehlende Tage, Rechts-/Pausenregeln, Quelle/Vollständigkeit und alle weiteren
Prüfungen sind nicht Gegenstand dieses Filters. Auch eine leere Auswahl bleibt
sichtbar ohne Sauberkeits- oder Vollständigkeitsbehauptung.

Der vollständige eigene Kontext und seine Konflikte werden im vorhandenen
Lesesnapshot bestimmt, **vor** der neuen Auswahl und deren 10er-Paginierung.
Beide Enden eines bestehenden Konflikts können dadurch auf ihren jeweiligen
Seiten gefunden werden, auch wenn sie auf verschiedenen Starttagen liegen.
Der neue Modus nutzt dieselbe SQL-Instant-/ID-Reihenfolge wie die bisherige
Liste; der Standardmodus behält seine alte Seitenabfrage und Antwortbytes
außer dem neuen Auswahlhinweis. Ein zu großer Prüfseitenwert wird auf die letzte
vorhandene Prüfseite begrenzt. `review=1` aktiviert diesen Modus; ein vorhandener
anderer oder arrayförmiger `review`-Wert ergibt festen400-Text. Personenparameter
wählen niemanden aus: die bestehende Session-Ownerbindung und beide
`WHERE user_id = ?`-Prädikate bleiben erhalten.

Tageswerte und Konfliktkennzeichnungen beziehen sich weiterhin auf den
**vollständigen** eigenen Tagesbestand, nicht nur auf die gefilterten oder
sichtbaren Zeilen. Die bestehenden Teilansichtslabels sowie unveränderte
Korrektur-/Stornolinks bleiben erhalten. Originaldaten, Vier-Augen-Freigabe,
Fachformeln und Buchungspfade bleiben unverändert. Voll- und Zeitraum-CSV
werden durch die Listenansicht nicht gefiltert oder fachlich verändert.

## Bestehender Client und feste Public-Kopie

Der Ansichtswechsel setzt die Seite auf1. Gewünschter und bestätigter Zustand
bleiben getrennt: Auch eine Anwesenheitsaktualisierung oder alte Paginierung
während des laufenden Wechsels behält den gewünschten Modus und Seite1.
Nach dessen Bestätigung bleibt der Modus bei weiterer Navigation erhalten. Nach
fehlgeschlagener Aktualisierung bleiben die bisherigen Zeilen und Auswahl
sichtbar, mit dem vorhandenen Fehlerstatus; spätere Antworten eines älteren
Requests dürfen eine neuere Auswahl nicht überschreiben. Das bestehende
Auf-/Zuklappen, Pending-/Buchungsclient und CSV-Button werden nicht ersetzt.

Die bereits vereinbarte Source-/Public-Parität von `assets/js/main.js` wird im
selben Paket gewahrt: feste öffentliche Kopie bytegleich, ausschließlich ihr
bereits vorhandener exakter Hash in `FreshWebConfiguration::assets()` und die
zugehörige Tabellenzeile aktualisiert. Alle fünf anderen Assetpins sowie der
Validator bleiben unverändert. Keine neue Assetroute, dynamische Kopie,
Zulassungslockerung, Runtimefreigabe oder Host-/Securitykonfiguration. Ohne
nativen Prüfhinweisschalter bleibt der bisherige Standardrequest unverändert;
ein neuer Laravel-Filter oder dessen Abnahme wird nicht behauptet.

## Grober Quellnachweis und Grenzen

PHP8.5.12: ein isolierter Journey nutzt die tatsächliche native Listenregion
nach ausgelassenem unverändertem Bootstrap, erfundene Own-/Fremdzeilen und eine
anonyme In-Memory-DB. Standardseiten bleiben bis auf die neue Auswahl bytegleich.
14 alte Hinweise werden vor Paging über zwei Seiten gefunden; große Seitenwerte,
nicht zuordenbare Zeit, beide Nacht-Konfliktpartner, vollständiger gesunder
Tageskontext, nicht markierte valide DST-/NULL-offene Zeilen, Fremdausschluss,
400 bei ungültigem Modus und Wiederholung sind geprüft. `query_only`, exakte
Quellzeilen/Änderungszähler und geschlossene Transaktionen belegen den engen
nichtschreibenden Readerumfang, keine echte Berechtigungsabnahme.

Node24.22.1 führt den tatsächlichen gemeinsamen Client in einer erfundenen
DOM-/Fetch-Lebensfolge aus: Standard-URL, Filterwechsel, erhaltene Navigation,
serverseitig begrenzte Seite, unterbrochener Wechsel, Fokus, Fehlerrollback und Race erhalten ihre erwarteten
Zustände. Die bestehende reine statische Assetprüfung akzeptiert passende
alte/neue Kopie+Pins und verweigert beide gemischten Varianten. Keine neue
Repositorytestsuite, CI- oder Laufzeitkonfiguration.

Nicht geprüft: reale App-/Login-/Session-/CSRF-/HTTP-/Browser-/Layout-/Download-,
Mehrworker-, Performance- oder Produktivabnahme, echte Daten/Konten/Schlüssel.
Der vorhandene native Bootstrap kann weiterhin seine bisherigen Nebenwirkungen
haben; dieser Slice führt keinen Import oder Writer ein und attestiert keine
global schreibfreie Seite. Kein vollständiger Diagnostik-, Reparatur- oder
Migrationsabschluss, kein neuer Dienst und kein Deployment.
