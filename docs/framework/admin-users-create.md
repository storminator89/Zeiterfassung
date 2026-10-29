# #79 – vorhandenen lokalen Benutzer-Anlegebogen verbinden

**Folgeänderung #31:** Die damalige reine Native-Werteparität unten wird durch
die [gemeinsame Stammdatenvalidierung](../admin-user-assignment-integrity.md)
ergänzt: Name, E-Mail und endliche Tagesstunden/Anfangsguthaben werden am selben
Writer-Einstieg geprüft, ohne die bisherige Pflichtfeld-/Transaktions-/Hash-/
Authority-/SQL-Komposition zu ersetzen. Der neue Helper normalisiert keine Werte.

Begrenzter Folgeschritt der [nur lesenden Benutzerverwaltung](admin-users-readonly.md):
Nur `add_user` ist jetzt über die bereits optionale Laravel-Adminseite verbunden.
Bestehende URL, Karte, Feldnamen/IDs, Klassen, Icons, Rollenoptionen und ausgewählte
Originaltexte DE/EN/ZH bleiben erhalten. Bearbeiten, Löschen, Passwortänderungen,
LDAP, Tokens und bestehende Benutzerdetails bleiben unverändert gesperrt/lesend.
Kein Standardbootstrap, Route-Flag, Auth-/Sessionpfad oder Datenbankschema wird aktiviert.

## Ein fester Auftrag und die nativen Werte

`LocalWebAdminUsers`/`LegacyLocalWebAdminUsers` akzeptieren nur `add_user` mit
`username`, `password`, `email`, `role`, `department`, `supervisor`,
`regelarbeitszeit`, `ueberstunden`. Gemischte/unbekannte Adminaufträge werden weiter
vor Reads/Writes/Preferences abgewiesen; CSRF-Felder entfernt nur der vorhandene
Controller. Identität stammt ausschließlich aus dem aktuellen lokalen Guard;
Nichtadmins und LDAP-Actors erreichen keine Writerauflösung.

`AttendanceWriter::createLocalUser()` ist eine feste INSERT-Operation, kein
allgemeiner Kontenwriter. Sie erhält die native `empty()`-Pflichtprüfung, auch
für den String `0` bei der Regelarbeitszeit. Kein Trim, numerischer neuer
Wertebereich, neue E-Mail-/Passwortregel, Ersatzwert oder gleiche-Abteilung-Policy
wird erfunden. Die explizite Transportgrenze verweigert nicht-stringige
Formwerte (außer nativen fehlenden/null-Werten), statt Arrays/Objekte durch Hashing
oder PDO zu konvertieren. Normale Formularwerte bleiben unveränderte Strings.

Unter dem bestehenden einmaligen IMMEDIATE-/5000-ms-Writer-Lock werden Rolle
und `auth_source` der Actor-ID erneut geladen: weiterhin lokaler Admin ist Pflicht.
Das unveränderte `validateUserAssignment()` prüft auf demselben PDO die exakte
Rollenliste, kanonische positive Abteilungs-/Vorgesetzten-IDs, existierende
Abteilung, Supervisor/Admin-Auswahl und auflösbare azyklische ausgewählte Kette.
Leerer String beim Vorgesetzten bedeutet NULL; fehlender/null-Vorgesetzter wird
nicht still als leerer String normalisiert. Keine fachliche Reparatur fremder
Altketten. Der native rohe `admin_create_user()`-Transaktionswrapper wird nicht
geschachtelt aufgerufen.

`password_hash(..., PASSWORD_DEFAULT)` verarbeitet nur das gelieferte Passwort.
Das feste native INSERT schreibt dieselben acht Spalten; `auth_source='local'`
und `token=NULL` entstehen durch die bestehenden Defaults. Der vorhandene
Initialmodelltrigger erzeugt genau ein Wochenmodell, seine und die Benutzer-
Audittrigger laufen in derselben Transaktion. Vertrauenswürdiger Auditkontext:
`web:{Actor-ID}`, `admin.php`; kein Formularwert liefert Akteur/Grund. Keine
zusätzlichen Modell-/Auditzeilen, kein Retry und kein zweiter Writer.

## Vorhandene Oberfläche und Grenze der Abnahme

Die vollständigen Vorgesetztenoptionen stammen aus einer eigenen autorisierten
DEFERRED-Lesesnapshotabfrage: nur `id,username`, Rolle Supervisor/Admin,
Reihenfolge Username, unabhängig von Benutzersuche/Paginierung. Die vorhandene
Abteilungsliste bleibt unverändert. Weder Passwort/Token noch andere
Kontomodellfelder werden dafür gelesen oder ausgegeben.

Der Original-Anlegebogen nutzt die bereits vorhandene standardmäßig falsche
`adminPauseSettings`-Präsentationsfreigabe; das ist keine Autorisierung. CSRF,
RefreshLocalAccount, auth:web, auth.session und getrennte GET/POST-Routen bleiben
unverändert. Das Original-Toggle blendet nur das Formular ein/aus. Erfolg rendert
wie der native Create-Handler nach Commit mit `USER_CREATED_SUCCESS`, ohne neue
Weiterleitung oder automatische Wiederholung. Erneutes manuelles Absenden bleibt
die native Create-Semantik mit vorhandenen UNIQUE-Constraints, kein neuer
Idempotenzvertrag. Technische Fehler sind generisch und fordern eine Prüfung des
gespeicherten Stands; keine SQL-/Pfad-/Kontodetails und keine sichere
Rollbackbehauptung nach unbestätigtem Commit. Passwort und andere Eingaben werden
bei Rendering niemals zurückgespiegelt; bestehende Passwort-Logging-/Flashsperren
bleiben unverändert. Escaping erfolgt durch Blade.

Die isolierte lokale Webfixture verlangt derzeit exakt ihre zwei ursprünglichen
synthetischen Konten. Dieses Paket lockert diese Integritätsgrenze **nicht**:
Eine dort erzeugte dritte Zeile würde den nächsten Fixturezugriff verweigern.
Die Verbindung ist daher kein behaupteter bestandener Browser-/End-to-end-Test
und keine Freigabe dieses Fixtures für Benutzererstellung. Eine separat reviewte
Testfixturegrenze bzw. ausdrückliche Runtimekomposition ist dafür noch notwendig.
Kein echter Account, Login, Passwortwechsel oder Produktiv-/Host-/CI-Eingriff.

## Begrenzter Nachweis

Ein grober CLI-Ablauf verwendet ausschließlich erfundene Memory-Quelldaten und
eine eigene private kurzlebige SQLite-Rollbackjournaldatei, weil der echte Writer
Memory-Journalbetrieb absichtlich verweigert. Der tatsächliche geänderte Writer
wird geladen; kein App-/Guard-/Session-/HTTP-Boot. Geprüft: erfolgreiches lokales
Create mit nativem Hash/Defaults, genau ein Trigger-Initialmodell und zwei
geheimnisfreie Benutzer-/Modellaudits im vertrauenswürdigen Kontext; native
Pflichtfeld-/ungültige Zuordnungs-/Nichtadminverweigerung ohne Datenänderung;
künstlicher Auditfehler rollt Benutzer, Modell, Audit und Sequenzen vollständig
zurück. Die eigene Fixturedatei wird auch im Fehlerfall entfernt. Der Ablauf
prüft den Writer, nicht die vollständige Runtimeadoption oder Browserintegration.
PHP-/Blade-/JavaScript-Syntax ergänzen den begrenzten Nachweis, keine Testmatrix.
