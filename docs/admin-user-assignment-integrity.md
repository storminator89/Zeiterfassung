# Benutzerzuordnungen und Stammdatenvalidierung (#31 / #34)

Die ursprüngliche Zuordnungsintegration ist unten historisch beschrieben. Die
gemeinsame Native-/Laravel-Feldvalidierung ist im Folgeabschnitt am Ende ergänzt;
ältere Aussagen über unveränderte E-Mail-/Zahlen-/Ziel-ID-Regeln gelten nur für
das damalige Teilpaket, nicht für den ergänzten Stand.

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

## Gemeinsame Native-/Laravel-Stammdatenvalidierung (#31)

Folgebasis: Main `3d9bc5eaf691d011e1c6b07bc6c6cccb9dbe584a`, Tree
`691969683b162da5fd1185622d1612d56ec601d7`. Der definitions-only Helper
`lib/admin_user_fields.php` gilt jetzt für manuelles Anlegen **und** Bearbeiten
in `admin.php`, `AttendanceWriter::createLocalUser` und
`ManagedUserWriterOperations::editManagedUser`. Kein zweiter Validator, neue
Verbindung oder generischer Writer. Laravel prüft weiterhin zuerst die aktuelle
Authority unter seinem bisherigen Writerlock; die nativen Prüfungen liegen vor
Hashing/INSERT/UPDATE in den bestehenden eigenen Transaktionen.

- Benutzername: 1–100 gültige UTF-8-Bytes, keine ASCII-Steuerzeichen oder äußere
  Leerzeichen. Letztere passen nicht zum bestehenden trim-basierten Login.
- E-Mail: String mit gültiger vorhandener PHP-E-Mail-Syntax, höchstens 254 Bytes.
  Die Grenzen entsprechen dem vorhandenen Setup-/Registrierungsvertrag.
- Stunden: nichtleere endliche numerische Formstrings (auch Dezimal-/Exponenten-
  schreibweise), höchstens 64 Zeichen, keine Arrays, bools, NaN, INF oder
  Überläufe. Das vorhandene Tages-Soll liegt zwischen 0 und 24 Stunden
  (derselbe 0–1440-Minutenvertrag der Arbeitszeitmodelle). Ein signiertes
  Anfangsguthaben darf negativ sein und hat keinen erfundenen betrieblichen
  Höchstsaldo; nur die sichere Sekunden-/Minutenumrechnung im PHP-Integerbereich
  begrenzt seinen Betrag auf `intdiv(PHP_INT_MAX, 3600) - 1` Stunden.
- Edit-/Löschziel-IDs verwenden jetzt denselben bereits vorhandenen kanonischen
  positiven Integerparser wie Zuordnungs-IDs. Kein bool-/float-/Whitespace-/
  Vorzeichen-/Null-/Überlaufwert wird zum vermeintlich gültigen Ziel umgewandelt.

Werte werden nicht still getrimmt, normalisiert, geklemmt oder repariert.
Ungültige übernommene Altwerte werden ebenfalls nicht zurückgeschrieben. Die
bisherige Create-`empty()`-Pflichtsemantik einschließlich Tages-Soll-String `0`
bleibt erhalten; die Edit-Passwortzweige behalten leeres Passwort bzw. String `0`
als unverändert bei. Nichtskalare Passworteingaben werden vor Hashing abgelehnt,
ohne eine neue Passwortpolitik oder LDAP-Passwortrotation einzuführen.
Native Create behält bei bekannter vor-Hash-/vor-INSERT-Validierungsablehnung
über denselben vorhandenen Projektor nur begrenzte Nicht-Credentialfelder für
manuelle Überarbeitung, nie Passwort oder Rollen-/Zuordnungsentscheidung.

### Originalkriterien und bewahrte Grenzen

- **Atomarer Letzt-Admin-Schutz:** aktueller Rollencheck/-count und Mutation
  bleiben unter demselben IMMEDIATE-Lock. Die bestehenden Laravel-Schutzregeln
  für den letzten lokalen bzw. V5-loginfähigen aktiven Admin bleiben zusätzlich
  unverändert; keine Herabstufungs-, Lösch- oder Recoveryausnahme.
- **Ungültige Eingaben ablehnen:** bestehende Rollenliste, aktuelle Abteilung,
  berechtigter Supervisor, Selbst-/ausgewählte Zyklus-/Waisenprüfung bleiben
  erhalten, ergänzt um die oben genannten Pflichtwerte, E-Mail und Zahlen.
  Globale Admins dürfen weiterhin alle vorhandenen Abteilungen wählen; kein
  neues Mandanten-/gleiche-Abteilung-Modell. Nicht existierende/falsch kodierte
  IDs werden abgelehnt.
- SQL-Feldlisten, Audit-/Historienregeln, Auth/CSRF, UI und beide Passwortzweige
  bleiben erhalten. Tages-Soll-Validierung ändert kein datiertes Arbeitszeitmodell,
  keine frühere Gutschrift und keine Summenformel. LDAP/Registrierung/Setup,
  globale Altdatenbereinigung, Nachfolgerregeln für entfernte Vorgesetzte und
  Fremdschlüsselaktivierung (#34) bleiben eigenständige Themen.

### Grober Nachweis

PHP 8.5.12: ein isolierter Ablauf mit tatsächlichen nativen Handlerfragmenten,
gemeinsamen Helfern und erfundenen SQLite-Zeilen prüft gültige Edits sowie
Pflichtwerte/E-Mail/Stunden/Rollen/IDs/Zuordnungsablehnung ohne Datenänderung.
Zwei reale PDO-Verbindungen beweisen die Serialisierung: der zweite BEGIN kann
unter dem ersten Writerlock nicht eintreten; nach dessen Herabstufungs-COMMIT
verweigert der frische Count die Herabstufung des verbleibenden Admins. Dazu
PHP-Syntax, vollständige Pfad-/Modus-/Quellinverse und unabhängige Quellprüfung;
unveränderliche Head-/Tree-/Patchnachweise werden am PR festgehalten.

Nur unbrauchbare Passwortmarker; jeder Hashaufruf ist **im Harness** ein harter
Tripwire, keine erfundene Hashfunktion. Gültiges Create wird deshalb nur bis zur
Hashgrenze geprüft, keine erfolgreiche Passwortanlage/-rotation behauptet.
Kein Laravel-Writer-/Runtime-/HttpKernel-/Auth-/CSRF-/UI-/Browsertest, keine echten
Bestandsdaten, neuen Credentials, Keys, Appinstanz oder Sicherheitskonfiguration.
Browserabnahme übernimmt der Nutzer; die genannten Quell-/Komponentennachweise
sind keine Deployment- oder Gesamt-App-Abnahme.


## Exakte Abteilungsziele für Bearbeiten und Löschen (#34)

Quellbasis: Main `9cbe63d931388b1c5e3891a929f2e381be929828`, Baum
`67345d57a667907c4cb0dc2766f375eae3fb509f`. Die native Bearbeiten-Schaltfläche
übergibt die bestehende Abteilungs-ID jetzt wie der Benutzereditor als exakt
gequoteten String, nicht als JavaScript-Zahl. Zuvor konnte beispielsweise die
ID `9007199254740993` zu `9007199254740992` gerundet und beim Umbenennen die
benachbarte Abteilung geändert werden. Die Laravel-Schaltfläche verwendet
bereits ein exaktes String-Dataset und bleibt unverändert.

Beide vorhandenen Abteilungsmutationen verwenden nun denselben bestehenden
`admin_assignment_id`-Parser wie die Benutzerzuordnungen: positive kanonische
Dezimalstrings oder PHP-Integer im Integerbereich. Leerzeichen, Vorzeichen,
führende Nullen, Dezimal-/Exponentenformen, Überlauf, Arrays, bool, float und
fehlende Ziele werden nicht als ID normalisiert oder von SQLite umgedeutet.
Die native Umbenennung zeigt den festen Validierungsfehler in der bestehenden
Fehlerfläche, statt eine unbehandelte Ausnahme oder Missing-Key-Warnung zu
verursachen. Der vorbereitete Laravel-Controller behandelt diesen Fehlertyp
bereits; beide Writer verwenden weiterhin dieselben festen Kernfunktionen.

Keine neue Existenz-/Zeilenanzahlregel für Umbenennen, Namensnormalisierung,
Ersatzzuordnung oder Löschpolitik. SQL und vorhandene Writer-/Rollen-/CSRF-/
Transaktionsgrenzen bleiben gleich, einschließlich der Verweigerung einer
belegten Abteilung unter dem bestehenden Writerlock. Kein FK-Schalter,
Schema-/Audit-/API-/Runtime-/Hostdelta; keine neue Verbindung oder Migration.
Die zusätzliche Include-Kette enthält nur bestehende Funktionsdefinitionen.

### Begrenzter Nachweis

Ein grober anonymer Memory-Journey mit PHP 8.5.12 / SQLite 3.46.1 und Node
24.22.1 reproduziert aus den tatsächlichen alten Funktionen/Schaltflächen die
Nachbar-ID-Umbenennung sowie numerische Zielaliase. Das tatsächliche neue
PHP-Schaltflächenfragment und der Node-VM-Aufruf erhalten beide großen
Nachbar-IDs und `PHP_INT_MAX` exakt; die gemeinsame PDO-Umbenennung trifft nur
das beabsichtigte Ziel. 16 ungültige Zielarten werden vor jeder Mutation
abgelehnt. Belegte, freie und fehlende Löschziele, eine vom Aufrufer besessene
Transaktion und der tatsächlich extrahierte native Fehlereingang werden grob
geprüft. Syntax und vollständige Quell-/Pfad-/Moduserhaltung werden separat
geprüft. Kein kleinteiliger Testsuite-Ausbau und kein manueller CI-Start.

Dies ist kein tatsächlicher App-/Login-/Auth-/CSRF-/HTTP-/Browser-/Layout- oder
Laravel-Writer-Zulassungsnachweis; der Node-Aufruf ist kein Browser. Keine reale
Datei-DB, Bestandsbeziehungen, Konten, Zugangsdaten oder Betriebsumstellung.
Bestätigte reale Mappings, Fremdschlüssel-Migrationsplan und übrige #34-Gates
bleiben offen; aus dem Zielfix folgt keine vollständige Abnahme oder Reparatur.
