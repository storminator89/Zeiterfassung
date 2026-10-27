# #79 – vorhandene Abteilungsverwaltung im bestehenden Admin-Opt-in

Die bisherige Admin-Pausenansicht enthält zusätzlich die originale
`admin.php#department-management`-Karte: vollständige ID-/Namensliste, vorhandene
clientseitige Suche, Anlegen, Umbenennen im vorhandenen Dialog und Löschen mit
Bestätigung. Karten-/Tabellen-/Formklassen, Icons, Feldnamen und Dialog-IDs bleiben
bestehen. DE/EN/ZH-Beschriftungen werden aus den vorhandenen nativen Wörterbüchern
übernommen. Escaped Datenattribute und `Js::from` ersetzen lediglich Inline-Daten
im JavaScript; Namen werden weder als HTML noch als ausführbarer Code eingesetzt.
Keine neue Oberfläche, serverseitige Suche, Sortierung, Seiteneinteilung oder
Abteilungs-/Benutzerzuordnung.

## Ein gemeinsamer Kern, kein verschachteltes BEGIN

`lib/departments.php` bleibt eine reine Definitionsdatei ohne Bootstrap,
Verbindungsöffnung, Migration oder HTTP-/Sessionzugriff. Die vorhandene öffentliche
Funktion `department_delete_if_unused(PDO, mixed): void` behält ihre Signatur und
ihre Transaktionshülle: ID-Validierung vor dem `BEGIN IMMEDIATE`, BEGIN außerhalb
des try (ein fehlgeschlagener BEGIN darf keine fremde Transaktion zurückrollen),
COMMIT und bedingtes ROLLBACK/rethrow unverändert.

Mechanisch ausgelagert sind die ursprüngliche String-/Int-/Positive-ID-Prüfung
in `department_delete_id()` und die ursprünglichen Statements/Prüfungen in
`department_delete_locked()`: Abteilung muss existieren; **jeder** noch zugeordnete
Benutzer verhindert die Löschung, unabhängig von Rolle/Authentifizierungsquelle;
DELETE muss genau eine Zeile betreffen. Existenz, Referenzprüfung und DELETE
laufen unter demselben Writer-Lock. Keine Kaskade, kein automatisches NULL-Setzen
und kein bloßes Vertrauen auf die SQLite-Foreign-Key-Einstellung.

Der native Handler ruft weiterhin die öffentliche Hülle. Laravel ruft nur den
transaktionsfreien Kern innerhalb der bestehenden dedizierten IMMEDIATE-/5000-ms-
Single-attempt-Hülle auf. Keine zweite Verbindung, kein natives BEGIN innerhalb
von `AttendanceWriter`, keine Savepoints und kein öffentlicher SQL-Callback.

Die native Anlegeprüfung `empty($department_name)` und die jeweils zwei
INSERT-/UPDATE-Zeilen sind ebenfalls nach `lib/departments.php` ausgelagert;
`admin.php` verwendet sie an genau denselben Stellen. Alle anderen nativen
Handler-, UI-, Account-, LDAP- und Tokenbytes bleiben erhalten. Die native
Löschhülle enthält dieselbe Transaktionslogik und delegiert nur Parser/Kern.

## Bewusst beibehaltene native Eigenheiten

* Anlegen verweigert `empty()` einschließlich `"0"`; reine Leerzeichen bleiben
  zulässig. Kein Trim, keine neue Längen-/Namensnormalisierung. `false` im engen
  Frameworkservice bedeutet nur diese vorhandene Ablehnung; die vorhandene
  übersetzte Pflichtfeldmeldung wird gezeigt, nicht Erfolg behauptet.
* Umbenennen übernimmt ID/Name unverändert. Nativ gibt es weder Empty- noch
  Existenz-/Zeilenzahlprüfung: auch eine nicht passende ID kann erfolgreich
  zurückkehren. Ein leerer Name wird nicht durch eine neue Serverregel verboten.
  Die vorhandene HTML-required-Validierung bleibt erhalten.
* Die bestehende Schema-UNIQUE-/NOT-NULL-Regel bleibt maßgeblich. Keine neue
  Groß-/Kleinschreibung, Dublettenpolicy oder Schemaänderung.
* Native erfolgreiche Aktionen redirecten und verlieren dabei ihre lokale
  Erfolgsvariable. Hier ebenfalls Redirect nach Commit auf den vorhandenen
  Kartenanker, keine erfundene Flash-Erfolgsmeldung.

**Explizite Transportunterschiede:** Native Anlege-/Umbenennungsstatements laufen
in Autocommit; Laravel verwendet für jedes dieselbe bestehende Writer-Transaktion
mit frischer Adminprüfung unter Lock. Native technische PDO-Fehler hängen rohe
Diagnosen an die übersetzte Fehlermeldung. Laravel zeigt nur den bestehenden
übersetzten Fehlerpräfix; keine SQL-/Pfad-/Personendaten, kein Erfolg bei unklarem
Commit. Fehlende Formularwerte werden als null weitergereicht statt native
Undefined-key-Warnungen in die Antwort zu übernehmen. Keine zusätzlichen
fachlichen Validatoren werden eingeführt.

## Aktuelle lokale Adminrechte, enge Befehle, gleiche Route

`ChronoReads::adminDepartments()` lädt Rolle und Quelle im bestehenden
DEFERRED-Snapshot erneut und verlangt einen aktuellen lokalen Admin **vor**
`SELECT id, name FROM departments`. Es werden keine Account-/Passwort-/LDAP- oder
Tokenwerte gelesen. Die Liste ist vollständig in der ursprünglichen Abfragefolge.

`LegacyLocalWebDepartments` verlangt den positiven lokalen Admin aus dem
bestehenden Webguard vor Writerauflösung, niemals eine Rolle/Identität aus dem
Formular. `AttendanceWriter::department()` liest Rolle/Quelle vor jeder Mutation
unter seinem vorhandenen Lock erneut; inzwischen entzogene Rechte verweigern die
Mutation. Die native Referenzprüfung ist davon unabhängig und bleibt vollständig.
Departments haben keine vorhandenen Audittrigger. Dieses Paket ergänzt kein
Journal und behauptet keine Protokollierung dieser Tabelle.

Kein weiterer Registrar, keine neue Route, kein Layout-/Auth-/Sessionumbau:
der vorhandene `includeAdminPauseSettings: false`-Default hält die gesamte
Adminseite inklusive dieser Karte weiterhin aus dem normalen Bootstrap heraus.
Die ausdrücklich zugelassene Adminseite verbindet jetzt beide unterstützten
Karten; die historische Dokumentation des reinen Pausenpakets beschreibt dessen
damaligen Umfang. Geschützte Sessionblockierung, CSRF, RefreshLocalAccount,
`auth:web`, `auth.session` und der auf admin.php begrenzte Raw-Formpfad bleiben
unverändert. LDAP-Admins sind im Framework weiterhin nicht zugelassen.

POST erlaubt exakt **eine** vorhandene Aktion plus ihre eigenen Felder:
`update_pause_settings` mit den bisherigen Pausenfeldern; `add_department` mit
`department_name`; `edit_department` mit `department_id`/`department_name`; oder
`delete_department` mit `department_id`, jeweils neben CSRF. Gemischte,
unbekannte oder Account-/LDAP-/Tokenbefehle ergeben unverändert 400 vor
Read/Write/Preference-Save. Anders als der native Mehrfach-if-Dispatcher kann
hier kein gemischtes Formular mehrere Aktionen auslösen. Diese bereits im
Pausenpaket zugelassene enge Transportgrenze wird gezielt erweitert, nicht
abgeschwächt. Benutzer, LDAP, Zugangsdaten und API-Token bleiben sichtbar als nicht
verbunden gekennzeichnet; kein nativer Fallback und kein stiller Teilerfolg.

## Begrenzter Nachweis / offene Abnahme

Ein grober Ablauf mit ausschließlich erfundenen SQLite-Zeilen vergleicht die
extrahierten nativen Operationen und Framework-Schreibhülle, einschließlich
referenzierter Löschung, Namenseigenheiten und inzwischen entzogener Adminrechte.
Dazu Quellblock-/Unverändertheitsnachweis, notwendiger PHP-/Blade-/JS-Syntaxcheck
und inerte Inhalts-/Escapingprüfung; keine kleinteilige neue Testmatrix.
Keine realen Daten, tatsächlichen Accounts, Session-/Credentialinjektion,
HTTP-/Browser-/Serverausführung, Installation oder neue CI-Läufe. Geschützte
UI-/CSRF-/Anmeldeabnahme und Betriebsaktivierung bleiben ausdrücklich offen.

Der eine grobe synthetische Ablauf bestand unter PHP 8.5.12 mit dem bestehenden
bekannten-v2-Fixture und dessen Offline-Adoption. Originale native SQL-Blöcke und
die ursprüngliche Löschhülle dienten als Vergleich: Zeilen und Departmentsequenz
waren nach Anlegen/Umbenennen/Löschverweigerung/Löschen gleich. Andere vorhandene
Fixturetabellen blieben von den Departmentoperationen unverändert; eine danach
nur synthetisch entzogene Adminrolle verweigerte einen weiteren Schreibversuch
unter Lock ohne Teiländerung. Original und Backup blieben unverändert; sämtliche
neu erzeugten privaten SQLite-Dateien wurden entfernt. Default-Routen blieben
nur `/up`, keine Geschäftsverbindung/Writerbindung oder Auth-/Sessioninjektion.
DE/EN/ZH-Inhaltskopien wurden inert mit erfundenem HTML-/JS-feindlichem Namen
rendernd auf Escaping geprüft; die echte CSRF-Direktive nur kompiliert/gelintet,
nicht ausgewertet. Zwei Fehler des Evidence-Helfers (zu breite SQL-Quellerfassung,
optionale nicht vorhandene Fixturetabellen) wurden ohne Anwendungsänderungen oder
abgeschwächte fachliche Assertions korrigiert und im Handoff offengelegt.
