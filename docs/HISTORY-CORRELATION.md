# #52/C — begrenzte strukturelle Historienbezüge

Die folgenden Abschnitte dokumentieren den historischen Exportvertrag. Die
additive native HTML-Ansicht auf Main `f7b4dbf0` ist am Dokumentende abgegrenzt;
sie ändert weder Resolver, JSON-Export noch Rollen-/Teamscope.

Basis: Main `8139842bab8fc64f93313e7b1848a16b96b496c2`, Tree
`ea1ac5ed0d5f31b16eda72436a0506165538144c`.
[#52](https://github.com/storminator89/Zeiterfassung/issues/52) verlangt
korrelierbare Ereignis-/Sitzungs-/Buchungs-/Korrekturdaten im bisherigen Scope.
Dieses Paket ergänzt einen **ausdrücklichen** zweiten GET-Auszug
`history.php?export=correlated-json`, keinen unbeschränkten Gesamtbestand.
Die vorhandene Auswahl `selected-json`/`chrono-selected-history-page-v1`, ihre
Feldprojektion und ihre bisherigen Fehlermeldungen bleiben unverändert.

## Ankerfilter bleiben Ankerfilter

Das bestehende 100er-Keyset `id < before`, absteigende Reihenfolge sowie alle
AND-Filter für Person, Bereich, Datensatz, Antrag und Berliner Änderungszeitpunkt
werden unverändert durch `chrono_history_query` bzw. `HistoryQuery` angewendet.
Der neue Link bewahrt den aktuellen Cursor und alle Filter. Normales HTML und
der bestehende v1-Auszug laden keine zusätzlichen Beziehungen.

Nur im ausdrücklich gewählten neuen Modus werden zu dieser Ankerseite begrenzte
Referenzen ergänzt. Diese können **außerhalb des Anker-Datums-/Bereichs-/Antrags-
filters** liegen; sie sind sichtbar als separate Bezüge gekennzeichnet und nicht
als zusätzliche passende Auditzeilen ausgegeben. Insbesondere bleibt
`request_id` die bisherige Auswahl von Auditzeilen des Antrags selbst. Kein
Cursorloop oder versteckter Gesamtexport, keine neue HTML-Tabelle oder Route.

## Dieselben aktuellen Rechte, ein Read-Snapshot

Die nativen und Laravel-Adapter verwenden ihre bereits vorhandene Verbindung.
Der neue native Modus besitzt genau eine DEFERRED-Lesetransaktion, verweigert das
Übernehmen einer fremden Transaktion und lädt darin die aktuelle Rolle des aus
der bestehenden Sitzung stammenden Akteurs erneut. Anker und Bezüge werden in
**dieser** Transaktion gelesen. Ein verschwundener/unbekannter Akteur erhält eine
feste 401-Ausgabe, keine Rolle aus einem Request. Kein neuer Anmelde-, Credential-
oder Sitzungsvertrag und keine behauptete umfassende Neuauthentifizierung.

Laravel ergänzt nur eine neue `ChronoReads::historyCorrelations`-Methode. Sie
benutzt die bereits vorhandene Snapshot-Hülle mit frisch nachgeladener Identität,
Rolle und Teamzuordnung; alle bisherigen Readmethoden bleiben unverändert.
Authentifizierungs-/Autorisierungsfehler behalten die bisherigen Adapter.

Jeder Lookup erhält zusätzlich den bisherigen Scope: eigene Person ODER Admin
ODER aktuell direkt zugeordnete Supervisor-Person. Keine rekursiven/historischen
Teams, Abteilungsrechte, neuen Rollen oder globalen Admin-Bypässe. Jede Kante
muss dieselbe besitzende Person haben; eine ID allein ist kein Freibrief zum
Nachladen eines fremden Datensatzes. Fehlende/ungültige/fremde Beweise werden
nicht durch personenbezogene Fehler oder einen Existenzhinweis unterschieden.
Gelöschte Konten bleiben damit wie bisher nur im aktuellen Adminumfang sichtbar.

## Feste Felder statt Rohdaten

Nur diese zusätzlichen Beziehungsspalten sind freigegeben:

- Sitzungen: Sitzungs-ID und Buchungs-ID.
- Ereignisse: interne Ereigniszeilen-ID, Ereignis-ID, Sitzungs-ID, fester Typ
  (`start`, `pause_start`, `pause_end`, `end`) und Server-Empfangszeit.
- Korrekturanträge: Antrags-ID, nullable Buchungs-ID, fester Status
  (`pending`, `approved`, `rejected`) und Stornoflag.
- Zu Ankern: Audit-ID, feste Auflösungsmarkierung, Referenz-IDs und Hinweis,
  ob eine aktuelle gespeicherte Antragsbeziehung ergänzend verwendet wurde.

Neue JSON-Beziehungs-IDs sind kanonische Dezimalstrings, damit große gültige
SQLite-IDs nicht schon beim JavaScript-JSON-Einlesen gerundet werden. Das
unveränderte v1-Ankerformat wird dadurch nicht heimlich umtypisiert.

Keine Geräte-/Quellen-/Grant-/Credential-/PIN-/Hash-/Antwortfelder und keine
zusätzlichen Begründungen, Notizen oder Namen aus verknüpften Tabellen. Auch
interne Owner-/Seed-/Prüftabellen werden nicht serialisiert. Der neue Serializer
baut ausschließlich die festen bekannten Schlüssel neu auf; kein Durchreichen
beliebiger Helferarrays. Die vorhandene ausgewählte Ankerdarstellung verwendet
weiterhin unverändert `history_presentation.php` mit seinen Feld-/Größen-/Tiefen-
/Kürzungsgrenzen. Bereits erlaubte Anker-Freitexte können sensible Angaben
enthalten; dies ist keine allgemeine semantische Geheimniserkennung.

## Explizite Beziehungen, keine Ursachenrekonstruktion

`attendance_events.session_id` plus `work_sessions.record_id` verbinden die
Ereignisse mit einer Sitzung/Buchungs-ID. `time_adjustments.record_id` verbindet
einen Antrag mit einer Buchung. Eigentümer werden auf jeder Stufe geprüft.
`audit_log` hat aber **keinen** Ereignis-/Transaktions-Fremdschlüssel: ein Ereignis
derselben Sitzung ist nicht allein deshalb der bewiesene Verursacher einer
konkreten Auditzeile. Akteur-/Begründungstexte werden niemals dafür geparst.
Zeitnähe, Namen, gleicher Saldo oder heutiger Anwesenheitsstatus sind kein Ersatz.

Bei Storno wird die aktuelle `zeiterfassung`-Zeile gelöscht, während Sitzungs- und
Antragsbeziehungen sowie DELETE-Audits erhalten bleiben. Deshalb wird gerade
**kein Live-Buchungs-JOIN** zur Voraussetzung gemacht. Wo ein aktueller Antrag
noch NULL enthält, wird keine Buchung erfunden. Hat er inzwischen eine gespeicherte
Beziehung, darf diese nur als CURRENT-Zusatz erscheinen, nicht als angebliche
Tatsache zum früheren Auditzeitpunkt.

Ein eigener Antrag kann im beschädigten Bestand dennoch auf eine fremde
Buchungs-ID zeigen. Deshalb muss eine solche Buchungsbeziehung durch eine
passende eigene erhaltene Sitzung oder einen passenden eigenen Buchungs-Audit-
Nachweis belegt sein. Dafür ist genau ein weiterer begrenzter **Metadatenlookup**
zugelassen: feste DISTINCT Owner-/Buchungs-ID-Paare aus sichtbaren
`zeiterfassung`-Auditzeilen. Keine zusätzlichen Snapshotpayloads, Live-Buchung,
fremden Existenzdaten oder Reparaturen. Eigentümer-/Beziehungsmischung, verwaiste
oder widersprüchliche IDs, fehlende Alt-Sitzungen und unbrauchbare Snapshots
bleiben ohne geratenen Ersatz. `linked` bedeutet nur mindestens eine
verifizierte Beziehung, nicht eine vollständige Viererkette; unbrauchbare
Teilzeilen können fehlen, auch wenn andere Bezüge desselben Ankers gültig sind.
Ohne belegbaren Bezug bleibt der Anker `unresolved`; nicht passende Bereiche
sind `not_applicable`. Fehlende Bezüge bedeuten nie „es gab kein Ereignis“.

## Feste Grenzen und ehrliche Lücken

Die Ankerseite bleibt höchstens 100 Zeilen. Jede ausgegebene Sammlung von
Sitzungen, Ereignissen und Anträgen hat ebenfalls höchstens 100 Einträge.
Begrenzte Metadatenbatches lesen maximal LIMIT 101 zum Erkennen von Abschneidung;
auch zusammengeführte Teilmengen werden dedupliziert/begrenzt. Ein auf 100
begrenzter Besitznachweis kann weitere Beziehungen ungeklärt lassen; sein
`truncated.references`-Flag bleibt sichtbar. Ankerreferenzen verweisen nur auf
tatsächlich ausgegebene Sammlungsobjekte. Kein Nachladen pro Anker, endloser
Seitenlauf oder stilles Umdeuten gekappter Ergebnisse in Nichtvorhandensein.

Das neue Format heißt `chrono-correlated-history-page-v1`. Es hält
`complete_audit=false`, setzt `complete_correlation=false` und erklärt die
Bezüge als getrennt enthaltene Referenzen. Es ist weder ein vollständiger
forensischer Nachweis noch eine rechtliche/Revisions-/Aufbewahrungszusage.
Bisherige BASELINE-Zeilen rekonstruieren keine Vorgeschichte; fehlende Altlinks
werden durch diesen Read nicht repariert. Der neue #57-Quittungspfad ist eine
getrennte Geräte-/Grant-Autorität und wird weder aufgerufen noch aufgeweicht.

Outputgrenzen sind keine Garantie einer begrenzten SQLite-Scanarbeit: ohne
neue Indizes können große Bestände teuer bleiben. Kein Performance-/Lastnachweis
oder neuer Index, keine PRAGMA-/Timeout-/Verbindungs-/Schema-/Auditmigration.
Der vollständige ausgewählte JSONbody entsteht vor allen Downloadheadern;
Fehler ergeben feste JSONtexte, nie einen Rohdatenfallback. Der neue feste
Attachmentname ist `chrono-history-correlated-page.json`; Content-Type,
no-store und nosniff entsprechen dem bisherigen Auszug. Keine Serverdatei,
externen Uploads, persistente Datenkopie oder echte Exportausführung im Paket.

## Nachweis und Abnahmegrenze

Ein grober ausschließlich erfundener Memory-Snapshot-Nachweis betrachtet
Owner/Admin/aktuelles Direktteam, Storno/gelöschte Projektion, fremde Kanten,
NULL-/Altbeziehungen, Kappung, festen JSONumfang sowie unveränderte Ankerfilter
und v1-Ausgabe. Dazu PHP-/kompilierte Blade-Syntax und vollständige Quellerhaltung.
Der grobe Nachweis bestand am 28.10.2026 unter PHP 8.5.12; keine echte
Historie, DB, Datei mit Nutzerdaten, Accounts, Credentials, App-/Auth-
/Session-/HTTP-/Browser-/Downloadausführung, neue CI, Deployment oder Remote-
Schreibzugriff. Die genehmigte technische Projektion ist keine neue Rolle oder
Aktivierung des vorbereiteten Laravel-Pfads. Vollständiges #52, echte Rollen-/UI-
/Export-/Stornointegration und Performance bleiben ausdrücklich offen.


## Additive native HTML-Ansicht auf Main f7b4dbf0

Enger lokaler Quellstand auf Basis
`f7b4dbf0196d65ee01de84818213806ec01904dd` (nach #239/#240), ohne produktive Abnahme oder Release.
Die bereits vorhandene Laravel-Bezugsansicht wird nicht neu implementiert oder
aktiviert. Die native Seite erhält dieselbe begrenzte praktische Lesemöglichkeit:
**Bezüge anzeigen** setzt explizit `relations=1`. Ohne Opt-in werden weiter nur
die bisherigen Anker gelesen, keine zusätzlichen Bezüge. Nur der exakte String
`1` aktiviert den Modus; andere vorhandene Werte ergeben eine sichtbare 400-
Filterverweigerung. Bestehende Downloadmodi werden vorher unverändert behandelt.

Der bisher inline definierte native Exportreader wird definitionsgleich nach
`lib/native_history_relations.php` ausgelagert. Export und HTML verwenden genau
seine bisherige DEFERRED-Lesesnapshot-Hülle: frische Rolle aus dem aktuellen
Konto, dann Anker, danach der unveränderte owner-geprüfte Korrelationsresolver;
Commit bzw. Rollback vor der Darstellung. Eine bereits fremd geöffnete Transaktion
wird nicht übernommen. Die Sitzung liefert nur die vorhandene Akteurs-ID,
keinen neuen Scope. Diese Abfrage ersetzt nicht die bestehende Authentifizierung.

Vor der neuen festen Fragmentansicht durchlaufen die Bezüge den vorhandenen
öffentlichen Exportserializer. Die Ansicht erhält dadurch nur die oben genannten
validierten Felder und ergänzt feste Typ-/Zeitlabels. Keine zusätzlichen Namen,
Notizen, Begründungen, Quellen, Antwortpayloads oder internen Ownerdaten. Anker
bleiben im bisherigen Feldvergleich; referenzierte Tabellen stehen kompakt in
aufklappbaren Abschnitten. Ungeklärte/gekappte Beziehungen und heutige Antrags-
zuordnungen sind beschriftet, niemals als vollständige Ursache dargestellt.
Filter, älterer Cursor, Neueste-Link und Umschalter bewahren den Darstellungsmodus;
**Filter zurücksetzen** kehrt bewusst zur bisherigen Standardansicht zurück.

Fehlgeschlagene Opt-in-Reads liefern vor der Seitenausgabe einen festen Text,
no-store/nosniff und 503 bzw. die bestehende 401/403-Verweigerung. Kein Fallback
auf alte/gecachte Bezüge, keine Ausnahme-/SQLdetails und kein Teilrendering.
Neue Metadaten-, Index-, Schema-, Schreib-, Geräte- oder Rollenverträge entstehen
nicht. Der gemeinsame Resolver und die JSON-Projektionsfunktionen bleiben exakt.

### Grober lokaler Nachweis

PHP **8.5.12**: ein grober Durchlauf rendert den unverändert kopierten nativen
Seitenquelltext gegen eine anonym erfundene, `query_only` gesetzte In-Memory-DB.
Nur Config und Seitenchrome sind sichere Teststubs, keine echte Appinitialisierung.
Er prüft Standardansicht ohne Beziehungsqueries, eigene/stornierte/ungeklärte
Bezüge trotz veralteter gecachter Adminrolle, aktuelles Direktteam, Teamwechsel,
Adminscope, Escaping und fehlende private Payloads. SQLite-Lesetraces bestätigen
Rolle/Anker/Bezüge im selben Snapshot; ein erzwungener Beziehungsfehler rollt ohne
Mutation zurück. Die HTML-Daten entsprechen der vorhandenen öffentlichen JSON-
Projektion. PHP-Syntax und `git diff --check` sind sauber.

Keine native Bootstrap-/Login-/Session-Neuabnahme, echte HTTP-Header-/Browser-
/Layout-/Konkurrenzprüfung, reale Historie, Betriebsdatenbank, neue GitHub-CI,
Remoteänderung oder Veröffentlichung. Vollständige #52-Abnahme und Performance
bleiben offen; ein lokaler Quellnachweis ist keine Produktivfreigabe.
