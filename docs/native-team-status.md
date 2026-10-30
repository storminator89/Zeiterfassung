# #54 – Team-Stempelstatus auch in der nativen Vorgesetztenansicht

Begrenzter, rein lesender Schritt auf Basis Main `132ff9b0` (Baum
`4192386b82c35dbcc8767c9de0c78b7994885200`). Für die Integration auf Main
`c9483797ff8470998fd018042d2e988e3b1f015d` (Baum
`9acde7d4819aee8428e7aa286780964f522537f9`) erneut geprüft: alle neun
betroffenen Basispfade unverändert, bestehende Passwortänderungen erhalten.
Die aktive native
`supervisor.php` zeigt in einer bestehenden Card-/Tabellengestaltung die Namen
und aktuellen Stempelzustände des bereits berechtigten Teams. Kein neues Design,
Endpunkt, Polling, ICS-Feed, Gesundheitsdetail, Abwesenheitsschreiber oder V5-/
Runtime-Cutover. Die historische Fachauswertung bleibt unverändert; ihr bestehender Scope nutzt
die für den neuen Read frisch geprüfte Rolle statt eines bekannt veralteten Werts.

## Eine gemeinsame bestehende Statuslogik

Die bisher privaten Laravel-Methoden zur Teamstatusauswertung werden in
`lib/team_status.php` definitions-only gemeinsam verwendet. Die unveränderte
Zuverlässigkeitsprüfung, bisherigen SQLprojektionen und `attendance_status`
bleiben maßgeblich: Working/Pause/Absent oder bei widersprüchlichen aktuellen
Zeilen `unknown`. Das ist keine neue Anwesenheitsautorität und keine vollständige
Datenqualitäts-/Reconciliationprüfung. Die Laravel-Snapshot-/V5-Grenze und ihr
bestehender Rückgabevertrag bleiben erhalten.

Nur Name und ein erlaubter Status verlassen die gemeinsame Aufbereitung je
Person. Ein einziger serverseitiger Berlin-/ISO-Zeitpunkt gilt für die gesamte
Statusliste; keine Clientuhr oder pro Person frisch gewählte Uhr. Interne
User-/Buchungs-/Sitzungs-/Pausen-IDs, Beginnwerte, Pausendauern, Notizen,
Arbeitsorte, Abwesenheitsarten, Krankheit, Credentials und Schlüssel werden
nicht in die Statusprojektion übernommen. Die vorhandene historische Tabelle
wird dadurch nicht neu als frei von solchen Bestandsinformationen bezeichnet.

## Bestehender Scope, frische native Lesetransaktion

Der native Einstieg bleibt hinter dem bisherigen Bootstrap, Login und aktueller
Sessionrollenprüfung. Der neue Statusread benötigt einen inaktiven PDO, öffnet
nur eine eigene `DEFERRED`-Lesetransaktion und prüft darin denselben vorhandenen
nativen Passwortfingerprint (`auth_version`) und die aktuelle Rolle erneut.
Keine Sessionmutation, neue Auth-/Passwortregel oder Credentialantwort. Die
frisch bestätigte Rolle wird nur intern an den Seiteneinstieg weitergereicht,
nicht an die Status-DTO oder Karte. Scheitert die aktuelle Identitäts-/Rollenprüfung,
bleibt dieser Kontext leer: Der bestehende Rollenverweigerungszweig verhindert
dann auch alle nachfolgenden historischen Teamqueries. Wird Admin inzwischen
Supervisor, verwendet die historische Abfrage den engeren bestätigten Teamumfang.
Ein späterer reiner Statusqueryfehler löscht dagegen nicht den schon geprüften
Rollenwert und wird weiterhin nur als nicht verfügbare Karte angezeigt.

Nur aktueller Admin oder Supervisor darf Namen lesen. Der SQLumfang bleibt
exakt `supervisor_id = angemeldeterNutzer OR Rolle = admin`: aktuelle direkte
Zuordnung statt historischer Buchungen, kein impliziter eigener Eintrag,
Unterteam, Standort-, Rollen- oder beliebiger Personenselektor. Personen ohne
Buchung bleiben sichtbar, Umzuordnungen greifen im nächsten Snapshot. Die
vorhandene Adminliste umfasst weiterhin alle Benutzer, nicht eine neu definierte
Aktivitäts-/Standortpolitik.

Alle Statusabfragen teilen diesen einen PDO-Snapshot und Serverzeitpunkt. Die
bestehende historische Fachberechnung und ihre eigene Readgrenze werden nicht
still geändert. Dies ist keine vollständige Konkurrenz-/Auth-Race-Absicherung:
weitere Änderungen nach dem abgeschlossenen Statussnapshot bleiben außerhalb
dieser begrenzten Snapshotprüfung. Das neue Readmodul steuert keine Buchung, Migration,
Kalenderpflege, Audit- oder Eventreparatur. Es lässt keinen PDO, Callback oder
Credentialwert in den Anzeigevertrag entweichen.

## Sichtbare Grenzen statt erfundener Zustände

Die Karte bleibt unabhängig von historischer Suche/Pagination und nicht
abschließenden Überstundensummen. Sie verwendet keine IDs/Klassen der bisherigen
Suche, Kartenfilter oder Gruppentabelle. Namen, Zeitpunkt und lokalisierte
Statuswerte werden wie vorhandene native Texte mit `h()` escaped. Die neuen
DE-/EN-/zh-Texte übernehmen die bereits vorhandenen Laravel-Statuslabels.

Eine leere zugeordnete Liste wird ausdrücklich als leeres Team angezeigt.
Auth-/SQL-/Snapshotfehler werden dagegen als „nicht verfügbar“ angezeigt, niemals
als leere Liste, „alle abwesend“ oder „alle unknown“. Details aus Exceptions
werden nicht ausgegeben. Die Laravel-Fehlergrenze bleibt unverändert ablehnend.
Der Zeitpunkt ist eine Momentaufnahme, kein physischer Anwesenheits-, Online-,
Verfügbarkeits-, Hardware- oder zukünftiger Planungsnachweis. Geschlossene
Historie wird vom Status nicht vollständig gescannt; die Teamnamensliste selbst
bleibt wie im bestehenden Laravel-Vertrag ohne neue Personenpaginierung.
Keine Performance- oder großbetriebliche Datenschutzabnahme ist damit behauptet.

## Grober synthetischer Nachweis

`php framework/tools/rehearse-native-team-status.php` verwendet ausschließlich
vier kanonische Tabellen-DDLs in einer anonymen `:memory:`-Datenbank mit
18 erfundenen Personen, nicht verwendbaren Kennwortplatzhaltern und erfundenen
Buchungszuständen. Keine Appmigration, Datei-Datenbank oder Anmeldung.

Geprüft: direkter Team-/Adminumfang, fehlende Historie, kein Own-/Unterteamleck,
Working/Pause/Absent, konservative unklare Zustände, eine Berliner DST-Uhr,
Minimalprojektion, aktuelle native Rolle/Version, Transaktionsablehnung und
Rollback, unveränderte Quelldaten/Globals/Zeitzone, spätere Umzuordnung sowie das
**tatsächlich neue reine PHP-Kartenfragment** mit Escaping/Leer-/Fehlerzustand.
Vorhandene historische Fach-/UI-/JS-/SQLbytes und der ausgegliederte Zustandskern
werden daneben quellseitig abgeglichen.

Das ist keine vollständige native Seiten-, Laravel-, Login-/Cookie-/Session-,
HTTP-, Browser-/Layout-, Konkurrenz-, Produktionsdaten- oder Betriebsabnahme.
#54 bleibt wegen Abwesenheits-/Vertretungs-/Feed- und realen Abnahmegates offen.
Keine kostenpflichtigen Dienste, Host-/Sicherheitskonfigurationsänderung oder
Release-/Deploymentwirkung aus diesem lokalen Nachweis ableiten.

## #55-Nachtrag: ungeklärte Berliner DST-Wandzeit ist kein sicherer Teamstatus

Quellbasis Main `ea9bd198a764c5db39de8109b377f6b51e7f4d46`, vollständiger Baum
`3ca860a9062464146785119ea300296c1f82b558`. Die obige unveränderte
Zuverlässigkeitslogik beschreibt den ursprünglichen Stand. Bei genau einer
offenen Projektion kann dessen permissives `report_stored_epoch` eine doppelte
offsetfreie Herbststunde auswählen oder eine nicht existierende Frühlingszeit
verschieben und daraus trotzdem `working`/`paused` anzeigen. Der tatsächliche
alte gemeinsame Helper reproduziert beide Fälle in anonymen Memorydaten.

Die gemeinsame **Anzeigeprüfung** verwendet nun zuerst die bereits bestehende,
unveränderte `legacy_timestamp_assessment` und gibt ausschließlich für deren
`ambiguous_berlin_local_time` und `nonexistent_berlin_local_time` den vorhandenen
Status `unknown` zurück. Es wird weder ein Offset gewählt noch aus Sitzungs-/
Pausenepochen eine historische Zuordnung rekonstruiert. Auch ein zufällig
passender numerischer Sitzungsbeginn bestätigt den unaufgelösten ursprünglichen
Wandtext nicht. Der gespeicherte Zustand und Originalwert bleiben unverändert.

Eindeutige gewöhnliche offsetfreie Zeiten und explizite Z-/Offsetinstants bleiben
wie bisher; insbesondere sind beide ausdrücklich angegebenen Herbstoffsets
unterscheidbar und gültig. Andere bisher unterstützte oder nicht vom strengen
Diagnoseformat erfasste Darstellungen werden in diesem engen Paket nicht neu
klassifiziert. Alle bisherigen Mehrfach-/Inkonsistenz-/Zukunftsprüfungen bleiben.
Dies ist **keine vollständige Datenqualitäts-, Originalwert- oder Mappingfreigabe**.

Nur `lib/team_status.php` und diese Notiz ändern sich. Keine neue Zustandsart,
UI/Text/Filter/Route oder zusätzliche SQL-Abfrage; ursprüngliche SQLfelder,
Reihenfolge, Limits, Teamscope, aktuelle Rollen-/Versions-/Snapshotprüfungen und
Minimal-DTO bleiben. Der bereits vorhandene gemeinsame Helper wird weiter von
Native und Laravel konsumiert. `attendance_status`, eigener autoritativer
GET-Status, Stempelsteuerung, Pending-/Retry-/Writer-/Freigabegrenzen und sämtliche
Summen/Exports werden nicht geändert. Insbesondere ist das neue `unknown` weder
eine Abwesenheitsbehauptung noch eine erlaubte Reparatur oder Buchungsaktion.

Grober PHP 8.5.12 / SQLite 3.46.1-Nachweis erweitert außerhalb des Repositorys den
vorhandenen tatsächlichen `rehearse-native-team-status.php`-Ablauf: 29 erfundene
Personen auf vier originalen Memory-DDLs, fünf vorher bestimmt angezeigte DST-
Fälle jetzt unknown (einschließlich gematchter Sitzung/Pause und fremdem Team),
24 andere Adminzustände unverändert. Beide expliziten Herbstoffsets, UTC,
gewöhnliche offsetfreie Zeit, bisherige Bruchteils-/Nicht-Berlin-Offsetdarstellung,
Absent/Working/Pause/alte Unknowns, direkte Rollen-/Teamscopes, native aktuelle
Version/Rolle, Idle-/Fehler-/Rollbackgrenzen und tatsächliches unverändertes
PHP-Kartenfragment mit Escaping/Leer-/Fehlerzustand sind enthalten. Alter und
neuer Helper werden gegen denselben Snapshot verglichen; der zugrunde liegende
`attendance_status` bleibt auch in den fünf neuen Unknownfällen explizit gleich.
Quelldaten, Schema, Änderungszähler, anfängliches query_only, Globals und Zeitzone
bleiben erhalten; spätere synthetische Rollen-/Zuordnungsänderungen sind allein
Fixtureaufbau zwischen Reads. Ein anfänglicher externer Harness-Quotingfehler
wurde nur dort berichtigt, ohne weitere Produktänderung.

Notwendige PHP-Syntax, ganze Inverse/Replay und vollständige Quellenerhaltung
sind separat dokumentiert. Keine neue Repositorytestsuite, manueller CI-Start,
GitHub-Approvalreview, realen Konten/Schlüssel/Zugangsdaten, Datei-DB, Server oder
Aktivierung. Kein vollständiger Native-/Laravel-App-, Auth-/HTTP-/Browser-/Layout-,
Konkurrenz-/Performance-/Produktions- oder Betriebsnachweis und kein Deployment.
#55 bleibt teilweise/offen; Mapping/Reparatur und übrige Datenqualität sind separat.
Evidenz: `/tmp/chrono-team-dst-status-mainea9bd198-support/`.
