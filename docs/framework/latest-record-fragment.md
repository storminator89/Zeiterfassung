# Laravel: eigener letzter Zeiteintrag als natives HTML-Fragment

Teilumfang #79/#78 auf tatsächlichem Main
`37b5b4d6dd2178f55249c38819226b14f4a5f06e`, Baum
`17f22e4d0f2f1d91e03e81d397c066387f1f5294`. Kein Cutover und keine
Standardaktivierung: die Route ergänzt ausschließlich den ausdrücklich
aufgerufenen bestehenden `LocalWebRoutes`-Stack.

## Bewahrter nativer Vertrag

`get_latest_record.php` liefert das vorhandene einzelne Card-Body-HTML oder
`<p>Kein Zeiteintrag gefunden.</p>`, nicht JSON oder eine neue Seite. Klassen,
Grid, Dauerfeld, Detailfelder und Warn-/Korrekturlink stammen aus der nativen
Datei; alle Daten werden in Blade escaped. Fünf fehlende Labels sind je Sprache
exakt aus `languages/{de,en,zh}.php` in die bestehenden reinen Maps kopiert.
Keine globalen Sprachkonstanten oder ausführbaren Legacybootstraps.

`ChronoReads::latestWorkRecord()` ist **parameterlos**. Innerhalb der bestehenden
benannten Readtransaktion wird der aktuelle Guardbenutzer erneut aus `users`
aufgelöst. Der feste Query liefert nur `id,startzeit,endzeit,pause,standort,
beschreibung` dieses Benutzers, niemals fremde IDs, Token oder Authfelder:

- `user_id` stammt nur aus der aktuellen serverseitigen Identität;
- `absence_kind IS NULL` und `COALESCE(beschreibung,'') NOT IN
  ('Urlaub','Krankheit','Feiertag')` bleiben exakt erhalten;
- `ORDER BY (endzeit IS NULL) DESC,id DESC LIMIT 1`: offene **NULL**-Endzeit
  zuerst, danach höchste Anlage-ID. Das ist keine Zeitachsen-/Startzeitordnung.
  Auch der native Unterschied zwischen NULL und leerer Endzeit bleibt bewahrt.
  Kein stiller #17-Fix und keine Änderung von `reportRows()`/anderen Reads.

Die vorhandenen `report_record_calculation`, `report_format_hours` und
`booking_local_date` bleiben die einzigen Rechen-/Berlinformatierungsfunktionen.
Offen ist keine Null-Minuten-Arbeit; ungültige Daten zeigen `Ungültig`, festen
Diagnosehinweis und eigenen `adjustments.php?record_id=…`-Link. Standortcodes und
historische DE/EN/ZH-Werte werden mit vorhandenem `work_location_code` auf die
jeweils bestehenden Sprachlabels projiziert, unbekannt bleibt nativ `Unbekannt`.
Es werden keine Rohzeiten, Alttexte, Rollen oder Formeln repariert. Diese einzelne
Card behauptet keine globale Kollisions-/Vollständigkeits- oder Zeitkontoanalyse.

## Rechte und Transport

Die Route liegt wie bisherige geschützte Nutzerwege hinter verschlüsselten
Sessions, `LegacyFormCsrf`, `RefreshLocalAccount`, `auth:web` und `auth.session`.
Weder Query, JSON noch Formfelder können einen Zielbenutzer oder Datensatz
wählen. Die zusätzliche Snapshotidentität ersetzt nicht die Middleware-/
Passwortwiderrufsgrenze. Bestehende Read-/Snapshot-/Rollen-/LDAP-Kontexte bleiben
unverändert; kein neuer Team- oder Adminzugriff.

Der native anonyme Textabbruch ohne expliziten Status wäre versehentlich200.
Hier ist die bewusst benannte Sicherheitsabweichung **401 Text
`Nicht angemeldet.`** statt einer scheinbar erfolgreichen Datenantwort oder
Login-HTML. Auth-/Autorisierungsfehler werden nicht verschluckt. Unerwarteter
Read-/Renderfehler ergibt festen503-Text, keinen erfundenen leeren Erfolg und
keine SQL-/Pfaddetails. Erfolg bleibt HTML200, mit no-store/nosniff.

Die native Datei besitzt keinen eigenen Methodenguard. Deshalb werden die
bestehenden Laravel-Routermethoden plus TRACE/CONNECT gleich behandelt;
unsichere Methoden behalten zwingend CSRF, auch bei Same-Origin. Der feste
Raw-Formpfad verhindert globale Normalisierung der Tokenfelder. Unbekannte
nichtstandardisierte Methoden und vorgeschaltete Webserververbote sind keine
neue Zusage. Keine CSRFausnahme und kein zusätzlicher Schreibweg.

## Umfang, Integration und Nachweisgrenze

Zehn Pfade: zwei additive Readerdefinitionen, neuer Controller/DTO/Blade,
additive Route-/Authfehler-/Rawpath-Hunks, drei additive Sprachmaps und dieses
Dokument. Reader-Snapshot/andere Reads, Writer/Provider, HomeController und
#197, native Datei, Admin-/LDAP-/Fresh-Quellen bleiben unverändert.
Spätere Denial3-/LDAP-/Admin-/Fresh-Union muss die additiven Routen, Mapkeys,
Authfehlerabbildung und die bereits geltenden Opt-ins/CSRF/Limiters bewahren und
separat unabhängig geprüft werden. FreshWebs bestehende Fremdprofil-
Kollisionsliste muss bei seiner expliziten Kombination auch die neue URI prüfen.

Ein grober synthetischer Memory-Reader-/Controller-/Blade-/Routerablauf prüft
Ownscope trotz Zielparametern, native Selektion/offen/ID/Abwesenheit, leeren und
ungültigen Zustand, Dauer/Berlinlabels, Escaping/Sprachen, aktuelle Accountrolle,
feste Fehlerantworten und Middlewarezuordnung. Notwendige PHP-/kompilierte
Blade-Syntax und Diffhygiene sind geprüft; keine neue Testsuite oder CI.

Die erste Fixture hatte ihren bereits erzeugten Memory-SessionStore nur am
synthetischen Request, noch nicht für die globale ResponseFactory gebunden;
die behauptete erfolgreiche Fragmentantwort wurde daher sichtbar abgewiesen.
Nur diese Testbindung wurde ergänzt. Anwendung und Assertions blieben
unverändert; kein Appkey oder echter Sitzungs-/Credentiallauf wurde benötigt.

Das ist keine tatsächliche HTTP-/Session-/Login-/Passwortwiderrufs-/CSRF-/
Browser-/Produktivabnahme. Keine echten Konten, Credentials, Schlüssel,
Geschäftsdateien, Buchungen, GitHubwrites oder Runtime-/Hoständerungen.
#79/#78 bleiben teilweise umgesetzt/offen; kein vollständiger Migrationsclaim.


## Additive serial source union on Main `ddd3b1dd`

This integration reanchors the already source-reviewed Latest fragment
`489b52a19385ff36f687b3861a73ef32e379433b` and denial-only compatibility packet
`b8e7d55819a9c2a0182a136de0e0c11630401d37` onto actual Main
`ddd3b1dd7b28e714f270493105fa0503f5cf743a` (tree
`5807211d9365ce2c4820cee9fad3e2f843fae86a`). It does not reimplement either
feature or run the historical synthetic evidence again.

The exact reviewed Reader method/interface and five copied labels per locale
are additive; removing only them recovers every current Main byte, including
newer History methods/snapshot and Admin translations. Controller/DTO/Blade
code and the denial controller/document remain exact source. The common
registrar merges only the reviewed imports, raw paths, fixed anonymous-response
blocks and three protected method registrations. All current signature flags,
Budget selection/throttle, Terminal opt-ins, LDAP/Setup/registration gates,
CSRF/current-account/session middleware and existing actions remain unchanged.
No Writer, native endpoint, account source, key or business operation is edited.

The separately documented mechanical FreshWeb integration extends only its
foreign-profile collision list with `get_latest_record.php`, `delete.php` and
`import.php`; its profile/route-selection and existing Budget/LDAP/Setup options
are unchanged. The Fresh wrapper applies to these registrar additions as to
other registered paths; no profile is activated by this source change.

Current checks are necessary PHP/compiled-Blade syntax plus static hashes,
exact source/body mapping, reversible route/Reader/locale/Fresh projections and
whole-tree preservation. No local functional tests, new GitHub CI, real login,
HTTP/browser/CSRF/auth-pipeline/upload/restore/DB/secret/runtime/host change or
remote write is performed. Unsafe methods still inherit CSRF; TRACE/CONNECT
server support and protected endpoint behavior remain untested operational
gates. Existing source-review limitations and held unrelated work are not
cleared; #79/#78/#82/#65 remain partial/open. The final immutable source tree
requires its own independent serial-union review, not operational approval.
