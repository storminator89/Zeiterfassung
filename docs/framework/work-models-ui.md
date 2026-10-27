# FW-08 / #79 – bestehende Arbeitsmodellansicht, opt-in integriert

**Opt-in Integration, noch keine HTTP-/Browserabnahme.** Auf das zuerst separat
geprüfte Vierdateienpaket folgt die ausdrücklich freigegebene gemeinsame
Integration auf dem unveränderten Home-Stand. Die vorhandene `work_models.php`-
Ansicht bleibt einfache Blade-Ansicht mit dünnem Controller und reinem DTO;
UI und Fachkern werden nicht ersetzt. Nur der bestehende ausdrücklich
aufgerufene Registrar verbindet die Route. Defaultboot bleibt ausschließlich `/up`.

## Vorhandene Karten, Felder und Tabelle

`chrono.work-models` erhält H1, Einleitung, Verwaltungskarte, Hinweise zur
Historie/Gültigkeit/Gutschrift, Feldnamen/-IDs, CSS-Klassen und Modelltabelle.
Die sieben Wochentage bleiben Montag bis Sonntag, die Tabellenüberschriften
bei den bisherigen zwei Anfangsbuchstaben. Dynamische Inhalte sind escaped
Blade; der vorhandene gemeinsame Header wird genutzt, nie `config.php`,
`header.php`, `functions.php` oder `lib/app_ui.php` eingebunden. Kein neues
Designsystem, JavaScript, Asset, Kalender oder eigenständiger Rechenweg.

Die Verwaltung sieht wie bisher alle username-sortierten Zieloptionen, ohne
automatische Auswahl des angemeldeten Kontos. Neues Modell: heutiges Datum,
alle Minuten 0, leerer Grund. Das Datum wird ausdrücklich als Berlin-Datum
formatiert, weil der native Bootstrap `Europe/Berlin`, Laravel hingegen UTC
verwendet. Dieser reine Form-Anzeigewert wird im Controller erzeugt, nicht im
DTO oder als neue Datumsvalidierung. Nach Fehlern werden die Felder wie im
Bestand nicht aus der Eingabe wiederbefüllt. Mitglieder sehen keine
Verwaltungskarte. Ein standardmäßig deaktiviertes Fieldset folgt dem bereits
vorhandenen inaktiven UI-Muster; es ist keine Autorisierungsprüfung.

## Ein Lese- und Schreibweg

`ChronoReads::workModels()` liefert bereits die native Liste: eigene Modelle
für Mitglieder/Supervisoren, alle für Admins, `valid_from DESC,id DESC`, maximal
200. Der Controller wählt nur sichtbare Felder und dekodiert/castet die bereits
gespeicherten Minuten zur Darstellung wie die native Tabelle. Er berechnet
keine Sollzeit, Priorität, Gutschrift oder Historie neu. `WorkModelsPage`
enthält ausschließlich diese Zeilen, Zieloptionen, Wochentagslabels und
Anzeigewerte; insbesondere keine Abfragen, Uhr oder Autorisierungslogik.

Die separat geprüfte parameterlose Methode `ChronoReads::workModelTargets(): array`
lädt im vorhandenen Snapshot den aktuellen Akteur nach und verweigert Nichtadmins
vor der Zielliste mit `AuthorizationException`. Für Admins liefert
sie unverändert `SELECT id,username FROM users ORDER BY username`, ohne neue
Team-/Kontotypfilter oder Ziel-ID aus der Anfrage. Der Controller ruft sie nur
für die angezeigte Adminrolle auf; die Dienstprüfung bleibt unabhängig davon
Pflicht. Die eigene Modellhistorie für Nichtadmins darf nicht gesperrt werden.

`LocalWebWorkModels::apply(array): void` ist der einzige Schreibweg. Der
bereits freigegebene Dienst gewinnt lokale Identität/Rolle aus dem `web`-Guard,
verweigert Nichtadmins vor Writerauflösung und nutzt denselben zugelassenen
privaten Operation-Writer. `user_id` ist nur das Zielkonto, nie Auditakteur.
Der vorhandene Kern behält Datum und Ziel-ID-Cast, Grundtrim/-länge (5 bis
1000 Bytes), `FILTER_VALIDATE_INT` für `day1` bis `day7` (0 bis 1440 inkl.),
append-only INSERT und exakten Auditgrund `Arbeitszeitmodell: {Grund}`.
Rückdatierung, gleiche Gültigkeit mit höchster ID, Feiertage und unveränderte
gespeicherte Abwesenheitsgutschriften bleiben bestehende Kernsemantik. Kein
zweiter SQL-/Policy-/Transaktions-/Audit-/Berechnungsbestand.

## Exakter opt-in Formtransport

- `GET work_models.php` ausschließlich an `index`; `POST work_models.php`
  ausschließlich an `store`. Kein POST-Dispatch aus einem GET-fähigen Handler.
- Nur unveränderte Raw-Formfelder `user_id,valid_from,day1,day2,day3,day4,day5,
  day6,day7,reason` gehen an den Dienst. Keine Query-/JSONmischung, Actor-/Role-
  oder Auditfelder, keine Form-Request-/Carbon-/422-Neunormalisierung.
- Erfolg: nativer Redirect `work_models.php?saved=1`; Gespeichert-Banner nach
  Parameterpräsenz, nicht Wahrheit des Wertes. Formular ohne neue Action-URL.
- `AuthenticationException` bleibt beim bestehenden Auth-Renderer.
  `AuthorizationException` des nichtadministrativen Schreibzugriffs wird wie
  das native Abbrechen vor `BEGIN` als **403 text/plain** ausgegeben, nicht in
  einen 200-HTML-Fehler umgewandelt. Der Dienst liefert dabei den bisherigen
  Text `Nur die Verwaltung darf Arbeitszeitmodelle festlegen.`.
- Fachfehler erscheinen wie im nativen Admin-Handler im escaped HTML ohne
  neue JSON-/422-Statusform. Unbekannter technischer Formularfehler bleibt
  `Modell konnte nicht gespeichert werden.`. Keine automatische Wiederholung
  oder erfundene Idempotenz-/Ereignis-ID für neue Modellversionen.

## Gemeinsame, ausdrücklich freigegebene opt-in Integration

Das ursprüngliche Vierdateienpaket ließ gemeinsame Dateien unverändert. Der
anschließende separat freigegebene Integrationsschritt ergänzt ausschließlich:

1. Den separat geprüften `workModelTargets()`-Vertrag und seine Implementierung
   im bestehenden `ChronoReads`-/`LegacyChronoReads`-Snapshot.
2. Ein zusätzliches standardmäßig falsches `Interactions::workModels`-Flag
   (benannte Argumente), `work_models.php` im `AttendancePage`-Aktivseitenvertrag
   und additive Linkmarkierung/ehrliche Verbindungsnotiz im vorhandenen Layout.
3. Getrennte GET-/POST-Routen im ausschließlich opt-in aufgerufenen Registrar;
   `work_models.php` in dessen eng begrenzten Trim-/Leerstring-Ausnahmen und
   geschützten Auth-Rendererpfaden. Unverändert Pflicht: persistentes
   Sessionlocking, Session/`LegacyFormCsrf` mit `@csrf`, danach
   `RefreshLocalAccount` → `auth:web` → `auth.session`.
4. Zur Vorbereitung einer später ausdrücklich zugelassenen Browserabnahme:
   benanntes Fixture-Anzeigeflag und festen HTTP-Whitelist-Eintrag, keine neue
   Konto-/Team-SQL oder Ersatzbindung in Produktivprovidern.

Das Flag steuert nur die Darstellung, nicht Rechte oder Produktivaktivierung.
Ein aktueller Guard und frischer explizit zugelassener Writer müssen weiterhin
denselben Bestand meinen. Keine reale DB, Schemaänderung, neue PDO-Factory,
Security-/Host-/Schlüsseländerung oder Runtime-/Portaktivierung gehört zu
diesem Paket. Der geprüfte Home-Ausgangsstand und seine Browserabnahme bleiben
getrennt; dessen eingefrorener Checkout wurde nicht verändert.

## Begrenzte spätere Abnahme, derzeit nicht behauptet

Nach separater Freigabe der Browserabnahme genau ein grober isolierter Ablauf:
synthetischer Admin legt für das Mitglied ein datiertes Modell an → nativer
Redirect/Banner und Tabellenzeile → Logout/Mitgliedlogin → Mitglied sieht die
eigene Version ohne Verwaltungsformular → Reload ohne zweites INSERT.
Read-only Abschluss belegt Ziel/Datum/sieben Minuten in richtiger Reihenfolge,
`created_by`, exakten `web:{Admin-ID}`-Auditakteur/-grund, erhaltene Altmodelle
und nicht betroffene Daten, Integrität sowie Original-/Backuphashes.

Die vorhandenen gemeinsamen Modellhistorien-/Abwesenheitssnapshot-/Rollback-
Beweise werden referenziert, nicht ohne Grund wiederholt. Keine neue breite
Rollen-, DST-, Negativ- oder Mikrotestsuite und kein Vollabschluss von #78/#79.
Notwendige Syntax-/Composer-/Plattform-/statische Blade-Prüfung ersetzt keine
fehlende HTTP-/Middleware-/Browserabnahme. Keine neue GitHub-CI, Remoteveröffentlichung,
reale Zugangsdaten, bestehende App 18083 oder synthetische Runtime 18090.

## Tatsächliche statische Prüfung des Vierdateienpakets

Am 27.10.2026 auf Basis `50fa7cff4c6aa85df39e922d1160dc6603cb6651`:
PHP-8.5.12-Syntaxprüfung von Controller/DTO, direkte Blade-Kompilierung samt
Syntaxprüfung des erzeugten PHP sowie Composer-Strict-/Plattformprüfung
bestanden. Die vorhandenen Dependencies entsprechen dem unveränderten Lock.
Die Blade-Prüfung bootete oder renderte keine Anwendung; keine DB, Session,
HTTProute, Fixture oder Runtime wurde dafür aktiviert. Dies beschreibt den
ursprünglichen Vierdateienstand; der folgende gemeinsame Integrationsschritt
ist davon getrennt. Seine echte Browserabnahme bleibt offen.

## Tatsächliche statische Prüfung der gemeinsamen Integration

Ausgang ist der eingefrorene geprüfte Home-Stand
`dd1743e3d768a6a52157311746c33c20cbbe46cc`, dazu exakt die Lesescheibe
`7f894807d1c57a182c8d147a39258972cc3c56cf` und das Vierdateienpaket
`23e4716806e9719034cd8c8d67d17f0c51547fd9`. Ihre Quellblobs und die reine
Union samt Reversepatch wurden vor Änderungen der sechs gemeinsamen Dateien
festgehalten. Die drei UI-Codeblobs und die gesamte Lesescheibe bleiben exakt;
dieses eigene Dokument beschreibt nun den anschließenden Integrationsstand.

Am 27.10.2026 bestand genau ein vollständiges synthetisches Admin-Seitenrendern
mit vorhandener Vorlage/Layout und Escaping, dazu die PHP-Syntax der
kompilierten Blade-Dateien sowie notwendige PHP-/Composer-/Plattformprüfung.
Explizite Routenmetadaten bestätigen getrenntes GET/HEAD→`index`, POST→`store`,
Session/CSRF vor frischem Konto und `auth:web`/`auth.session`, auflösbares
benanntes Login und unverändertes Defaultboot nur `/up`. Dies ist keine echte
HTTP-/Middleware- oder Berechtigungsabnahme.

Keine DB-Verbindung, keine benannte `chrono`-Konfiguration/Writerbindung,
unverändertes natives Sessionglobal. Für die reine Ansicht wurde nur eine
leere prozesslokale Array-Session aufgelöst: kein Login, kein `setUser`, kein
eingesetzter Authwert/CSRF-Token, keine Anfrageausführung und keine persistente
Session. Es wurde kein Server/Browser gestartet und keine Fixture vorbereitet.
Der Secure-Login-Freigabeblock der Home-Abnahme bleibt unangetastet; bis zur
gesonderten Freigabe ist auch der Arbeitsmodell-Browserablauf offen. Keine
Merge-/Veröffentlichungsfreigabe wird aus dieser statischen Prüfung abgeleitet.
