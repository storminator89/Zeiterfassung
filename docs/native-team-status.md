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
