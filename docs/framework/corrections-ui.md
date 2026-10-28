# FW-08 / #79 – bestehende Korrekturseite mit Laravel

**Begrenzte nächste lokale UI-Welle, kein produktiver Cutover und kein
vollständiges #79.** Aufbauend auf der [lokalen Webintegration](local-web-ui-integration.md)
und dem [gemeinsamen Korrekturdienst](local-web-corrections-service.md) wird
die vorhandene `adjustments.php`-Ansicht weiterverwendet, nicht ersetzt.

## Vorhandene Formulare und Antragskarten

`chrono.corrections` übernimmt das bestehende Markup, H1/Erklärung, Feldnamen,
CSS-Klassen, Offset-Platzhalter, Stornocheckbox, Fehler-/Gespeichert-Banner und
Antragskarten. Es wird keine neue Tabelle, Oberfläche, Zeitzonenautomatik oder
SPA eingeführt. Der gemeinsame vorhandene Header mit Theme/Navigation bleibt;
sein aktiver Menüpunkt ist nun explizit `adjustments.php` statt stets Home.
Alle neuen dynamischen Ausgaben sind escaped Blade. Die vorhandenen deutschen
Seitenlabels bleiben wie im Bestand deutsch. Keine Kalender-/Stundenneuberechnung.

Der dünne Controller verwendet ausschließlich bereits freigegebene Dienste:

- `ChronoReads::reportRows(chronological: true)` liefert eigene Einträge nach
  dem bereits übernommenen Instant-/ID-Vertrag aus #17.
  Für den identischen 200-Optionen-Anzeigecap werden die ersten 200 Zeilen
  genommen und nur `id/startzeit/endzeit` dargestellt. Keine neue Query, kein
  fremder Benutzerselector. Der bestehende Reader lädt weiterhin alle eigenen
  Zeilen; keine neue Performanceabnahme für große Bestände wird behauptet.
- `ChronoReads::correctionsPage()` liefert die bestehende native Statusauswahl
  mit gescopten Zählern und `id DESC LIMIT 50`/Offset in einem Readsnapshot.
  Der alte `corrections()`-Vertrag bleibt für andere Verbraucher unverändert.
  Das Viewmodel enthält nur sichtbare Felder; `original_json` bleibt im
  Kern/Bestand und wird nicht ins HTML eingebettet.
- Headeridentität kommt vom nach Middleware aktualisierten `web`-Guard,
  Anwesenheitsstatus vom bestehenden Reader. Zeitpunkte werden nur mit dem
  vorhandenen reinen `attendance_local`-Formatter angezeigt. Explizit geladen
  werden dessen Funktionsdefinitionen, niemals `config.php`, `header.php`,
  `functions.php` oder native Session-/DB-Bootstraps.
- `LocalWebCorrections::apply(array): void` ist der einzige POST-Schreibweg.
  Controller/Blade enthalten keine SQL-, Vieraugen-, Team-, Snapshot-,
  Finalitäts-, Transaktions- oder Auditregeln. Frischer zugelassener Writer,
  aktueller Guard und derselbe private Bestand kommen aus der bestehenden
  expliziten Composition, nicht aus Formfeldern.

## Exakter lokaler Formtransport

`GET adjustments.php` geht ausschließlich an `index` und liest/rendert.
`POST adjustments.php` geht ausschließlich an `store` und darf `apply` aufrufen.
Es gibt keinen Raw-POST-Dispatch in einem GET-fähigen Handler: ein effektives
GET durch Laravels Method-Override erreicht deshalb keine Mutation unter
übersprungener CSRF-Prüfung.

Die POST-Methode reicht nur den Raw-Form-Bag für
`action,record_id,cancel,start,end,pause,reason,id,decision,review_reason`
unverändert weiter. Keine Query-/JSONmischung, Actor-/Role-/Auditfelder,
Carbon-/Boolean-/422-Neunormalisierung. Die vorhandene eng begrenzte Ausnahme
von globalem Trim/Leerstringkonvertieren schließt zusätzlich ausschließlich
`adjustments.php` ein; `cancel === '1'` und die vollständigen nativen
Transformationen verbleiben im gemeinsamen Kern.

Beide Routen benutzen den bestehenden Stack: Session/`LegacyFormCsrf` →
`RefreshLocalAccount` → `auth:web` → `auth.session`. CSRF wird nicht deaktiviert;
die Formulare verwenden `@csrf`. Erfolg ist der native Redirect mit
`request_status`, `request_page`, ggf. `record_id` und `saved=1`;
`saved` ist wie bisher Parameterpräsenz. Fachfehler
werden wie im nativen Handler im vorhandenen HTML angezeigt, auch wenn der
Kern etwa eine 403/409-Exception liefert; der alte HTML-Handler lieferte dann
keine neue JSON-/422-Antwort. Technischer Formularfehler bleibt generisch
`Antrag konnte nicht verarbeitet werden.`. Middleware-Auth/CSRF-Verweigerung
bleibt davon getrennt. Form-Action, GET-Vorauswahl und die bisher nicht
automatisch wiederbefüllten Fehlerfelder bleiben wie im Bestand.

Reviewformularsichtbarkeit bleibt lediglich Darstellung: pending + andere
Person + angezeigte Admin-/Supervisorrolle auf einer bereits gescopten Liste.
Die tatsächliche Freigabe prüft unverändert ausschließlich der gemeinsame Kern
unter Writerlock. Auch Admins dürfen sich nicht selbst freigeben. Storno und
Ablehnen sind keine Attrappen, sondern dieselben vorhandenen Befehle am Kern.
Es wird keine automatische POST-Wiederholung oder erfundene Ereignis-ID für
Korrekturanträge eingeführt. Der Stempel-Pendingvertrag ist nicht automatisch
ein Idempotenzversprechen für Korrekturformulare.

## Opt-in und begrenzte Abnahme

Der Registrar wird weiterhin nicht standardmäßig aufgerufen; normales Boot
behält ausschließlich `/up`. `Interactions::corrections` ist standardmäßig
false und nur eine vertrauenswürdige Anzeigefreigabe, keine Autorisierung.
Nur der bereits isolierte Fixture-Launcher schaltet die Seite an und ergänzt
seine feste HTTP-Whitelist um genau `/adjustments.php`. Keine Legacy-Fallbacks,
neuen Produktionsbindungen, DB-/Host-/Schlüssel-/Sicherheitsänderungen oder
Zugriffe auf die bestehende App 18083. Sämtliche Kern-/Writer-/Read-/Auth-/API-
Dateien und bestehenden Assets bleiben unverändert.

Die vorhandene synthetische Fixture besitzt schon zwei getrennte lokale
Konten: Mitglied ID 1 und Admin ID 2. Keine neue Account-/Team-SQL für diese
UI-Abnahme. Abgenommen wurde genau ein realer unterstützter CUA-Ablauf:
Mitglied beantragt Änderung einer vorhandenen Buchung → eigener Antrag Offen,
Projektion unverändert → Logout/Adminlogin → andere Person gibt begründet frei
→ Mitglied sieht Freigegeben und geänderte Buchung. Read-only Abschluss prüft
Originalsnapshot, finale Entscheidung/Reviewer, beabsichtigte Projektion und
Kernauditakteure `web:1`/`web:2`, keine zusätzlichen Stempelereignisse,
SQLiteintegrität sowie unveränderte Original-/Backuphashes.

## Tatsächliche grobe Abnahme am 27.10.2026

PHP 8.5.12 / Laravel 13.38.0: neue ganze Ansicht kompiliert/rendert synthetisch;
PHP-Syntax, Composer-/Plattformprüfung, `git diff --check` und explizite
GET-/POST-Routen-/Middlewareauflösung ohne DBzugriff bestanden. Natives
Sessionglobal unverändert, benannter Login auflösbar, normales Boot nur `/up`.

Der unterstützte native Terminal/CUA-Browser verwendete ausschließlich eine
neue private bekannte V2-Fixture auf Loopback 18090. Das Mitglied ID 1 stellte
genau einen Antrag für vorhandene Buchung 42 (23.10.2026, Beginn 08:00+02:00,
Ende 14:00+02:00, Pause 30). Die vorhandene Seite zeigte „Offen“; vor der
Entscheidung belegte ein read-only Zwischenhalt die noch unveränderte
Projektion 08:00–13:00 und den exakt erhaltenen Originalsnapshot.

Nach Logout meldete sich der andere Admin ID 2 an und gab denselben Antrag
begründet frei. Nach Adminlogout/erneuter Mitgliedsanmeldung sah das Mitglied
„Freigegeben“, den Entscheidungsgrund und die geänderte vorhandene Eintragsoption
bis 14:00+02:00. Die ganze Korrekturseite wurde grob visuell angesehen; vorhandene
Header-/Form-/Kartenstruktur bleibt, kein App-Runtimefehler beobachtet. Keine
Ablehnen-/Storno-/Supervisor-/DST-/Negativeingabesuite als zusätzliche Abnahme.

Read-only Abschluss: genau ein Antrag `approved`, requester=1/reviewer=2,
Originalsnapshot weiterhin exakt vor der Änderung; Buchung 42 nun
08:00–14:00+02:00/Pause 30. Eine neue Antragsauditzeile unter `web:1`, drei
Freigabe-/Projektionsauditzeilen unter `web:2`, jeweils mit den exakten Gründen
des gemeinsamen Kerns. Null Stempelereignisse, eine geschlossene
Korrektur-Arbeitssession, keine offene Session, SQLite `quick_check=ok`,
Original-/Backuphash unverändert. Backendinspektion war ausschließlich lesend;
sämtliche fachlichen Änderungen liefen über die tatsächlichen UI-Formulare.

Echte Screenshots/Textsichten, vorhandenes Serverlog und Vorher-/Pending-/Final-
DBberichte liegen als lokale Prüfarbeitsdaten außerhalb des Repositorys.
Keine neue Mikro-/Rollen-/DST-/Pixeltestsuite, CI, reale Zugangsdaten oder
produktive Aktivierung. Weitere Korrekturzweige sind am selben bestehenden
Dienst verdrahtet, aber nicht durch diesen einen Browserfall neu abgenommen.
Vollständige #79-/Sicherheits-/operative Cutoverfreigabe bleibt ausstehend.

## #50 – ausgewählte Vorher-/Beantragt-Buchungsdauer

Ergänzende Basis: Main `6f032fd8b9faadad5b9da045e4296e11542251c3`, Tree
`eb465a4cf2a87b432e16767b4dccd06cde06d9a8`. Genau sechs Pfade: neuer reiner
`lib/correction_presentation.php`, vorhandene native Antragskarten in
`adjustments.php`, Laravel-Controller-Payload, `CorrectionsPage`-Payloaddokumentation,
vorhandene Korrektur-Bladevorlage und dieses Dokument. Keine neue Oberfläche.

In jeder bestehenden Antragskarte stehen nun ausgewählte gespeicherte
Netto-Buchungsdauer vorher und beantragt sowie eine vorzeichenbehaftete
Minutendifferenz, **nur wenn beide Seiten auswertbar abgeschlossen sind**.
Das ist eine Buchungsdauer-Vorschau, **kein Vorher-/Nachher-Kontosaldo**, keine
Freigabezusage und kein neuer Schreib-/Genehmigungsweg. Der tatsächliche Kern
prüft bei der Entscheidung unverändert den aktuellen Datensatz, Snapshot,
Zeit-/Gutschriftenregeln, Berechtigungen und das Vier-Augen-Prinzip.

Der originale Snapshot wird ausschließlich als begrenztes JSONobjekt gelesen:
höchstens 65.536 Bytes, Parser-Tiefe 8 und feste Auswahl von `startzeit`, `endzeit`
und `pause`. Andere Namen und Werte werden nicht in die Projektion übernommen;
keine beliebige Rekursion, Notiz-, Standort-, Personen- oder Geheimnisfelder.
Die vollständigen Originalsnapshots werden nicht an Blade weitergegeben.
Ungültiges/zu großes/zu tiefes JSON, fehlende Zeitfelder und falsche Typen
ergeben nur einen festen Hinweis, keine erfundenen Nullminuten. Der bestehende
`report_record_calculation()` allein liefert Dauer, tatsächliche Pause und
Offset-/Berlin-/DSTinterpretation. Gespeicherte beantragte Epochwerte werden
auf darstellbare Ganzzahlen geprüft und über den vorhandenen kanonischen
Instant-/Offsetpfad in denselben Kalkulator gegeben; keine neue lokale Zeitwahl.

Ein Nachtrag ohne Original heißt „Kein vorheriger Eintrag“, eine offene
Originalbuchung hat keine abgeschlossene Dauer. Storno heißt „kein neuer
Zeitwert“ und zeigt insbesondere nicht mehr die technischen Antragstimestamps
als scheinbar neu gebuchte Zeit. Diese Fälle sowie jede unlesbare Seite zeigen
**keine numerische Differenz**. Unbrauchbare vorgeschlagene Zeit-/Pausenwerte
und nicht positive Vorschlagsintervalle bleiben ebenfalls ohne Differenz und
werden nicht als scheinbar normalisierte Zeitangaben angezeigt.
Eine auswertbare gespeicherte Dauer ist ausdrücklich keine Zusage, dass der
gesamte Antrag nach allen unveränderten Regeln genehmigt werden kann.

Alle neuen Inhalte bleiben escaped Text in den vorhandenen Karten. Der Helfer
nutzt nur bereits gelesene, bereits autorisierte Anträge und deren gespeicherten
Snapshot; keine aktuellen Buchungen nachladen, keine Rückschlüsse aus fremden
Datensätzen und keine neuen SQLabfragen. Native Statusfilter/Seitenzählung und
Laravel-100er-Limit behalten ihre bisherigen getrennten Verträge. Query, Rollen,
Freigabe, Audit, Writer, Rohdaten, Routen, Auth, Session und übrige UI bleiben
unverändert. Kein Export oder Aktivieren der standardmäßig geschlossenen Laravel-
Verdrahtung und keine Datenmutation durch die Vorschau.

Ein grober rein erfundener Vorschau-/Unverfügbarkeits-/Auslassungsnachweis mit
16 vorhandenen Kartenfragmenten und PHP-/Blade-Syntax bestand am 28.10.2026 unter
PHP 8.5.12. Nachweis und Quellenprüfung werden separat eingefroren; keine tatsächlichen Daten, DB,
App-/Controller-/Session-/Auth-/HTTP-/Browserausführung oder neue CI-Läufe.
Geschützte/visuelle Abnahme, echte Datumsauswahl einschließlich Herbststunde,
Kontosaldovorschau, Rücknahme, Vertretung und vollständiges #50 bleiben offen.

Nach dem Abschluss wurde auch das Mitglied ausgeloggt, der eigene Browser-/
Terminaltab geschlossen und ausschließlich der temporäre 18090-Prozess beendet.
Der fehlende Listener wurde nativ bestätigt; die vorhandene 18083-Anwendung
und ihre Sitzungen blieben unangetastet.

## #14 – vorhandenen nativen Auswahlschutz auch in Laravel bewahren

Ergänzende Basis: Main `8aa48d23f843b33eb69efa625f177c651388fca2`, Tree
`a1f3c3a14f59a9d8ae7f74e4cdc1a4e8d5eea224`. Genau vier Pfade ändern sich:
Korrekturcontroller, angehängtes `CorrectionsPage::selectionInvalid`, bestehende
Formularvorlage und dieses Dokument. Native Seite und kanonischer
`lib/correction_selection.php` bleiben unverändert; keine neue Auswahlregel.

Der Controller verwendet denselben bereits vorhandenen **vollständigen eigenen**
`reportRows()`-Read genau einmal wie zuvor. Die ersten 200 Optionen behalten
ihre Reihenfolge. Eine ausdrücklich verlinkte gültige ältere eigene Record-ID
wird aus genau diesem bereits autorisierten Array gefunden und über denselben
nativen `correction_selection_options()`-Helfer einmalig angehängt. Es gibt
keinen zweiten Lookup, fremden Benutzerparameter oder erweiterten Teamscope.

`correction_selection_id()` allein besitzt die bestehende IDvalidierung:
GET mit expliziter ID verlangt eine positive ganze Zahl; nur POST `action=request`
darf ausdrücklich `record_id=0` für einen Nachtrag wählen. Arrays, Leerstrings,
Null, negative/gebrochene/überlaufende oder anders ungültige IDs werden nicht
durch einen Cast in einen Nachtrag verwandelt. In der vorhandenen opt-in Route
werden Korrekturfelder bereits von Trim-/Leerstring-Normalisierung ausgenommen;
Registrar, Middleware, Query-/Formparser und Sicherheitskonfiguration ändern sich
nicht. Ohne ausdrückliche Auswahl bleibt der bewusste normale Nachtragseintrag.

Ungültige, fehlende oder nicht im eigenen Read enthaltene ausgewählte IDs
liefern den festen escaped nativen Fehler und **kein Antragsformular**, stattdessen
denselben Link zur erneuten eigenen Auswahl. Nach einem fachlichen POSTfehler
bleibt die gültige POSTauswahl maßgeblich, nicht eine andere GET-ID; auch der
ausdrücklich gewählte neue Eintrag 0 bleibt erhalten. Request-only IDvalidierung
erfolgt vor dem Aufruf des unveränderten Writers; die bestehende Kernprüfung
besitzt weiterhin allein Besitz-, Freigabe-, Snapshot- und Transaktionsregeln.
Review-POSTs behalten ihren eigenen bisherigen Vertrag und werden nicht als
Antrags-/Recordauswahl umgedeutet.

Das neue Flag ist ausschließlich Darstellung, keine Berechtigung. Die #50-
Dauervorschau, Antragskarten und fremdberechtigten Reviewaktionen bleiben
bytegleich; ein ungültiger Formularselektor versteckt nicht die bestehenden
berechtigten Antragskarten. Kein SQL, Readerinterface, Kern/Writer, Datenwert,
Audit, Route, Auth, Session, sonstiger Sprach-/Kontroll- oder Aktivierungspfad
ändert sich. Laravel bleibt standardmäßig geschlossen.

Ein grober erfundener eigener Alt-/Fremd-/Fehl-/Ungültig-/POSTauswahlnachweis
und PHP-/Blade-Syntax bestand am 28.10.2026 unter PHP 8.5.12 und wird getrennt
eingefroren sowie unabhängig quellengeprüft.
Keine echten Daten, DB, Antragsmutation, App-/Auth-/Session-/HTTP-/Browser-
ausführung, neuer CI-Lauf oder Remote-Schreibzugriff. Das schließt die bekannte
Laravel-Quelllücke, ersetzt aber nicht die weiter offene tatsächliche #14-
Seiten-/Browserabnahme oder die übrigen Korrektur-/Migrationsgatter.


## Ergänzung: vorhandene native Statuswarteschlange und Seiten

Basis dieses getrennten Sechspfadpakets ist Main
`b5558c36b64c9491ddec26800a0588952d32316a`, Baum
`be08dfa141e24deef42e0a80e107e9d6e47bbeab`. Es übernimmt die bereits vorhandene
native Korrekturantragsliste, statt die vorbereitete Laravel-Seite dauerhaft auf
die letzten 100 Anträge aller Status zu beschränken. Bezug #79 und #50;
keine neue Fachfunktion oder neue Oberfläche.

Die bestehende Filterkarte, Labels/CSS und Seitenlinks werden in dieselbe Blade-
Ansicht übernommen: `pending`/Offen ist der Standard, daneben `all`/Alle Status,
`approved`/Freigegeben und `rejected`/Abgelehnt. Statuszähler, offene Anzahl,
Antragsgesamtzahl, Seite/von und leerer Zustand entsprechen dem nativen Markup.
Jede Seite zeigt höchstens 50 Anträge nach absteigender ID. Ungültige oder nicht
skalare Querywerte fallen wie nativ auf Offen/Seite 1 zurück; positive Seiten
werden vor dem Offset auf die vorhandene letzte Seite geklammert. Die normalen
Form-/Kartenfelder und das Vieraugen-Reviewformular bleiben unverändert.

`correctionsPage(status,page)` ergänzt das Readerinterface und den vorhandenen
Reader. Ausschließlich die aktuelle authentifizierte Identität aus dessen
unverändertem Snapshot darf den Scope bestimmen:
`a.user_id=? OR ?='admin' OR (?='supervisor' AND u.supervisor_id=?)`.
Damit bleiben eigene Anträge, bisheriger Adminrahmen und beim Supervisor das
aktuell direkt zugeordnete Team erhalten; kein rekursiver Team-, freier
Benutzer- oder neuer Datumsselector. Gruppierte Statuszähler und gefilterte
Antragszeilen werden in demselben frischen Snapshot gelesen. Die festen Status-
werte sind separat geprüft; Seitenzahl/Offset sind begrenzte Integer. Der
alte `corrections()`-Leser, alle anderen Reader-Methoden sowie die LDAP-/
Bestandskonto- und Snapshotprüfungen ändern sich nicht.

Filter-/Seitenlinks und Erfolgsredirect bewahren wie nativ die explizite eigene
Recordauswahl. Ein Antrag-POST überschreibt die Queryauswahl mit seiner
validierten eigenen Auswahl; explizit0 entfernt sie, ungültige Auswahl bleibt
als `invalid` markiert. GET-Werte werden wie nativ als Strings oder `invalid`
weitergetragen und erneut über denselben Auswahlhelper geprüft. Queryfelder
werden nie in den Writercommand gemischt. Auch Arrays im Raw-Form-Bag werden
nicht per skalarem Inputzugriff umgedeutet. Das URLziel bleibt fest
`adjustments.php`; Querywerte werden mit `http_build_query` codiert und im HTML
zusätzlich escaped.

Nur Controller, CorrectionsPage-DTO, Readerinterface/-methode, bestehende Blade-
Ansicht und dieses Dokument ändern sich. DTO-Zusätze besitzen inaktive Defaults,
sodass vorhandene statische Aufrufer weiterhin rendern. Keine Änderungen an
Registrierung, Onboarding, Auth, Routes/Provider, Writer/Befehlsallowlist,
CSRF/Middleware, Fachprüfung/Freigabe, Transaktionen/Audit, nativen Dateien,
Assets, DBschema oder Standardaktivierung. Die gelockten Abhängigkeiten bleiben
unverändert. Keine Performance- oder Lastabnahme wird behauptet.

### Begrenzter grober Nachweis

Unter PHP **8.5.12**, Laravel **13.38.0** und SQLite **3.46.1** wurde ein einziger
grober synthetischer Query-/Helper-/Renderablauf geprüft. Eine schreibgesperrte
anonyme Memory-PDO mit 125 erfundenen Anträgen vergleicht den tatsächlichen
nativen Queueblock mit dem inneren neuen Readerbody: eigene Statusseiten,
53 offene Anträge über zwei Seiten, aktuelle direkte Team-/Eigengrenze,
Adminbereich, leeres Team/leerer Status und Klammerung der letzten Seite stimmen
überein. Kein Dispatch des Readersnapshots und damit keine neue Rollen-/
Authabnahme wird daraus abgeleitet.

Die tatsächlich reinen Controllerhelper wurden mit skalaren und Array-Query-/
Formwerten sowie GET-/POST-Auswahl und codierten URLs geprüft. Drei inaktive
Bladefälle (zweite offene Seite, leere Liste, alter DTO-Aufruf) kompilieren und
rendern ohne DBverbindung; Seiten-/Auswahlbeibehaltung, Markup-Escaping und
fehlende Originalsnapshots wurden geprüft. Native Sessionglobals bleiben
unverändert. Notwendige PHP-Syntax einschließlich kompilierter Bladeausgabe
und `git diff --check` sind grün. Keine neue Testsuite oder CI gestartet.

Keine tatsächlichen Konten, echte Datenbank/Datei, Login-/Middleware-/Writer-/
HTTP-/Browser- oder visuelle Vollabnahme. Insbesondere wird kein neuer
Antrag-/Freigabe-/Redirect-Gesamtjourney oder Produktionseinsatz behauptet.
Das frühere Zweipersonenreview wird nicht rückwirkend als Nachweis dieser
Ergänzung verwendet. #50/#79 bleiben mit ihren übrigen Gates offen;
kein produktiver Cutover oder Runtime-/Host-/Schlüsselwechsel.
