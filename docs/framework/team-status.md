# CH-54/A: datensparsamer Team-Stempelstatus in der vorhandenen Supervisoransicht

Basis: Main `c7169dedb5537e71641564e3fd2489e6c04787a9`, Tree
`d546dbcfcace017836cc524fa6facc60c1fd3934`, 27.10.2026.
Dieses Paket ergänzt ausschließlich die vorhandene geschützte Supervisoransicht.
Kein neuer öffentlicher Endpunkt, keine neue Rollen-/Standortpolitik und keine
Schreib-, Integrations-, Kalenderfeed- oder Gerätefunktion.

## Genau sieben Dateien

`LegacyChronoReads.php` ergänzt das Ergebnis des bestehenden `teamReport()` um
`team_status`. Die bisherige Rolleprüfung, historische SQLabfrage, vollständigen
Projektionsinputs und `supervisor_report_projection()`-Fachrechnung bleiben
unverändert; keine historischen Summen oder Verfügbarkeitsregeln werden ersetzt.
Zwei private lesende Hilfsmethoden bereiten den neuen Teil auf.

`LocalWebSupervisorController.php` liefert dieselbe bestehende View als
`no-store`-Response. `chrono/supervisor.blade.php` ergänzt eine vorhandene
Card-/Tablegestaltung, unabhängig von der historischen Summenverfügbarkeit.
Die vorhandenen DE-/EN-/zh-Supervisorlabels und dieses Dokument sind die übrigen
vier Dateien. Kein neuer Browsercode, Asset, Polling oder anderer UIumbau.

## Exakt bestehender aktueller Berechtigungsumfang

Die bestehende Snapshotgrenze lädt den angemeldeten Akteur und dessen Rolle erneut
auf dem zugelassenen `chrono`-Read-PDO. Nur aktuelle `admin`-/`supervisor`rollen
kommen bis zur Auswertung; ein veralteter Guard-Adminwert genügt nicht.
Die neue Namensliste liest direkt aus `users` mit exakt der bereits bestehenden
Bedingung `supervisor_id = viewerId OR viewerRole='admin'`, ohne historischen
INNER JOIN. Damit sind Personen ohne jede Buchung enthalten. Ehemalige oder andere
Teams werden nicht aus alten Buchungen ergänzt; es gibt weder implizite eigene
Zeile für Supervisoren noch Rekursion über deren Unterteams.

Der aktuelle native Supervisorumfang besitzt keinen zusätzlichen Standortfilter.
Dieses Paket erfindet keinen solchen Filter und verwendet weder Standort- noch
Terminal-Credentials, LDAPdetails oder einen requestgewählten Zielnutzer, um den
Umfang zu erweitern. Vorhandene Web-/Session-/CSRF-/Reauth- und Routenopt-ins
bleiben unverändert. Namen und Status entstehen im **selben** lesenden Snapshot
wie die frisch autorisierte historische Auswertung, nicht aus Clientidentitäten.

## Nur Name und Status, mit einem globalen Serverabrufzeitpunkt

Jede neue Personenzeile enthält ausschließlich `username` und den whitelisted
`state`; interne User-/Record-/Session-/Break-IDs werden nicht zurückgegeben.
Der einzige zusätzliche Metawert ist der einmal im Snapshot serverseitig erzeugte
`loaded_at`-Zeitpunkt in Berlin/ISO-8601. Keine clientgewählte Uhrzeit, Start-/End-
oder Pausendauer pro Person, Krankheit, Abwesenheitsgrund, Notiz, Projekt, Standort,
E-Mail, Token, ICS, Credential oder Gerätedetail wird hinzugefügt.

Die UI escaped Namen und Werte mit normalem Bladeoutput. Die Statuscard benutzt
keine vorhandene Überstunden-Filter-/Cardklasse und verändert deren Suche,
Detaildialoge, Charts, Tabelle oder Pagination nicht. Die vollständige aktuelle
Namensliste ist unabhängig von historischer Suche/Pagination; sie ist weiterhin
auf genau denselben autorisierten Personenkreis begrenzt. Ein leeres Team wird
als leeres zugeordnetes Team beschrieben, nicht als Anwesenheitsbehauptung.

| Verlässlicher vorhandener Stempelzustand | Anzeige |
|---|---|
| `working` | Arbeitet |
| `paused` | Pause |
| `absent` | Nicht anwesend |
| `unknown` | Status unklar |

Das ist ein **Stempelstatus beim Serverabruf**, kein Echtzeit-, Online-,
Hardware- oder physischer Anwesenheitsnachweis. Ohne offene Stempelmarker liefert
der vorhandene Helper auch bei einer neuen Person ohne Historie `absent`.
Daraus wird kein Grund für deren Nichtanwesenheit abgeleitet. Es gibt keine
automatischen Anwesenheits- oder Arbeitszeitkorrekturen und kein Hintergrundpolling.

## Unklare aktuelle Evidenz ist nicht „nicht anwesend“

Der existente reine `attendance_status()` bleibt die Zustandsquelle und wird
nicht geändert. Vor der Anzeige untersucht eine **nur darstellende** begrenzte
Zuverlässigkeitsprüfung höchstens zwei aktuell offene/leer endmarkierte
Session-, Projektions- und Pausenkandidaten je Person. Sie scannt keine
abgeschlossene Historie und lädt dafür keine Notizen oder Abwesenheitskategorien.

`unknown` entsteht insbesondere bei mehreren aktuellen Kandidaten, fehlender oder
abweichender Session-/Projektionszuordnung, nicht eindeutiger offener Pause,
Pausenmarker einer nicht offenen passenden Session sowie unlesbaren, zukünftigen
oder widersprüchlichen aktuellen Beginnwerten. Die bestehende kanonische `report_stored_epoch()`-Prüfung wird wiederverwendet;
relative oder reine Datumstexte ergeben NULL und bleiben unklar; es gibt keinen neuen Zeitparser oder
DST-/Payroll-/Rundungs-/Abwesenheitsregelwechsel. Ein alleiniger leerer Legacy-
Endmarker ohne Session wird nicht zu `absent`: Der bestehende Statushelper
berücksichtigt dort nur NULL, weshalb diese Anzeige bewusst unklar bleibt.

Erst nach dieser begrenzten Konsistenzgrenze wird der bekannte Helperzustand
übernommen; unerwartete Zustände/Recordbezüge bleiben ebenfalls unklar. Gründe,
Originalwerte und IDs der Unklarheit werden nicht in das neue UIpayload kopiert.
Das ist **keine vollständige Datenqualitäts-, Eventrekonstruktions-, Konflikt-,
Zeitmapping- oder Reconciliationprüfung**. Historische Ungültigkeit allein
ändert einen ansonsten eindeutigen aktuellen Stempelzustand nicht.

SQL-/Connection-/Schemafehler werden nicht als leere Liste, „unknown für alle“
oder Abwesenheit ausgegeben: Der bestehende Request-/Snapshotfehler bleibt ein
Fehler. Aggregate `available=false` bleibt unverändert, verhindert aber nicht die
unabhängige korrekt autorisierte Statuscard. Daten werden weder repariert noch
gelöscht, Schema-/Version-/Trigger-/Writer-/Auditregeln bleiben unberührt.

## Begrenzte Abnahme

Nur Syntax/Quell-Unverändertheitskontrolle und eine grobe erfundene
Speicherfixture: aktueller direkter Teamumfang gegenüber veralteter Guardrolle,
Working/Pause, Person ohne Historie, ausgeschlossener anderer Nutzer,
mehrdeutiger aktueller Zustand, unabhängige Card bei nicht finalen Aggregaten und
escaped Nur-Name-/Statusdarstellung. Keine vollständige Performance-,
Konkurrenz-, Datenschutzbetriebs- oder veröffentlichte UIabnahme.

Keine reale DB, Nutzer, Credentials, Datenkopie, Browser-/HTTP-/Netzwerk-/Server-
oder Deploymentaktion; keine neue CI gestartet. #52-Historiediff, Carry-in,
Backupadmission, ICS/Integrationen und sonstige Lanes werden nicht verändert.
