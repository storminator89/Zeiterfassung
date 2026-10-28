# #52/B — ausgewählter JSON-Auszug einer bestehenden Historienseite

Basis: Main `cb8fc5245e5dc980824fb3152224a499a049d3bd`, Tree
`2d99179d07ff4db2dd8f6932026b73cebb889ec0`. Die bestehende native Historienseite
und vorbereitete geschützte Laravel-Historie erhalten genau einen bestehenden-
Stil-Link: „Aktuelle Treffer dieser Filterseite als ausgewähltes JSON (max. 100)“.
Kein neues UI, keine Route, Rollenberechtigung, aktive Frameworkregistrierung,
Datenbankverbindung, Schreibfunktion, Journal-/Schemaänderung oder Serverdatei.

## Exakt derselbe bestehende Filter-/Sichtvertrag

`history.php?export=selected-json` liest einmal dieselbe bisherige Historienquery.
Laravel ruft ausschließlich die vorhandene `historyQuery()` im vorhandenen
Read-Snapshot mit frisch geladener Identität/Rolle auf. HTML-Aufbau, Chrome-Reads
und Sprachpräferenzänderung werden im Exportzweig nicht zusätzlich ausgeführt.
Native Anmeldeprüfung und geschützte Laravel-Middleware bleiben unverändert;
Authentication-/Authorization-Exceptions behalten ihre bestehenden Adapter.
Keine Identität oder Rolle wird aus Filter-/Exportwerten abgeleitet.

- Sichtbar bleiben eigene Auditzeilen, alle Zeilen für Admins oder Zeilen aktuell
  direkt zugeordneter Supervisor-Personen. Keine rekursiven/historischen Teams,
  neue Sicht auf gelöschte Konten oder über Benutzer-ID 0; die bisherige Admin-
  Sicht darauf bleibt erhalten. Der Personenfilter verengt nur diesen Umfang.
- Person, Bereich, Bereichs-Datensatz, Korrekturantrag und Änderungsdatum werden
  weiter gemeinsam mit AND angewendet. `request_id` meint weiterhin nur den
  protokollierten Antrag selbst, nicht automatisch verbundene Buchungen.
- Von-/Bis-Datum gelten für die protokollierte Änderung, nicht den Buchungstag:
  Berlin, beide lokalen Tage eingeschlossen mit exklusiver Folgetagsgrenze.
  Optionale/offene Datumsbereiche bleiben erlaubt. Es gibt keinen neuen
  fachlichen Maximalzeitraum und keine erfundene gesetzliche Aufbewahrungsfrist.
- `id < before`, absteigende IDs und `LIMIT 100` bleiben genau gleich. Der Link
  bewahrt **die aktuelle Seitenmarke** und alle bestehenden Filter, statt über
  das normale Filterformular versehentlich auf die neueste Seite zu springen.
  Kein Cursorloop, Gesamtbestandsexport oder Nachladen verbundener Zeilen.
- Der Export ist ein neuer autorisierter Read dieser Filterseite, kein über
  mehrere HTTP-Anfragen stabil eingefrorener Bildschirm-/Gesamtbestand. Neuere
  Änderungen, Rollen-/Teamänderungen oder Parallelität werden nicht als globaler
  Snapshotvertrag verschleiert. 100 Treffer bedeuten nur, dass ältere Treffer
  möglich sind, nicht deren bewiesene Existenz.

Ungültige vorhandene Filter oder ein unbekannter/nichtskalarer Exportmodus
geben eine feste JSON-400-Antwort ohne Auditabfrage, nie einen unbeschränkten
Fallback. Sonstige Read-/Format-/Kodierfehler ergeben eine feste JSON-500-Antwort
statt SQL-/Pfad-/Personendiagnose oder Rohdaten. Unveränderte HTML-Anfragen ohne
Exportmodus behalten ihre bisherige Query, Fehler, Karten und Paginierung.

## Auswahl, keine neue Rohdatenoffenlegung

Der definitions-only Helfer `lib/history_export.php` erhält ausschließlich die
bereits autorisierten maximal 100 Zeilen. Er hat keine PDO-, Dateisystem- oder
HTTP-Nebenwirkung. Dieselbe feste `history_presentation.php`-Feldliste liefert
ausgewählte Vorher-/Nachhertexte und lesbare Änderungen. Der bestehende Helfer
wird unverändert wiederverwendet, nicht durch einen zweiten Rohparser ersetzt.

- Pro Entität bleiben nur die bereits freigegebenen Buchungs-, Konto-, Feiertags-,
  Sitzungs-/Pausen-, Korrektur- und Arbeitszeitmodellfelder. Konto-Snapshots geben
  weiterhin nur ID, Benutzername, Tages-Soll und Anfangsguthaben frei.
- Verschachteltes `original_json` erlaubt nur die feste Buchungsauswahl;
  `minutes_json` nur sieben endliche Wochentagszahlen. Keine beliebige Rekursion,
  Feldentdeckung oder neue Benutzer-/Credential-/Token-/PIN-/Hashprojektion.
- Originale `before_json`/`after_json`, interne `cells`/`signature` und unbekannte
  Feldnamen/-werte werden nicht ausgegeben. Unbekannte Bereiche/Operationen
  bekommen feste Marker; unbekannte Snapshots bleiben nicht darstellbar.
- Bestehende Metadaten bleiben auf Audit-ID, Berlin-Zeit, bekannten Bereich,
  Bereichs-Datensatz-ID, Operation und bisherigen Akteur/Begründung begrenzt.
  Fehlerhafte ID-/Zeitmetadaten erhalten einen festen Marker bzw. null und einen
  Begrenzungshinweis, keine geratenen Beziehungen oder 1970-Ersatzdaten.
- Snapshots bleiben auf 65.536 Eingabebytes, Parsertiefe 8 und die feste Struktur
  beschränkt; skalare Texte auf 512 Unicodezeichen mit Hinweis. Auch Metadaten-
  Akteur/Begründung nutzen diese bestehende Textbegrenzung. Hinweise für gekürzte,
  ausgelassene oder nicht darstellbare Inhalte bleiben explizit enthalten.

Erlaubte Freitexte können sensible Angaben enthalten. Das ist eine strukturelle
Feldfreigabe unter dem bisherigen Sichtrecht, **keine semantische Geheimnis- oder
Personendatenerkennung und keine pauschale „secret-free“-Zusage**. Es werden keine
Akteur-/Begründungstexte als Ereignis-/Korrekturverknüpfung geparst. Ein lokal
abgespeicherter Auszug ist nachträglich nicht technisch widerrufbar; keine neue
Aufbewahrungs-, Verschlüsselungs-, Revisionssicherheits- oder Rechtszusage.

## Fester Downloadtransport, keine Persistenz

Der vollständige ausgewählte JSONbody wird mit `JSON_THROW_ON_ERROR` materialisiert,
bevor die Transportadapter seine Header oder Bytes ausgeben. Bei einem Fehler
kein Teildownload, kein Rohdatenfallback. Der native Zweig endet vor jedem HTML;
Laravel verwendet dieselbe fertige Body-/Headerstruktur. Keine persistierte
Serverdatei, temporäre Datenkopie, externer Upload oder zusätzliche Datenquelle.

Erfolg: `application/json; charset=UTF-8`, `Cache-Control: no-store`,
`X-Content-Type-Options: nosniff`, fester Attachmentname
`chrono-history-selected-page.json`. Keine nutzersteuerbare Header-/Dateinamen-
Komponente. Fehler haben denselben festen Content-Type/Cache-/nosniff-Plan, aber
keinen Attachment-Erfolg. Das sind Antwortheader dieses Auszugs, keine globale
Sicherheits-/Host-/CSP-/Sessionänderung. Quelle/Format heißt
`chrono-selected-history-page-v1` und markiert `complete_audit=false` sowie
`related_records_included=false` ausdrücklich.

## Korrelation bleibt begrenzt und #52 offen

Vorhandene ausgewählte IDs bleiben erhalten: Buchungs-Audit-ID/Bereichs-Datensatz,
Sitzung mit `record_id`, Pause mit `session_id`, Antrag mit eigener ID und
Buchungs-/Originalbuchungs-ID, Status/Stornoangabe. DELETE-Snapshots bleiben ohne
Lookup in einer noch vorhandenen Buchung exportierbar. Fehlende/außerhalb dieser
Seite oder Filter liegende Gegenstücke werden nicht geraten oder nachgeladen.

`attendance_events` ist kein zugelassener History-Auditbereich. Seine
Ereignis-/Sitzungsfelder, gespeicherte Antwort und Payloadhash werden nicht neu
gelesen oder ausgegeben. Vollständige Ereignis→Sitzung→Buchung→Antrag-Korrelation,
forensischer Rohjournalnachweis und ein stabiler Gesamtbestandsexport benötigen
einen getrennt freigegebenen Vertrag. Dieser Auszug schließt #52 nicht vollständig.

## Nachweisgrenze

Ein grober erfundener Serializer-/Filterlink-/Headerplan-Nachweis betrachtet
Auswahl/Redaktion, feste Marker, Kürzung, Cursor/Filtererhalt und fertige sichere
Erfolgs-/Fehlerpläne sowie die vorhandene Query-Scopeform. Hinzu kommen notwendige
PHP-/Blade-Syntax und vollständige Quellerhaltung. Der grobe Nachweis und
Syntaxprüfung bestanden am 28.10.2026 unter PHP 8.5.12. Keine tatsächliche Historie,
Datei mit Nutzerdaten, Audit-/App-/Guard-/Session-/HTTP-/Browserausführung, echter
Download, Server, Remote-Schreibzugriff, neue CI oder Deployment. Autorisierung,
SAPI-Header und Download im Browser werden hier nicht als abgenommen behauptet.
