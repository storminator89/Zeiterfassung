# FW-08 / #79 – bestehende Änderungshistorie, optionale inaktive Webintegration

**Fortsetzung des vierteiligen Prototyps `00f99b507fb2086634b56fa1b3efbc0c84579c9f`,
keine neue Oberfläche und keine Webaktivierung.** Der ursprüngliche Controller-/
Lesevertrag wurde auf Main `165ce38008c3a4a54bc2b4fca4fa2c63160d8a78` vorbereitet
und separat mit PR143 übernommen. Die hier ergänzte gemeinsame Integration
setzt ausschließlich auf dem eingefrorenen lokalen Home-/Modell-Unionsstand
`3d3533e6f0d6d885f969060f45f934a162c855e4` (Tree
`fced8def4bbc33276faea308dd17c9b2f57e4319`) auf. Das ist eine geprüfte lokale
Abhängigkeit, keine Behauptung eines bereits gemergten Home-/Modell-Mains.
`history.php`, `lib/app_ui.php` und die mit PR126 übernommenen nativen
`lib/history_filters.php`-Definitionen sind die Markup-/Vertragsquelle.
Die gemeinsame Webverdrahtung änderte die Originalseiten und Assets nicht.
Der unten beschriebene spätere #52/A-Schritt ergänzt nun gezielt die bestehenden
nativen und Laravel-Historydetails, nicht ihre Filter, Sichtrechte oder Routen.

## Zwei getrennte, additive Teile

1. **Reiner Lesevertrag:** neues unveränderliches `HistoryQuery` und ausschließlich
   die zusätzliche Methode `ChronoReads::historyQuery(HistoryQuery $query): array`
   samt Implementierung. Das vorhandene `history(int $before = PHP_INT_MAX)`
   bleibt unverändert; keine Änderung an Snapshot, Guard, Rollen oder anderen
   Readmethoden. Gemeinsame Dateien dürfen bei Integration nur diesen Methoden-
   zusatz erhalten, niemals durch ältere Dateiversionen ersetzt werden.
2. **Vorhandene Darstellung:** die ursprünglichen vier Dateien
   `LocalWebHistoryController`, `HistoryPage`, `chrono.history` und dieses Dokument
   werden fortgeführt. Die bestehenden PR126-Filter, Fehler-/Leerzustände und
   Paginglinks ergänzen die bereits übernommenen Auditkarten, kein neues Design.

Das ursprüngliche Prerequisite-Paket registrierte weder Controller noch Route,
Fixture, Provider, Layout oder Featureflag. Der unten getrennt beschriebene
Integrationsschritt ergänzt ausschließlich sieben gemeinsame Pfade. Beide
Stände sind **statisch vorbereitet, nicht funktionsabgenommen**.

## Kanonische Filter, unveränderte Sichtrechte

`HistoryQuery` hat einen privaten Konstruktor und entsteht ausschließlich aus
`fromQueryString(string)`. PHP `parse_str` bildet wie natives `$_GET` die
unveränderte `QUERY_STRING` ab; danach übernimmt ausschließlich
`chrono_history_filters` die bestehende Validierung. Dadurch werden leere
optionale Formfelder nicht durch Laravels globale Leerstring-zu-null-Konversion
ungültig, und ein NUL wird nicht vor dem nativen Parser weggetrimmt. Keine
Middleware wird deaktiviert oder umkonfiguriert. Es gibt keinen Konstruktor für
beliebiges SQL, ungeprüfte Filter oder eine vom Request behauptete Identität.

Person-, Bereichs-, Datensatz-, Antrags-, Datums- und Cursorfilter behalten
sämtliche nativen Bedeutungen: alle gemeinsam mit `AND`, positive IDs,
Änderungszeitpunkt in `Europe/Berlin`, einschließlich beider Kalendertage mit
exklusiver Folgetagsgrenze. `request_id` bezeichnet nur die eigene Auditzeile
von `time_adjustments`, keine automatische Korrelation zu Buchung oder Sitzung.
Es gibt keinen Lookup in aktuellen Buchungs-/Antragstabellen und keine neue
Such-, Export- oder Korrelationsfunktion. Der spätere #52/A-Feldvergleich unten
verwendet ausschließlich die bereits autorisierten Snapshotwerte derselben Zeile.

Der Reader ruft `HistoryQuery::statementFor` ausschließlich innerhalb seines
vorhandenen DEFERRED-Snapshots mit der aus dem Guard stammenden, dort frisch
nachgeladenen Identität/Rolle auf. Die Methode delegiert an
`chrono_history_query`; SQL und Parameter werden nicht nochmals aufgebaut.
Scope bleibt: eigene Einträge, direkt zugeordnete Supervisor-Teamkonten oder
alle Einträge für aktuelle Admins. Admins sehen weiterhin Benutzer-ID `0` und
protokollierte gelöschte Konten. Keine rekursive Teamsicht, neue Berechtigung,
Journalmutation oder zusätzliche Kontoabfrage.

Ungültige Filter liefern ein Fehlerobjekt, niemals eine unbeschränkte Liste:
der Controller überspringt die Auditabfrage und antwortet mit HTTP 400;
der Reader verweigert ebenfalls die Auditabfrage. Ein direkter Aufruf von
`statementFor` mit ungültigen Filtern wird vom kanonischen Helfer abgelehnt.
Die sonstigen bestehenden Chrome-Leseanforderungen sind damit nicht abgeschaltet.

Absteigende `id < before`-Reihenfolge und `LIMIT 100` bleiben gleich. Nur bei
**genau 100** Zeilen erscheint „Ältere Einträge“ mit der letzten ID; es gibt keine
zusätzliche Existenzabfrage. `chrono_history_url` erhält dabei alle Filter.
„Neueste Treffer“ entfernt ausschließlich den Cursor; ein neues Filterformular
sendet keinen alten Cursor. „Filter zurücksetzen“ führt weiter zu `history.php`.

## Originalkarten und ausgewählte Snapshotdarstellung (#52/A)

Titel, Baselinehinweis, Klassen, Entitätslabels, Akteur/Begründung und aufklappbare
Vorher-/Nachherbereiche mit umbrechenden `pre`-Blöcken bleiben erhalten.
Unbekannte Entitäten bleiben als Originaltext sichtbar. Der reine native
`attendance_local`-Formatter liefert weiterhin Berliner Zeit mit Offset.
Es werden nur Definitionen geladen, niemals native Bootstrap-/Headerdateien.

`HistoryPage` enthält nur bereits autorisierte Anzeigewerte, native Filtertexte,
Fehler/Labels und relative Paginglinks. Der gemeinsame definitionsreine Helfer
`lib/history_presentation.php` ergänzt innerhalb der bestehenden Details einen
lesbaren Feldvergleich und **ersetzt zugleich beide bisherigen Roh-JSON-Blöcke**
durch eine ausgewählte, neu formatierte Projektion. Der Laravel-Controller gibt
die ungekürzten Originalsnapshots nicht zusätzlich an die Vorlage weiter.
Native `h()`-Ausgaben und Blade escaped alle dynamischen Werte, einschließlich
Feldwerte, Filter, Links und ausgewähltem JSON. Kein HTML-Rohoutput, Skriptpayload,
Downloadendpoint, Logger oder Writer kommt hinzu. Das sensible Journal wird
nicht in Prüfprotokolle oder Beispieldaten kopiert.

### Feste Auswahl statt beliebiger JSON-Erkundung

Die Auswahl ist pro Entität fest im Helfer definiert: Buchungsdaten (ID, Person,
Beginn/Ende, Pause, Notiz, Arbeitsort, Abwesenheitsart und gespeicherte Gutschrift),
die vier bisherigen Konto-Auditfelder `id`, `username`, `regelarbeitszeit` und
`ueberstunden`, Feiertagsdaten, Arbeits-/Pausenintervalle, Korrekturanträge und
Arbeitszeitmodelle. Unbekannte Snapshotfelder werden **weder mit Namen noch mit
Werten** ausgegeben. Eine unbekannte Entität bekommt keine Snapshotprojektion.
Unveränderte Kartenmetadaten (Bereich, Akteur und Begründung) behalten ihren
bisherigen Vertrag und ihre bisherige Sichtbarkeit.

`time_adjustments.original_json` wird nur als ursprüngliche Buchung mit derselben
festen Buchungsfeldliste gelesen, nicht beliebig rekursiv aufgefaltet.
`work_models.minutes_json` wird nur als Liste von genau sieben endlichen Zahlen
für Montag bis Sonntag angezeigt; andere Strukturen/Typen werden nicht enthüllt.
Ein erlaubtes skalares Feld darf String, Zahl, Boolean oder `null` enthalten;
Arrays/Objekte in solchen Feldern werden durch einen festen Hinweis ersetzt.
Passwörter, PINs, Tokens und andere unbekannte Konto-/Snapshotfelder gelangen
somit nicht über eine generische JSONansicht daneben wieder in die Darstellung.
Das ist eine strukturelle Feldfreigabe, **keine allgemeine semantische
Geheimnis-/Personendatenerkennung**: erlaubte Freitexte können weiterhin sensible
Angaben enthalten und bleiben dem bereits vorhandenen Audit-Sichtrecht unterworfen.

Jeder JSON-String wird auf höchstens 65.536 Bytes und Parser-Tiefe 8 begrenzt;
angezeigte skalare Strings werden nach 512 Unicodezeichen mit Hinweis gekürzt.
Die feste Feld-/Unterfeldliste begrenzt auch die Tabellenstruktur. Fehlendes/SQL-
`null` heißt „Kein Snapshot vorhanden“; leere, ungültige, zu große, zu tiefe oder
nicht objektförmige Snapshots heißen „Snapshot nicht darstellbar“. Bei einer
solchen Seite wird kein künstlicher Hinzufüge-/Löschvergleich erfunden.
Dasselbe gilt für die Kinder eines nicht darstellbaren Buchungs-/Wochenfeldes:
der Vergleich nennt nur das nicht darstellbare Elternfeld, statt fehlende
Kinderwerte zu behaupten.

Strings, Zahlen, Boolean, `null` und nicht vorhandene Felder werden unterschieden.
Verglichen werden die vollständigen **erlaubten dekodierten Skalarwerte**, nicht
nur ihre gekürzte Anzeige. Sehr große JSON-Ganzzahlen werden zur Präzision als
Strings dekodiert; die Projektion ist kein byteforensischer Vergleich der
gespeicherten JSONdarstellung. Verschachtelte Buchungs-JSONstrings und Wochen-
JSONstrings erscheinen ausgewählt als Objekt bzw. Liste. Das Audit selbst bleibt
unverändert. Ein leerer Vergleich bedeutet ausdrücklich nur „Keine Änderungen
an darstellbaren Feldern“, nicht keine Änderung im vollständigen Journal.

Beide Detailsansichten nennen die Auswahl immer und weisen bei ausgelassenen,
nicht erkannten, nicht darstellbaren oder gekürzten Inhalten zusätzlich darauf
hin, ohne die ausgelassenen Schlüssel oder Werte preiszugeben. Diese Begrenzung
ist bewusst auch eine Verengung der bisherigen ungefilterten JSONanzeige.

## Explizite optionale Webintegration, Default weiterhin geschlossen

Im damaligen Integrationsschritt blieb der Controller-/Read-/DTO-/Blade-Body aus PR143 bytegleich.
Das gilt ebenso für `HistoryQuery`, `ChronoReads`, `LegacyChronoReads`, sämtliche
nativen Dateien und Assets. Nur die vorher separat offene gemeinsame Verdrahtung
ist ergänzt; es gibt weder eine zweite Chromeansicht noch einen neuen Reader.

1. `LocalWebRoutes::register(Application $app, bool $includeHistory = false)`
   erhält ausschließlich einen angehängten optionalen Parameter. Bestehende
   Aufrufe ändern ihr Verhalten nicht. Nur `includeHistory: true` ergänzt
   `GET|HEAD history.php` → `LocalWebHistoryController::index` innerhalb derselben
   geschützten Gruppe. Es gibt keinen History-POST-, Export- oder Schreibpfad.
2. Persistentes Laravel-Sessionblocking und die vollständige Middleware bleiben
   unverändert: Websession/`LegacyFormCsrf`, dann `RefreshLocalAccount` →
   `auth:web` → `auth.session`. Der nur bei aktiviertem History-Parameter
   zuständige Auth-Renderer behandelt History als vollständige Seite und leitet
   zu `login.php`, nicht zu einem erfolgreichen HTMLfragment oder JSONstatus.
   Die bestehenden anderen Rendererpfade sind unverändert.
3. `AttendancePage` lässt zusätzlich `history.php` als aktive Seite zu; nur
   der vorhandene Sidebarlink erhält seinen Aktivzustand. Das angehängte
   `Interactions::history`-Anzeigeflag ist standardmäßig `false` und liefert
   ausschließlich eine ehrliche Verbindungsnotiz. Es ist keine Berechtigung,
   kein Requestparameter und keine Default-Routenaktivierung.
4. Ausschließlich die bereits ausdrücklich aufgerufene synthetische
   `LocalWebFixture` setzt `history: true` und `includeHistory: true`.
   Ihr vorhandener fester Router erlaubt zusätzlich `/history.php`; kein
   Legacyfallback, Repository-Webroot oder neuer Host wird eingeführt.
   Die Fixture wird in diesem Schritt weder vorbereitet noch ausgeführt.

`history.php` erhält **keine** neue Trim-/Leerstring-Ausnahme. Der unveränderte
Controller nimmt die rohe `QUERY_STRING`; `HistoryQuery::fromQueryString`
übernimmt `parse_str` und den kanonischen nativen Filterhelfer. Laravels globale
`TrimStrings`-/`ConvertEmptyStringsToNull`-Transformation des Querybags kann
diesen separaten String nicht verändern. Leere Felder und NULvalidierung
behalten damit ihren vorhandenen nativen Vertrag, ohne Normalizeränderung.

`routes/web.php`, Bootstrap, Provider, Auth-/Guard-/CSRF-/Sessionkonfiguration
bleiben exakt. Ohne expliziten Registraraufruf bleibt ausschließlich `/up`
registriert; auch ein bisheriger parameterloser Registraraufruf registriert
keine Historyroute. Kein Produktions-, Host-, Sicherheits- oder Authcutover,
keine Credential-/Cookie-/Token-/Sessioninjektion oder Runtimeaktivierung.
Die vorhandenen WIP-/Browser-/Authentifizierungsholds werden weder aufgehoben
noch umgangen. Die geschützte History-Browserabnahme bleibt offen.

## Historischer begrenzter Prerequisite-Nachweis

Am 27.10.2026 unter PHP 8.5.12 bestanden: Syntaxprüfung der neuen/geänderten
PHP-Definitionen, rein statische Blade-Kompilierung (19 escaped Ausgaben) und
Syntaxprüfung des Ergebnisses, ein zusammenhängender reiner Filter-/SQL-/URL-
Vertragsnachweis und `git diff --check`. Kein Auswerten des kompilierten Templates, Controllerdispatch,
Laravelboot, DBzugriff, HTTP/Server/Browser/Login, Session-/Credentialkontext,
Funktionstest oder neuer CI-Lauf. Die Ergebnisse stehen im separaten eingefrorenen
Übergabenachweis; Kompilierung ist keine visuelle oder funktionale Abnahme.

Dieses Paket ist ein standardmäßig inaktiver #79-Baustein, **keine** fertige
Migration, vollständige History-Funktionsabnahme oder Produktionsfreigabe. Eine spätere autorisierte grobe Ansicht muss die
bestehende UI und den realen Authweg berücksichtigen; keine Mikrotestmatrix.

## Begrenzter statischer Nachweis dieser optionalen Integration

Am 27.10.2026 unter PHP 8.5.12 bestanden: Syntaxprüfung aller fünf geänderten
PHP-Dateien, direkte Kompilierung der vorhandenen Historyvorlage und des
geänderten gemeinsamen Layouts samt Syntaxprüfung des erzeugten PHP sowie
`git diff --check`. Beide Vorlagen behalten ausschließlich escaped dynamische
Ausgaben (je 19); das kompilierte PHP wurde **nicht ausgewertet**. Es gab kein
synthetisches Seitenrendern und keine Behauptung einer visuellen Abnahme.
Die wiederverwendeten lokalen Dependencies besitzen denselben unveränderten
`composer.lock`; kein Install/Update oder neuer Dienst war erforderlich.

Zwei getrennte Prozesse prüften ausschließlich deklarierte Routenmetadaten:
Defaultboot jeweils nur `/up`, beim bisherigen Registraraufruf ohne Parameter
keine Historyroute, bei `includeHistory: true` genau `GET|HEAD history.php` am
bestehenden Controller mit der unveränderten geschützten Middlewarefolge und
auflösbarem benannten Login. Nach Entfernung der einen Historyroute sind beide
registrierten Metadatenlisten identisch. Die vorhandenen blockierenden
Session-/FileStore-Metadaten wurden ausschließlich für den Registrar gesetzt;
keine Anfrage wurde ausgeführt und kein Middlewareverhalten funktional geprüft.

Dabei blieben DBverbindungen leer, `chrono`-Konfiguration und Writerbindung
abwesend, Guard-/Session-/Verschlüsselungsinstanzen unaufgelöst sowie das native
Sessionglobal unverändert. Kein Schlüssel wurde erzeugt/eingesetzt, keine
Sitzung geöffnet und kein Login/Controller ausgeführt. Native Quellen sowie
Historycontroller, DTO, Readvertrag/Reader, Filterhelper und History-Body bleiben
gegenüber der eingefrorenen Modellabhängigkeit exakt. Der separate Nachweis
hält die Siebenpfadgrenze und den vollständigen Reversepatch fest.

Keine Fixturevorbereitung, DB, Credentials, Browser/Auth-Anfrage, HTTP-/Server-
oder Runtimeaktivierung, neuen GitHub-CI-Läufe oder Deployments. Insbesondere
sind Filtertransport, authentifizierter Seitenaufruf und Historypaging nicht
funktional beziehungsweise im geschützten Browser abgenommen. Das bleibt ein
offenes, gesondertes Gate; #79 ist damit nicht vollständig abgeschlossen.

## Abgrenzung und Nachweis des späteren #52/A-Pakets

Basis dieses ergänzenden Pakets ist Main
`c7169dedb5537e71641564e3fd2489e6c04787a9`. Genau sechs Pfade sind betroffen:
neuer Präsentationshelfer, native `history.php`, Laravel-Historycontroller,
HistoryPage-Payloaddokumentation, bestehende History-Bladevorlage und dieses
Dokument. Filterhelper, Reader, SQL, Rollen, Keysetpaging mit Limit 100, Audit-
Schema/-Bytes und sämtliche Routen bleiben unverändert. Die bestehende native
Seite nutzt den Helfer; Laravel bleibt im bisherigen standardmäßig inaktiven
Integrationszustand. Kein neuer Export und keine neue Erfassung/Verknüpfung
zusammengehöriger Auditzeilen wird eingeführt.

Der grobe Nachweis am 27.10.2026 unter PHP 8.5.12 bestand mit 15 ausschließlich
erfundenen Auditkarten für gemeinsame
Auswahl, verschachtelte Buchung, Wochentage, Typunterschiede, Auslassung, Kürzung
und escaped Ausgabe der beiden vorhandenen Detailsdarstellungen. Hinzu kommen
PHP-Syntax, direkte Blade-Kompilierung und `git diff --check` bestanden; kein Appbootstrap,
Controllerdispatch, echter Audit-/DBzugriff, Session/Login, Browser oder neuer
CI-Lauf. Der separate eingefrorene Nachweis nennt die tatsächlichen Ergebnisse.
Eine solche Quellen-/synthetische Darstellungsprüfung ist keine geschützte
Browserabnahme. #52 ist damit nur um den begrenzten Feldvergleich ergänzt;
Export-/Korrelations- und vollständige Akzeptanzanforderungen bleiben offen.
