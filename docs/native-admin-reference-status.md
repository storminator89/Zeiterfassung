# Fehlende Stammdatenreferenzen sichtbar machen — Teilumfang #34

Lokaler Kandidat auf Remote-Main
`96e6200685188e1fb7f81b519d20447329730499`, Baum
`1c0bd825678d4a415ee6a14b00c6515cd196a057`. Vorhandene geprüfte 4c718818-Vorarbeit erneut übernommen: nur eine mechanische
SELECT-Spaltenunion erhält zusätzlich das inzwischen gemergte #31-
Historienbit. Helfer und beide Zellenfeatures bleiben bytegleich zur Vorarbeit.
Vorarbeiten schützen neue
Zuordnungen und das Löschen belegter Abteilungen. Dieser Slice diagnostiziert
nur den bereits gespeicherten sichtbaren Listenbestand, keine neue Fachregel.

## Konkreter Nutzen im bestehenden Adminablauf

Die native Benutzerliste zeigt bei LEFT JOINs fehlende Abteilungen/Vorgesetzte
bisher leer an — genau wie eine nicht gesetzte Zuordnung oder einen leeren
gültigen Namen. Zwei zusätzliche numerische Join-ID-Spalten derselben Abfrage
unterscheiden dies jetzt ohne neue Abfrage. Nur betroffene sichtbare Zeilen
erhalten einen knappen Hinweis unter der vorhandenen Zelle: fehlende positive
Referenz-ID oder nicht eindeutig prüfbare Zuordnung. Gültige bzw. NULL-
Zuordnungen und leere Namen vorhandener Ziele bleiben ohne neue Warnung.

Der Hinweis verweist auf den schon vorhandenen bewussten Bearbeitenablauf
derselben Zeile. Er ändert weder Auswahl noch Felder, öffnet keinen Dialog
automatisch und speichert nichts. Die Verwaltung muss eine gewünschte
Zuordnung ausdrücklich fachlich klären und auswählen. Der vorhandene
Same-lock-Validator prüft diesen späteren Schreibauftrag weiterhin separat.

## Rollen und Datenumfang

Die gesamte native Seite liegt unverändert hinter `config.php` und der
bestehenden aktuellen Adminprüfung. Kein neuer Endpoint, Fremd-/Teamscope oder
Personenwähler; Suche, zehn Zeilen pro Seite, Sortierung und Paginierung der
gleichen bisherigen Adminabfrage bleiben gleich. Die Referenzdiagnose ist an
diese Listenansicht gebunden, kein vollständiger Datenbankscan oder Zertifikat.
Keine Aussage, dass andere Seiten/Personen oder der ganze Bestand sauber sind.

Der neue definitions-only Helfer verwendet den bereits vorhandenen kanonischen
positiven IDparser `admin_assignment_id`. NULL bedeutet weiterhin ungesetzt;
ein nicht auflösbarer positiver Wert fehlt, andere unprüfbare Formen werden
nicht still zu NULL/0 umgedeutet. Ein Name allein ist kein Existenzbeleg. Die
Diagnose vergleicht zugewiesene und gejointe ID derselben Queryzeile, nicht zwei
zeitlich getrennte Abfragen. Sie bewertet keine Vorgesetztenrolle, Zyklen,
Abteilungsregeln oder LDAP-Policy neu.

Zusätzliche Ausgabe besteht nur aus festen Texten und bereits vorhandenen
numerischen Zuordnungs-IDs. Keine Passwörter, Tokens, PINs, Schlüssel, neuen
Namen-/Mail-/Notizdaten, Logs oder Downloads. Der bestehende `users.*`-Read wird
nicht erweitert um eine neue personenbezogene Quelle; neue Joinwerte sind nur
die numerischen Ziel-IDs. Vorhandene Namensausgabe und deren Escaping bleiben.

## Grenzen und Nachweise

Kein POST-/Auth-/CSRF-/Writer-/Audit-/Schema-/Fremdschlüssel-/Migrationsdelta,
kein Reparatur-UPDATE, keine automatische Ersatzzuordnung. Vorhandene
Last-Admin-/Belegungs-/Zuordnungs- und Transaktionsregeln bleiben bytegleich.

Nur ein grober anonymisierter `sqlite::memory:`-/tatsächlicher Query-/Helper-/
Zellenfragmentnachweis für NULL, vorhandene leere Namen, fehlende bzw.
unprüfbare IDs und Escaping sowie PHP8.5.12-Syntax und Quellerhaltung. Keine
echte DB, Konten, Keys, Appbootstrap, Login-/Session-/CSRF-/HTTP-/Browser-/
Modal-/Konkurrenz-/Großbestandabnahme. Keine Remoteaktion, neue CI, Host-/
Runtimeaktivierung oder Deployment. #34 bleibt partiell/offen.

## Aktuelle additive Zusammensetzung

Die ursprüngliche aktuelle Admin-Datei wird nach Entfernen nur des Require-
Zusatzes, der zwei Join-ID-Spalten, der vorbereiteten Foreach-Aufbereitung und
der beiden Zellenhinweise bytegenau wiederhergestellt. Der gemergte #31-
Historienhinweis und dessen Löschformular-Sperre bleiben vollständig erhalten.
Dessen vorhandener grober Memory-Nachweis wird zusammen mit dem unverändert
vorbereiteten Referenz-Journey erneut geprüft; dessen externe anonyme Fixture
erhält nur die inzwischen für die Listenabfrage erforderliche audit_log-Tabelle.
Kein neuer Algorithmus und keine Doppelimplementierung. PR #239 und dessen
offener Merge-Denial bleiben unberührt.
