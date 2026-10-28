# FW-06 / #77 – inaktiver Bearer- und API-Lesekern

**Additiver CH-16/#22-Statusvertrag:** Der aktive native Pfad und der weiterhin
unregistrierte Kandidat kennen nun `GET /api.php/attendance`. Hinter der
unveränderten Bearerprüfung liefert ein eigener Lesesnapshot exakt
`{success:true,state,record_id,record_id_string}` aus demselben `attendance_status`-Ergebnis: `absent`, `working`
oder `paused`, mit ganzzahliger aktiver `record_id` und deren exaktem String, sonst
beide `null`. Die spätere #36-Projektion fügt nur Antwortmetadaten hinzu; keine
Änderung gespeicherter Quittungen, zusätzliche Query oder neue APIpflicht. Keine
Berichtszeilen, fremde Personenauswahl, neue Rollenfilterung oder Schreibaktion.
Der Kandidat nutzt `apiAttendanceStatus()` auf derselben Snapshotgrenze;
GET wird nach dem bestehenden POST-Kandidaten registriert; dessen Guard und
Writer bleiben erhalten. Das optionale #36-Parsing ist der spätere Nachtrag unten. Rohe HEAD-/Override-/Pfadregeln gelten
weiter. Der nativen `attendance_json`-Fehlerform entsprechend bleiben
`AttendanceError`-Status erhalten und unerwartete Fehler JSON-503, nicht der
JSON-500 der Berichtsleser. Keine Defaultregistrierung oder Runtimeumschaltung.
Der #36-Folgeschritt akzeptiert zusätzlich optionale Stringbindung im direkten
POST und nutzt die vorhandene nullable Writerweiterleitung; alter Feldentfall
bleibt null. Details und Grenzen: [APIvertrag](../API-CONTRACT.md#optionaler-exakter-record-string-und-neue-clientbindung-36).
Dies ist nur die API-Abhängigkeit der Erweiterungsreparatur, keine vollständige
Firefox-/Edge-, JWT-, Browser- oder Produktivabnahme.

**Kontrollierte JSON-Fehler nach #25 / PR #140:** Der zentrale
`LegacyApiResponse::text()` behält aus Kompatibilität seinen historischen
Methodennamen, liefert aber nun wie der native `abort_request`
`{success:false,message}` mit `application/json; charset=UTF-8` und dem
unveränderten HTTP-Status. Dies umfasst die bisherigen Bearer-, Login-,
Alias- und Löschsperren; `Retry-After: 600` bleibt erhalten. Nur diese
Fehlernachrichten ersetzen ungültiges UTF-8 durch U+FFFD. Allgemeine
JSONantworten, Lese-Metadaten sowie Erfolgs-/Replay-/Endwarnungsbelege werden
nicht neu kodiert oder umgebaut. Ungefangene Fehler werden nicht global
normalisiert. Guardentscheidungen und Routenaktivierung ändern sich nicht.

**Begrenztes Vorbereitungspaket, kein API-Cutover und kein Abschluss von #77.**
Die bestehende UI, `api.php`-Routen, Datenbank-/Hostkonfiguration und Clients
bleiben aktiv; nur der unten dokumentierte CH-18-Paginationvertrag ändert sich
bewusst in beiden Lesepfaden. Die ausgelieferte Laravel-Routenliste enthält weiterhin
nur `GET|HEAD /up`. Weder `LegacyApiRoutes::registerCandidate()` noch ein
API-Routenfile wird vom Bootstrap aufgerufen. Der neue Guard ist nicht der
Defaultguard; `chrono`, ein API-Schlüssel und eine öffentliche API bleiben
ungebunden beziehungsweise unkonfiguriert.

## Erhaltener Bestand statt neuer Authengine

Die drei bestehenden Definitionen `base64UrlEncode`, `base64UrlDecode` und
`validateJWT` wurden **inhaltlich unverändert** aus `api.php` nach
`lib/api_tokens.php` verschoben. `api.php` lädt diese definitions-only Datei an
der bisherigen Stelle; Login, Tokenausgabe, `validateToken`, Routing und die
Preflight-Ausnahme davor bleiben unverändert. Die neue Datei bootet weder
`config.php` noch `security.php`, erstellt keine Schlüssel, öffnet keine DB und
startet keine Session. Admin-Tokenausgabe wird nicht angefasst.

Der aktive Altpfad verwendet diese unveränderten Definitionen. Der inaktive
Laravel-Requestguard delegiert inzwischen an das
[gepflegte JWT-Paket mit Issuerprofiladapter](maintained-api-tokens.md).
Der beizubehaltende tatsächliche Issuervertrag ist:
HS256/`typ=JWT`, `iss=aud=localhost`, ganzzahlige `exp > now`, ganzzahlige
`iat <= now+30`, positive ganzzahlige `user_id`, Zeichenketten-`ver`,
4096-Byte-Grenze und unveränderte Signaturprüfung. Das ist ausdrücklich ein
**temporärer Legacy-Kompatibilitätsadapter**, keine neue gewünschte
Kryptographiearchitektur. Das gepflegte Paket ist für den inaktiven Laravel-Guard implementiert; die
aktive Altprüfung wird damit nicht geändert. Dessen dokumentierte `nbf`-
Nichtgleichheit und alle übrigen Cutovergates müssen vor Produktivumschaltung
entschieden werden. Sanctum ist kein Drop-in-Ersatz für diese Tokens.

`LegacyApiTokenKey` ist ausschließlich ein expliziter Containervertrag und hat
**keine** Standardbindung oder Dateiimplementierung. Eine spätere gesondert
freigegebene Provisionierung muss das bereits vorhandene `jwt.key` read-only
zulassen und dessen originale 64 kleine Hexzeichen **als ASCII-Bytes**, nicht
`hex2bin`, liefern. Kein `APP_KEY`, Pfadfallback, neuer Schlüssel, Token oder
Zugangsdatenwechsel. Ungebundene/formatfalsche Schlüssel werden verweigert.

Der Header bleibt exakt `Bearer ` plus bestehende Tokenzeichen, ohne Querytoken,
Cookie-/Sessionfallback oder case-insensitive neue Akzeptanz. Nach JWT-Prüfung
wird das aktuelle Konto über die benannte `chrono`-Verbindung geladen und
`sha256(password)` gegen `ver` geprüft. Die bestehenden drei 401-Meldungen
bleiben im JSON-Fehlerumschlag unterscheidbar. Anders als der lokale Weblogin
prüft diese bestehende Bearer-API **weder `auth_source=local` noch eine bekannte Rolle**: auch ein
bestehender gültiger LDAP-/Admin-Token liest nur seine eigene Person. Es wird
kein LDAP-Login oder neues Token ausgestellt.

## Laravelgrenze und eigene Leseprojektionen

Die Reihenfolge ist zwingend `LegacyApiWire` → `RequireLegacyApiToken` →
Readcontroller → `ChronoReads`. Die Bearermiddleware setzt für genau diesen
Aufruf den Defaultguard `chrono_api`, leert eine eventuell vorherige
Requestguard-Identität und stellt den vorigen Guard im `finally` wieder her.
Die API-Dienste allein sind **kein** JWT-/Passwortwiderrufsersatz; sie dürfen
nicht als öffentliche Controller ohne diese Middleware gebunden werden.

| Kandidat | Bewahrter Vertrag |
|---|---|
| `POST /api.php/login` | Inaktiver [Folgekandidat](candidate-api-actions.md): lokale Credentialprüfung/Tokenausgabe, ausschließlich mit explizit zugelassenem Loginbudget und Schlüssel. |
| `POST /api.php/attendance` | Derselbe Folgekandidat auf dem einzigen #75-Writer, `api:{id}`, keine Aliase oder Aktivierung. |
| `POST /api.php/workentry`, `/api.php/setendzeit` | [Inaktive bestehende Aliase](candidate-api-aliases.md) auf demselben APIwriter, keine neue Antwortform oder historischen Clientzeiten. |
| `GET /api.php/users` | `{success:true,data:[{id,username,email,role}]}`, ausschließlich die aktuelle eigene Person, auch als Admin. |
| `GET /api.php/timeentries` | Eigene rohe Projektionszeilen nach `startzeit DESC`, `{success,data,meta:{total,filtered,limit,offset}}`. Ein Query-`user_id` wird nie als Subjekt verwendet. |
| `DELETE /api.php/timeentry/{numericId}` | Authentifizierte Sperre: 409 JSON, Korrekturantrag statt historischer Löschung. Kein Writer. |
| `OPTIONS /api.php` oder Unterpfad | 200 leer, vor Auth/DB/Schlüssel/Session; bestehende drei CORS-Header unverändert, keine Cookie-Credentials. |
| Unbekannter Pfad / andere Methode | Bestehende 400-`Invalid endpoint`- beziehungsweise 405-`Method Not Allowed`-JSONform, kein Laravel-404/422-/Loginredirect für die erfassten HTTP-Methoden. |

Die zwei expliziten API-Erweiterungen `apiIdentity()` und
`apiTimeEntries(ApiTimeEntriesQuery)` verwenden dieselbe private
Snapshot-/Verbindungs-/aktuelle-Identitätsgrenze wie #78. Es gibt keinen zweiten
Transaktionsadapter, freies SQLcallback, neue Fachrechnung oder fremden
Datenpfad. Nur die API-Eigenleser behalten die bisher fehlende Rollenfilterung;
alle bisherigen Web-/Team-Lesemethoden behalten ihre bekannten Rollen- und
Policyprüfungen unverändert. Jeder Snapshot bleibt einmalig auf demselben PDO.

Laravel validiert die bisherige Datumssyntax und der Controller formt deren
Fehler bewusst in bestehende JSON-400 statt 422 um. Die rohe PHP-Query wird
vor Laravels Trim-/Nullnormalisierung rekonstruiert. URL-Rawpfad und tatsächliche
HTTP-Methode werden geprüft: kein neu akzeptierter Slash-/Percentalias,
implizites HEAD oder `_method`-/Headeroverride. JSON verwendet wie im Bestand
`json_encode` ohne neue HEX-Optionen; kontrollierte bisherige Textfehler
verwenden nun den oben beschriebenen nativen JSON-Fehlerumschlag.

**CH-04 / #9:** Native API und inaktiver Lesekandidat filtern nun über den
gemeinsamen `chrono_api_booking_day`-Adapter nach demselben Berliner Starttag
wie Berichte, nicht nach SQLite-UTC. Grenzen, Datenfehler und grober Nachweis:
[API-Buchungstag](../CH04-API-BUCHUNGSTAG.md). Formprüfung bleibt keine
Kalenderprüfung.

**CH-18 / #24:** Beide HTTP-Adapter verwenden jetzt denselben definitions-only
Parser für strenge nichtnegative Integer. `limit`: Standard **100**, Maximum
**1000**, **0** ergibt leere Seite; `offset`: effektiv **0**, Maximum
**2147483647**, bei Auslassung `meta.offset:null`. `meta.limit` meldet immer das
wirksame Limit. Invalidtypen/-syntax/-bereich liefern JSON-400 **vor** dem
Lesesnapshot, keine PHP-Koerzierung, SQL-500 oder Laravel-422. Die rohe PHP-Query
bleibt maßgeblich, keine Trim-/Nullnormalisierung der Pagination.

Dies ändert den bisherigen Vertrag **bewusst**: fehlendes Limit und Offset
allein liefern nun höchstens **100**, nicht alle übrigen Treffer. Die
bestehenden Popup-Clients mit explizitem `limit=1` bleiben kompatibel. Externe
Vollabruf-Clients müssen Seiten iterieren. Der bereits gemergte SQLite-
`LIMIT -1`-Fallback bleibt defensiv erhalten, ist für normal validierte
HTTP-Querys aber nicht mehr erreichbar. Genaue Eingaben, Defaults/Maxima,
Migrationshinweise und begrenzte Nachweise: [API-Paginierung](../CH18-API-PAGINATION.md).
Keine Aktivierung des Kandidaten, Auth-/Writeränderung oder neue
Cross-Request-Snapshotgarantie.
Die übrigen Änderungen aus
[#25](https://github.com/storminator89/Zeiterfassung/issues/25) müssen separat
abgestimmt werden, nicht als Carbon-/Paginatornebenwirkung entstehen.
Strukturell nichtskalare Datumsquerys werden im Kandidaten ebenfalls als
ungültige Form 400 verweigert; der Legacy-Pfad kann stattdessen einen PHP-TypeError
500 erzeugen. Dieser explizite Unterschied ist vor jedem Cutover zu entscheiden.

## Offene Gates, keine erfundene vollständige API

Der **unregistrierte** Kandidat besitzt inzwischen den begrenzten
[Login-/direkten Stempelfolgeschritt](candidate-api-actions.md) und die
[bestehenden Aliase](candidate-api-aliases.md). Ein vollständiger Routenschalter
bleibt vor persistentem Shared-Budget-, Profil- und Gesamtvertragsreview
verboten. Auch exotische, von Laravels `any` nicht erfasste HTTP-Methoden benötigen
vor einer späteren Gesamtaktivierung eine separate 405-/CORS-Gesamtkante.

Noch offen: zugelassenes persistentes gemeinsames Loginbudget; produktive
JWT-Migration/Profilentscheidung; vollständige Erweiterungsabnahme;
reale vollständige Start/Pause/Ende/Retry-/`expectedRecord`-Abnahme; Normalisierung ungefangener Fehler;
repräsentative vollständige Firefox-/Edge-Abläufe und deren reale Verteilung.
Kein Swaggerendpoint wird aus dem inaktiven Kandidaten als verfügbar behauptet.
Web-/Terminalschreiber werden nicht umgangen. Keine neue UI, kein realer
Zugang, keine gemeinsame Fixture, Port 18083, CI-Auslösung oder Veröffentlichung.

## Grobe synthetische Abnahme

```sh
cd framework
php tools/rehearse-api-compatibility.php
```

Ein parameterloser Ablauf durch echte Laravel-`HttpKernel`-Requestobjekte mit
anonymer SQLite-Memoryfixture und Wegwerfschlüssel nur im Prozess. Kein
HTTPserver, Netzwerk, Dateikey, lokaler UItest oder reale Daten. Vier Gruppen:
inaktiver Boot/Admissions/Preflight ohne IO; erweiterungsartige eigene
Identity-/Berlin-Starttag-/Paginationleser mit exakten JSONbytes; manipulierter/abgelaufener/
falsche-Audience-/passwortwiderrufener Token ohne gecachte Webidentität;
Wirefehler/CRUD-Sperre und unveränderter Snapshot. Dies ist keine Vollsuite,
kein produktiver End-to-End- oder Browserstore-/Verteilungsnachweis. Die grobe
bestehende #78-Leseabnahme und #75-Writerprüfung bleiben separat grün.
