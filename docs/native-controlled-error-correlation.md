# Kontrollierte native 5xx korrelieren — Teilumfang #66

Lokaler Kandidat auf veröffentlichtem Main
`6967de41a5dca22613914619e0537e0285cf1d1f`, Baum
`fb53e441227fffb39e5cdcc2fddc8016cceacd0b`, nach #31/#50/#55/#56/#61.
Der vorhandene unabhängig geprüfte Kandidat wurde ohne Funktionsänderung von
der früheren Basis `cf656288` übernommen; nur diese Basisdokumentation wurde
angepasst.
Vorarbeit PR #230 betrifft
Laravel; dieser enge Slice betrifft ausschließlich den bereits vorhandenen
nativen `abort_request`-Pfad. Kein Release/Deployment.

## Tatsächliche Reichweite

Ein kontrollierter Status von 500 bis 599 in einem HTTP-SAPI erzeugt vor der
Ausgabe eine neue
serverseitige 128-Bit-Zufalls-ID (32 kleine Hexzeichen). Sie wird ausschließlich
als additiver Antwortheader `X-Request-ID` und in einer festen Best-effort-Zeile
des bestehenden PHP-Logtransports verwendet:

`Native controlled response failed. request_id=<32 Hexzeichen> status=<5xx>`

Keine Client-ID, kein Body/Query/Cookie/Authorizationwert, Benutzer, IP, URL,
Fehlernachricht, SQL, Exceptiontext, PIN oder Schlüssel wird in diese neue
Logzeile übernommen. Die ID ist weder authentifizierend noch eine Buchungs-,
Ereignis-, Korrektur-, Session- oder Idempotenz-ID. Kein globaler Loggerkontext
und keine neue persistierte ID.

Status und bestehende feste JSON-/Textbodybytes bleiben unverändert. Es gibt
kein zusätzliches JSONfeld und keine Änderung an CORS-/Expose-Headern,
Content-Type, Retryverträgen, Auth/CSRF, Securitybootstrap, Terminal-HMAC oder
Framework-Middleware. Normale Antworten und 4xx erzeugen hier keine neue ID
oder Logzeile. Bei bereits gesendeten Headern wird nicht versucht, nachträglich
eine ID einzuschleusen. Sehr frühe Fehler, PHP-Fatals, Ausnahmen oder andere
native Fehlerantworten, die `abort_request` nicht erreichen, sind nicht erfasst.

Entropie-, Header- oder Logtransportfehler dürfen die ursprüngliche Antwort
nicht ersetzen. Der Logeintrag ist ausdrücklich best-effort: Ein Header kann
auch dann vorhanden sein, wenn kein Logeintrag zugestellt wurde. CLI und phpdbg sind ausdrücklich ausgeschlossen: Ihre bisherigen Ausgaben
und Diagnostik bleiben unverändert, statt ohne HTTP-Antwort eine neue
Korrelationslogzeile zu erzeugen. Der allgemeine bisherige
PHP-/Anwendungslogbestand wird nicht umgeschrieben oder als geheimnisfrei
attestiert; manche alte Fehlerpfade protokollieren weiterhin eigene Meldungen.

## Kurzes Betreiber-Runbook

1. Bei einem kontrollierten nativen 5xx Status, Zeitpunkt und `X-Request-ID`
   aus der tatsächlichen Netzwerkantwort privat erfassen. Eine Proxy-/CDN-
   Fehlerseite muss diese App-ID nicht besitzen. Keine Tokens, Bodies,
   vollständigen URLs oder Personaldaten in Support/GitHub kopieren.
2. Nur die formatgültige 32-stellige kleine Hex-ID im vorhandenen privaten
   PHP-/stderr-/Containerlog des betroffenen Prozesses suchen. Kein öffentliches
   Logdownload, `phpinfo`, neues Logfile, Loghost oder externer Dienst nötig.
3. Gleiche IDs verbinden Antwort und feste Fehlersumme; der Eintrag belegt
   einen Anfragefehler, **nicht** Datenverlust oder einen fehlgeschlagenen
   COMMIT. Bei Antwortverlust einer Buchung den bestehenden Pending-/Event-ID-
   Wiederholungsvertrag beachten, niemals anhand dieser ID neu buchen.
4. Kein Logtreffer kann fehlende Zustellung/Rotation oder einen anderen Prozess
   bedeuten; daraus keinen gesunden Zustand ableiten. Ein fehlender Header
   beweist weder Erfolg noch ausbleibende Verarbeitung.
5. Betrieb muss Erfassung, Zugriff, Aufbewahrung, Rotation und Zuständigkeit
   gesondert prüfen. Dieser Slice sendet keine Alarme und entscheidet keine
   Schwellen oder Empfänger. Backupalter, Speicher, Heartbeat, Crash-/Restore-
   und vollständige Gesundheitszustände bleiben offene #66-Gates.

## Nachweisgrenzen

Ein grober isolierter HTTP-Transportjourney unter PHP8.5.12 `cli-server`
vergleicht ausschließlich diese Definitionsdatei mit Main in einer anonymen
Loopback-Fixture. JSON-/Text503 und JSON409 behalten Status, fertige Bodybytes
und bestehende Content-Type-/Cache-Control-/Retry-After-Header. Nur der
zusätzliche 5xx-Header und sein festes Log sind neu; das variable HTTP-Datum
wird nicht auf Bytegleichheit geprüft. Servergewählte IDs sind pro Antwort neu,
ignorieren die manipulierte Client-ID und stimmen mit der festen Logzeile
überein. 409 und bereits gesendete Header erhalten keine neue Korrelation.

Ein eigener ephemerer PHP-Unterprozess ohne verfügbare `error_log`-Funktion
belegt den abgefangenen Loggerfehler: 503, Body, vorherige Header und ID bleiben.
Das ist eine gezielte fehlende-Funktion-Simulation, keine Abnahme eines realen
Loghosts oder sämtlicher Logausfallarten. Der erste Versuch, den Logtransport
mit einem Verzeichnispfad ausfallen zu lassen, schlug als Fehlerfixture fehl:
PHP wich auf stderr aus. Daher wurde kein falscher Ausfallpass aus diesem
Versuch abgeleitet; der Anwendungscode blieb dabei unverändert.

Der unveränderte vorhandene Grobnachweis `tests/api_error_response.php` besteht
zusätzlich weiterhin einschließlich seiner exakten CLI-STDERR-/Status- und
API-/Terminal-Transportgrenzen. Keine vorhandene Assertion wurde dafür
gelockert oder umgeschrieben. Die Prüfung von `headers_sent()` liegt innerhalb
des Best-effort-Schutzes.

Alle Fixtureprozesse sind beendet. Hinzu kommen notwendige PHP-Syntax,
vollständige Quellerhaltung und unabhängiges Source-Review. Keine tatsächliche
Native-App-/Proxy-/Browser-/Session-/Mehrworker-/Loghost-/Alarmierungsabnahme,
kein Appbootstrap, echte Daten, Konten, Dateien mit Betriebsdaten oder Secrets.
Keine Remoteaktion, neue CI, produktive Runtime-/Host-/Sicherheitskonfiguration
oder Deployment. #66 bleibt offen.

## Aktueller Reanchor

Die Funktionsdatei ist bytegleich zur bereits unabhängig CLEAR geprüften
Vorarbeit. Auf obiger aktueller Basis wurden derselbe grobe anonyme Loopback-
Transportjourney, der unveränderte vorhandene API-Fehler-Grobnachweis sowie
PHP-Syntax und Diffcheck erneut ausgeführt. Alle übrigen Basisdateien und Modi
bleiben exakt erhalten. Keine neue kleinteilige Testsuite oder Remoteaktion.
Der offene Merge-Denial von PR #239 bleibt vollständig unberührt.

## #66-Nachtrag: Anfragekennung im vorhandenen Anwesenheitshinweis

Enger Client-Quellschritt auf Main
`92a38f3dd4891d0f7037b6559e13222691da37dd`, Baum
`1d9c5cf41b33bab3eed83b3bc5e986bc85caabbe`. Bei Anwesenheits-POST- oder
Statuslesefehlern ergänzt der vorhandene Client den bisherigen Meldungstext
um `Anfragekennung: … (kein Buchungsnachweis).`, sofern der tatsächliche
Antwortstatus ganzzahlig500–599 ist und `X-Request-ID` exakt32 kleine
Hexzeichen enthält. Das ist nur eine Formatprüfung der Antwortkennung, keine
kryptografische Serverprüfung, Buchungsquittung oder Aussage über einen COMMIT.

Ausgegeben wird weiter ausschließlich über das bestehende `textContent`.
Fehlende/abweichende Headerwerte, 2xx/4xx und Netzfehler erzeugen keinen
Ersatzwert. Auch fehlgeschlagene Headerabfrage darf die ursprüngliche Meldung
nicht ersetzen. Bei unlesbarem JSON bleibt der feste bisherige Fehlertext
erhalten; nur eine zulässige5xx-Kennung kann angehängt werden. Ein bestätigter
POST mit anschließend fehlgeschlagenem Statusread behält den bisherigen
Bestätigungspräfix und zeigt nur die Kennung dieses fehlgeschlagenen Reads.

Keine Kennung wird in Pending/Sessionstorage/POST übernommen oder protokolliert.
Ereignis-ID, exakter Retrypayload, Bestätigung, Storage-/Kontobindung, Status- und
Revisionslogik bleiben unverändert.5xx/Antwortverlust behalten weiterhin den
ungeklärten Vorgang; eine Anfragekennung beweist weder fehlende noch erfolgte
Verarbeitung. Abwesenheitsformular, Tabellenclient, Login, API/Erweiterungen und
globale Fehlerdarstellung werden nicht erweitert. Es gibt keinen neuen Header,
CORS-/Expose-Schalter, Logtransport, Endpunkt, neuen Dienst oder Runtimezugang.

Die vorhandene feste Public-Kopie bleibt bytegleich; ausschließlich ihr bisheriger
FreshWeb-Hash und die zugehörige Assetdokumentation folgen den neuen Bytes.
Keine alternative Assetzulassung oder Lockerung des Validators. Der isolierte
VM-/Transportnachweis nutzt nur erfundenen DOM, Speicher und Antworten; keine
tatsächliche Server-/Proxy-/Logzustellung oder Browserabnahme wird daraus
abgeleitet. #66 bleibt für die übrigen Betriebs- und Alarmierungsgates offen.

Der grobe tatsächliche Public-Client-/Startupadapter-VMvergleich bestätigt nur
den Meldungszusatz gegenüber der Basis: simulierte gespeicherte Startwirkung
mit5xx-Antwort, Reload und exaktem Metadaten-/Ereignis-Replay einmal; bestätigtes
Ende mit eigener fehlgeschlagener Statusabfrage; unlesbares5xx-JSON und derselbe
Replay. Pending-/POST-/Receipt-/Wirkungszustände bleiben gleich. Ungültiger oder
werfender Headerzugriff, Netzwerkfehler, erfolgreiche Antwort,4xx,
Speicherverweigerung und bisheriger Abwesenheitsfallback bleiben Kontrollen ohne
Kennungszusatz. Keine Antwortkennung im gespeicherten Vorgang oder Folgerequest.
Die vorhandene reine Assetprüfung akzeptiert passende alte/neue Kopie+Pins und
verweigert beide gemischten Kombinationen. Notwendige JS-/PHP-Syntax und
Quellerhaltung sind separat geprüft; keine neue Repositorytestsuite oder CI.
