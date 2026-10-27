# FW-06 / #77 – inaktiver Bearer- und API-Lesekern

**Begrenztes Vorbereitungspaket, kein API-Cutover und kein Abschluss von #77.**
Die bestehende UI, `api.php`-Routen, Datenbank-/Hostkonfiguration und Clients
bleiben aktiv unverändert. Die ausgelieferte Laravel-Routenliste enthält weiterhin
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
`sha256(password)` gegen `ver` geprüft. Die bestehenden drei Text-401-Meldungen
bleiben unterscheidbar. Anders als der lokale Weblogin prüft diese bestehende
Bearer-API **weder `auth_source=local` noch eine bekannte Rolle**: auch ein
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
| `DELETE /api.php/timeentry/{numericId}` | Authentifizierte Sperre: 409 Text, Korrekturantrag statt historischer Löschung. Kein Writer. |
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
`json_encode` ohne neue HEX-Optionen; Textfehler bleiben Text.

**Bewusst nicht repariert:** SQLite-`date(startzeit)` filtert UTC statt
Berlin-Kalendertagen; Formprüfung ist keine Kalenderprüfung. Numerische
Pagination bleibt die bisherige PHP-Koerzierung, einschließlich negativer Werte
und separater Metadaten. Der native Pfad und der inaktive Lesekandidat ergänzen
bei numerischem Offset ohne wirksames Limit jetzt SQLite-`LIMIT -1`: alle eigenen
Treffer nach dem Offset, ohne künstliche Seitengröße; `meta.limit` bleibt
unverändert. Dies behebt nur den SQLfehler aus
[#24 / CH-18](https://github.com/storminator89/Zeiterfassung/issues/24), nicht die
dort ebenfalls offenen Integergrenzen, Standard- und Maximalwerte. SQLite
dokumentiert [negative Limits als unbeschränkt und OFFSET nach LIMIT](https://www.sqlite.org/lang_select.html#limitoffset).
Die übrigen Änderungen aus [#9](https://github.com/storminator89/Zeiterfassung/issues/9) und
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
reale vollständige Start/Pause/Ende/Retry-/`expectedRecord`-Abnahme; Swaggerkorrekturen #24/#25;
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
Identity-/UTC-/Paginationleser mit exakten JSONbytes; manipulierter/abgelaufener/
falsche-Audience-/passwortwiderrufener Token ohne gecachte Webidentität;
Wirefehler/CRUD-Sperre und unveränderter Snapshot. Dies ist keine Vollsuite,
kein produktiver End-to-End- oder Browserstore-/Verteilungsnachweis. Die grobe
bestehende #78-Leseabnahme und #75-Writerprüfung bleiben separat grün.
