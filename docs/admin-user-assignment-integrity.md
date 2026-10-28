# Native Benutzerzuordnungen — begrenztes #31-/#34-Teilpaket

Basis: Main `7657688e0b3346d61d1adcb5995b895bfe189b36`, Tree
`8f027d80f07da9ad6dc8d07b7eeb3e2198dd3cf0`. Bezug:
[#31](https://github.com/storminator89/Zeiterfassung/issues/31) verlangt
serverseitige gültige IDs und Zuordnungen einschließlich Selbst-/Zyklusprüfung.
[#34](https://github.com/storminator89/Zeiterfassung/issues/34) hält Altdatenprüfung,
Migrations- und Ersatzregeln ausdrücklich offen; Fremdschlüssel nicht blind
aktivieren. Dieses Paket betrifft nur die nativen Anlege-/Bearbeitungsschreiber.

## Kleine feste Grenze innerhalb der bestehenden UI

`admin.php` behält seine ursprünglichen Formulare, Rollen-/CSRF-/Anmelderegeln,
Pflichtfeldprüfung beim Anlegen, SQL-Feldlisten, Erfolg-/Fehlerflächen und beide
Passwortzweige. Der bisherige Zuordnungshelfer liegt definitions-only in
`lib/admin_users.php`; kein Bootstrap, neue Verbindung oder Accountprojektion.
Benutzer-Schreibaktionen der vorbereiteten Laravel-UI bleiben deaktiviert.

- Die bisherige exakte Rollenliste `user`, `admin`, `supervisor` bleibt gleich.
  Eine reine Rollenprüfung vor der bestehenden typisierten Edit-Grenze erhält
  insbesondere den festen Validierungsfehler für nichtskalare Rollen; derselbe
  Rollencheck läuft auch innerhalb der Zuordnungsprüfung unter Lock.
- Eingereichte Abteilungs-/Vorgesetzten-IDs sind positive kanonische dezimale
  Strings oder Integer innerhalb des PHP-Integerbereichs. Keine Umwandlung von
  Arrays, bool, float, Null, Null-ID, Vorzeichen, Whitespace oder führenden Nullen
  in eine vermeintlich gültige Beziehung. Der vorhandene Parser der Edit-Ziel-ID
  im unveränderten Letzt-Admin-Helfer wird nicht geändert.
- Die eingereichte Abteilung muss aktuell existieren. Beide bisherigen nativen
  Formulare verlangen eine Abteilung. Der Admin darf weiterhin aus **allen**
  vorhandenen Abteilungen wählen: keine neue gleiche-Abteilung-/Mandantenregel.
- Nur der bisherige explizit leere Vorgesetztenwert `''` bedeutet weiterhin
  `supervisor_id = NULL`; ein fehlender/nichtskalarer Wert wird nicht als leer
  geraten. Ein gesetzter Vorgesetzter muss aktuell vorhanden sein und wie bisher
  die Rolle `supervisor` oder `admin` haben. Keine Beförderung oder Rollenänderung.
- Beim Editieren ist die Zielperson kein eigener Vorgesetzter. Die ausgewählte
  Vorgesetztenkette muss mit vorhandenen positiven IDs bei NULL enden. Erreichen
  der Zielperson, wiederholte IDs oder eine nicht auflösbare/verwaiste Kette
  werden mit festen escaped Validierungstexten abgelehnt. Es werden nur IDs und
  Kanten gelesen, keine Passwort-, Token-, E-Mail- oder Directorydaten.
- Nur die ausgewählte Kette wird bewertet. Unbeteiligte Altzyklen/-waisen werden
  weder repariert noch zum globalen Schreibveto. Eine andere gültige Auswahl
  oder explizit kein Vorgesetzter ist möglich; kein automatisches Umhängen.

## Dieselbe vorhandene Writer-Verbindung

Das Anlegen erhält eine eigene `BEGIN IMMEDIATE`-Einheit um Prüfung, bestehendes
Hashing und ursprünglichen INSERT. Scheitert BEGIN, gehört dem Helfer keine
Transaktion zum Zurückrollen. Nach erfolgreichem BEGIN stehen Prüfung und INSERT
unter demselben SQLite-Writerlock; Erfolg wird erst nach COMMIT gesetzt. Ein
regulärer Fehler rollt die eigene noch aktive Transaktion zurück. Kein
Fallback/Retry und keine Änderung von PRAGMAs, Timeouts oder Verbindungsoptionen.
Ein ungewisser Prozess-/Commit-/Antwortverlust ist damit nicht gelöst (#57).

Bearbeiten nutzt **unverändert** `admin_mutate_existing_user()` einschließlich
Zielprüfung, `BEGIN IMMEDIATE`, bestehendem Rollenanzahl-Letzt-Admin-Schutz und
COMMIT/ROLLBACK. Die Beziehungsprüfung findet jetzt innerhalb seines Callbacks
statt zuvor außerhalb des Locks. Deshalb kann dessen bestehendes Ziel-/
Letzt-Admin-Veto einer ebenfalls ungültigen Zuordnung jetzt vorgehen; beide
verhindern den Schreibzugriff. Seine gesamte bisherige Funktion und der
Lösch-/Historienzweig bleiben bytegleich. Die Prüfung soll keine andere
Transaktion übernehmen oder eine zusätzliche unabhängige Verbindung erzeugen.
Der vorhandene belegte-Abteilung-Löschschutz bleibt ebenfalls unverändert.

## Bewusste Grenzen

Kein `foreign_keys`-Schalter, neue Tabellen/Trigger/Indizes, Migration oder Lesen
von echten Bestandsbeziehungen. Die bestehenden Benutzer-Audittrigger und deren
bisherige Felder (`id`, `username`, `regelarbeitszeit`, `ueberstunden`) bleiben
unverändert; Rollen-/Abteilungs-/Vorgesetztenwerte sind weiterhin **nicht** als
vollständiger Vorher-/Nachher-Zuordnungsnachweis erfasst (#32). Kein zusätzliches
Raw-JSON, Secretfeld, Export oder Clientdatensatz.

Nicht entschieden oder umgesetzt: Ersatz-/Sperrregel beim Löschen/Herabstufen
eines Vorgesetzten mit direkten Untergebenen, Bereinigung vorhandener ungültiger
Beziehungen, globale Integrität anderer Schreiber und Fremdschlüssel-Migration.
Die bestehende LDAP-Managerpolitik bleibt getrennt; eine LDAP-Zuordnung befördert
niemanden zur Supervisorrolle und wird nicht durch diesen nativen Validator neu
bewertet. E-Mail-/Zahlen-/weitere Pflichtfeldregeln von #31 bleiben offen.
Vorhandene Authentifizierung und Rollenprüfung werden nicht erweitert; eine
frische Autorisierung unter dem Writerlock wird nicht als neu gelöst behauptet.

## Nachweisumfang

Ein einziger grober erfundener In-memory-Ablauf betrachtet die tatsächlichen
extrahierten nativen Anlege-/Editbodies und festen Helfer für gültige Auswahl,
Abteilungs-/Managerablehnung, Selbst-/indirekten Zyklus, verwaiste ausgewählte
Kette und eigene Transaktionsgrenzen. Dazu PHP-Syntax, Diffprüfung und
vollständige Quellerhaltung. Der grobe Nachweis und PHP-Syntax bestanden am
28.10.2026 unter PHP 8.5.12; COMMIT-Fehler und echte Konkurrenz wurden nicht
ausgeführt. Keine echte DB, Accounts oder Credentials,
App-/Session-/Auth-/HTTP-/Browserausführung, Server-/Quellrefresh, GitHubschreiben,
neue CI oder Deployment. Das ist keine Schutz-/Rollen-/UI-Abnahme und kein
vollständiger Abschluss von #31, #34 oder #32.
