# CH-19 / #25 – nativer API-Vertrag und verbleibende Fehlergrenzen

## Geltung und Nachweisstand

Ausgangsstand dieses Laufzeitpakets:
[`7d4077c6d62edf14a8ebe17ab51946312e976542`](https://github.com/storminator89/Zeiterfassung/commit/7d4077c6d62edf14a8ebe17ab51946312e976542),
`main` am 27.10.2026, einschließlich der streng begrenzten Pagination aus #24
und des akzeptierten Endes mit Abwesenheitswarnung aus #35. Die bereits
geprüfte native Fehlerformatierung bleibt dabei inhaltlich unverändert;
gegenüber ihrem Stand `77076181a819f50ab470f0eda4b00ba03fde998c` werden nur diese
Vertragsdokumentation und `swagger.json` an den neuen Elternstand angeglichen.
PR #131 hat zuvor nur Dokumentation/Schema und die falsche Geolocation-Werbung
im README korrigiert. **Dieses Paket normalisiert die kontrollierten nativen
`abort_request`-Fehler**, auch vor abgeschlossenem Bootstrap. Es ändert weder
Erfolgsantworten noch Fehlerbedingungen, Auth, DB, UI oder Hosting.

Maßgeblich ist der aktive native Router. `api.php` aktiviert die JSON-Fehlerform
explizit vor `config.php`; `security.php` lädt den definitions-only
`lib/http_error_response.php` vor jedem kontrollierten Bootstrapabbruch.
`Accept`, andere Header, URL-/Query-/Bodydaten und der Scriptname wählen diese
Form nicht aus. Ohne das serverseitige, strikt boolesche Opt-in bleiben
`abort_request`-Status, Text-Content-Type und Nachrichtbytes unverändert.

Der unregistrierte [Framework-Kandidat](framework/api-read-compatibility.md)
liefert über seinen zentralen Antwortformatter nun auch für bisherige
Bearer-, Login-, Alias- und Löschsperren den nativen JSON-Fehlerumschlag
`{success:false,message}` samt ursprünglichem Status und unverändertem
`Retry-After`. Fehlernachrichten verwenden dieselbe UTF-8-Ersetzung; bestehende
JSON-Erfolge, Metadaten und gespeicherte End-/Replaybelege bleiben unverändert.
Guardentscheidungen und Registrierung bleiben unangetastet; der Kandidat ist
weiterhin kein veröffentlichter Endpoint. Dies ersetzt keine spätere
HTTP-/Client- oder vollständige Cutoverabnahme.
Die alten `@OA`-Kommentare in `api.php` sind teilweise überholt; `apidoc.html`
lädt `swagger.json`. Eine automatische Neugenerierung aus diesen Kommentaren
würde die dokumentierten Korrekturen nicht zuverlässig bewahren.

**Keine vollständige Abnahme von #25:** Kontrollierte native Fehler sind nun
JSON, aber ungefangene PHP-/Infrastrukturfehler werden nicht pauschal abgefangen.
Eine vollständige HTTP-/Client-/Erweiterungsabnahme bleibt offen. Dieses
Dokument behauptet weder deren Erfolg noch einen Produktiv-Rollout.

**CH-18 / #24-Nachtrag:** Die Pagination wurde auf Basis von
`d01e569593bcc969d854ebc622ef6169fd671629` bewusst strenger und begrenzt:
Default `limit=100`, Maximum `1000`, Offset `0..2147483647`; Invalidwerte
JSON-400 vor SQL. Fehlendes Limit/Offset-only sind **nicht mehr unbegrenzt**,
`meta.limit` ist die wirksame Ganzzahl. Details, Clientumstellung und getrennte
Nachweisgrenzen: [API-Paginierung](CH18-API-PAGINATION.md). Die obige Beschreibung
des ursprünglichen reinen Dokumentationspakets ist historisch; dieser Nachtrag
betrifft auch nativen und inaktiven Kandidat-Lesecode. Keine allgemeine
Fehlernormalisierung im damaligen #24-Paket, kein Auth-/Writer-/Produktiv-Cutover.

## Routen und Erfolgsformen des aktuellen Bestands

Alle Pfade beginnen exakt mit `/api.php`; kein beworbener Subpfad-/Slashalias.
Außer Login und OPTIONS ist `Authorization: Bearer <token>` erforderlich.
Login akzeptiert lokale Konten; kein LDAP-Login über diesen Endpunkt. Das Token
läuft nach einer Stunde ab; Passwortänderung oder Kontolöschung widerrufen es.
Auch Administratoren lesen hier nur das eigene Konto, keine Mitarbeiterliste.

| Methode/Pfad | Eingabe und Erfolg (HTTP 200) |
|---|---|
| `POST /login` | JSON-Zeichenketten `username`, `password`; `{success:true,token}`. |
| `GET /attendance` | Nur eigener Status: `{success:true,state,record_id,record_id_string}`. Kein Event, Zeitstempel oder Schreibvorgang. |
| `POST /attendance` | `action`: `start`, `pause_start`, `pause_end`, `end`; `event_id` erforderlich; optional `confirm_end_pause` (nur echtes JSON-`true` bestätigt) und kanonische Stringbindung `expected_record`. Liefert die Buchungsquittung unten. |
| `POST /workentry` | Legacy-Startalias mit JSON-Objekt; `event_id` dringend mitsenden. Kein historischer Writer. Nichtleere `endzeit`/`pause` werden verweigert; `startzeit`, Beschreibung und Standort bestimmen die Buchung nicht. |
| `POST /setendzeit` | Legacy-Endalias, `id` muss zur eigenen offenen Buchung passen; `event_id` und `confirm_end_pause` mitsenden. Antwort ist dieselbe Quittung mit **`record_id`**, nicht `id`. |
| `GET /users` | `{success:true,data:[{id,username,email,role}]}` für das eigene Konto. Normalerweise genau ein Element; bei Kontolöschung zwischen Prüfung und Lesen kann die Liste leer sein. |
| `GET /timeentries` | Eigene rohe Berichtszeilen und `meta`; siehe Filtergrenzen unten. |
| `DELETE /timeentry/{numericId}` | **Kein Erfolgspfad:** authentifizierte JSON-409-Sperre, auch ohne Datensatzabfrage. Keine direkte Löschung und keine implizite API zum Einreichen eines Korrekturantrags. |

`OPTIONS` auf beliebigem Pfad endet vor Bootstrap/Auth/DB mit HTTP 200 ohne
Body; die bestehende CORS-Methodenliste ist GET, POST, OPTIONS, DELETE.
Unbekannte GET-/POST-/DELETE-Pfade liefern nach erfolgreichem Bootstrap JSON-400
`Invalid endpoint`; andere Methoden (auch HEAD) JSON-405 `Method Not Allowed`.
Das sind keine neuen 404-/422-Verträge.

### Status ist nicht Quittung

Aktueller Status, ohne offene Buchung:

```json
{"success":true,"state":"absent","record_id":null,"record_id_string":null}
```

`working`/`paused` haben eine positive ganzzahlige aktive `record_id`.
Eine alte offene Berichtszeile ohne `work_sessions` kann `working` ergeben;
der GET-Leser diagnostiziert mehrere offene Altzeilen nicht. Eine anschließende
Mutation kann deren Bereinigung über Korrekturanträge verlangen.

Beispiel einer bestätigten Startbuchung:

```json
{
  "success": true,
  "message": "Buchung bestätigt.",
  "time": "2026-10-27T08:00:00+00:00",
  "local_time": "2026-10-27T09:00:00+01:00",
  "event_id": "example-start-000001",
  "record_id": 42,
  "record_id_string": "42",
  "state": "working"
}
```

`time` ist Server-UTC, `local_time` derselbe Zeitpunkt in `Europe/Berlin`
mit Offset. Nach `end` ist der Quittungsstatus `absent`, aber die
**betroffene** `record_id` bleibt positiv; erst ein neuer GET liefert `null`.
Kein `data.id` in Buchungsquittungen. Bei unterschrittener konfigurierter
Mindestpause kann `pause_warning` hinzukommen; es wird keine fiktive Pause
abgezogen. Erfasst werden tatsächliche Pausen. Der Startalias setzt aktuell
leere Beschreibung und `office`, nicht frei übermittelte Clientfelder.

### Optionaler exakter Record-String und neue Clientbindung (#36)

Nachtrag auf Main `f69fee32c4df9d7be7e24185d1fdc1720103b2c0`: GET-Status und
alle drei Stempelerfolgstransporte ergänzen `record_id_string` aus demselben
vorhandenen Status-/Quittungsarray. Der numerische `record_id` bleibt unverändert;
Status `absent` hat beide null, eine Endquittung dagegen weiterhin die positive
**betroffene** ID, nun zusätzlich als String. Auch alte gespeicherte Quittungen
werden nur in der Antwort projiziert: keine Änderung gespeicherter JSONbytes,
Hashes, Replayentscheidung, Ereignisanzahl oder zweite aktuelle Statusabfrage.

POST `/attendance` akzeptiert optional `expected_record` als kanonischen positiven
ASCII-Dezimalstring (`[1-9][0-9]*`) innerhalb `PHP_INT_MAX`, vor dem Cast geprüft.
Ein vorhandenes ungültiges Feld ergibt JSON-400; fehlend bleibt null, ohne neue
Pflicht für ältere Clients. Das bestehende Record-Mismatch ergibt409 im selben
Writer; bereits committed gleiche Payloads werden weiterhin zuerst wiederholt.
API-/Terminal-`requireExpectedRecord` bleibt false; die strengere Webpflicht
wird nicht übertragen. `/setendzeit` behält seine bestehende Integerkoerzierung
von `id`; der neue Client kann dafür den zuvor gelieferten exakten String senden.

Die vorhandenen identischen Edge-/Firefoxcontroller verwenden bei neuem Server
nur für **neue** Pausen-/Fortsetzen-Pendingevents `expected_record`, und für neue
Endevents die String-`id` auf dem unveränderten Endalias. Start bleibt ungebunden.
Die Bindung kommt aus dem frischen eigenen Status, nie aus neuester Berichtszeile,
Kontoauswahl oder einer historischen Replayantwort. Ereignis-ID, Endpoint,
Bodytypen und gespeicherte alte Pendingpayloads werden beim Retry nicht geändert.
Matching-Origin/Konto-Pendingobjekte mit fehlerhafter neuer Bindung/String-ID
bleiben gespeichert und sperren Aktionen/Retry; keine stille Löschung oder Reparatur.
Die bisherige Bereinigung anderer ungültiger oder fremder Legacyzustände bleibt.
Erfolgsquittungen bestätigen nur das Event; der nächste andere Schritt bleibt
bis zum folgenden frischen GET gesperrt. Definitiver Konflikt lädt Status neu,
keine automatische Aktion auf der anderen Sitzung.

Fehlt das Stringfeld auf einem älteren Server, bleibt der bisherige sichere
numerische Legacy-Modus (nur `Number.isSafeInteger`), inklusive numerischer
End-`id` und ungebundener Pausen. Die bestehende Statuszeile benennt diese Grenze.
Ein **vorhandenes** fehlerhaftes Stringfeld wird nicht numerisch repariert.
Keine Rundung/Floatkonvertierung der neuen Bindung und keine neue Geschäftsgrenze;
der String ist durch das vorhandene SQLite/PHP-Integerformat begrenzt.

**Keine falsche #57-Erledigung:** Alte ungebundene Clients oder Pendingevents
bleiben beim optionalen alten Vertrag. Ein nicht nachweislich committed alter
Payload erhält nicht nachträglich die heutige ID; seine sichere kanalübergreifende
Klärung ist hier nicht implementiert. Logout/Ziel-/Kontowechsel und deren bisherige
Speichergrenzen bleiben unverändert, keine Offline-Queue oder neue Recoveryroute.
Server-zuerst-Quellkoordination ist empfohlen; reale Erweiterungsinstallation,
CORS-/HTTP-/Token-/Betriebsabnahme und ein gemischter Versionsrollback bleiben offen.
Nur grobe erfundene Speicher-/VM-Verträge und Syntax, keine tatsächlichen Requests,
Konten, Keys, Browser-/Listener-/Deployaktion oder vollständige #36/#77-Abnahme.

### Akzeptiertes Ende mit Abwesenheitswarnung ist kein 409-Fehler

Nach #35 wird das tatsächliche `end` auch dann gespeichert, wenn das beendete
Arbeitsintervall eine ganztägige Abwesenheit überschneidet. Die normale
Endquittung bleibt **HTTP 200, `success:true`, `state:"absent"`** mit der
positiven betroffenen `record_id`. Nur wenn solche Überschneidungen gefunden
werden, ergänzt der gemeinsame Writer:

- `overlap_record_ids`: Array der eindeutigen ganzzahligen IDs der betroffenen
  **Abwesenheits-Berichtszeilen**, nicht eine neue Arbeits-/Ereignis-ID.
- `overlap_warning`: Zeichenkette mit dem Klärungshinweis. Sie wird an die
  bestehende `message` angehängt, gegebenenfalls nach `pause_warning`.

Beispiel ohne zusätzliche Pausenwarnung:

```json
{
  "success": true,
  "message": "Buchung bestätigt. Arbeitsende gespeichert. Arbeitszeit überschneidet sich mit einer ganztägigen Abwesenheit. Betroffene Summen sind bis zur manuellen Klärung nicht abschließend. Bitte Korrekturanträge prüfen.",
  "time": "2026-10-27T05:00:00+00:00",
  "local_time": "2026-10-27T06:00:00+01:00",
  "event_id": "example-end-overlap-0001",
  "record_id": 42,
  "record_id_string": "42",
  "state": "absent",
  "overlap_record_ids": [43],
  "overlap_warning": "Arbeitsende gespeichert. Arbeitszeit überschneidet sich mit einer ganztägigen Abwesenheit. Betroffene Summen sind bis zur manuellen Klärung nicht abschließend. Bitte Korrekturanträge prüfen."
}
```

Dies gilt für `POST /attendance` mit `action:"end"` und den vorhandenen
`POST /setendzeit`-Endalias auf demselben Writer. Ohne gefundenen Konflikt fehlen
diese beiden optionalen Felder; sie sind keine neuen Pflichtfelder. Eine
Warnung macht weder die Buchung unbestätigt noch Summen abschließend: Korrekturanträge klären den
Sachverhalt, ohne hier Arbeit automatisch zu kürzen oder Abwesenheit zu löschen.

Echte Verweigerungen bleiben dagegen JSON-409 mit `success:false`, etwa
Start-/Zustandskonflikt, andere aktive Datensatz-ID, abweichender Inhalt bei
gleicher Ereignis-ID oder fehlende Bestätigung beim Ende einer aktiven Pause.
Die akzeptierte Endüberschneidung ist **kein** solcher Fehler. Die gespeicherte
Endwarnung bleibt bei identischer Ereigniswiederholung unverändert, auch nach
einer späteren Klärung; sie ist historischer Quittungsinhalt, keine aktuelle
Konfliktdiagnose. Details und Grenzen: [Nachtschicht und Abwesenheit](CH29-NIGHT-ABSENCE.md).

### Wiederholung und bestehender Erweiterungsclient

`event_id` hat 16–80 Zeichen aus `A-Z`, `a-z`, `0-9`, `_`, `-`.
Nach unbestätigter Antwort dieselbe ID mit derselben Aktion/Bestätigung und
gegebenenfalls erwarteten Datensatz-ID wiederholen, nicht eine neue Buchung
raten. Die Speicherung ist nach `api:{user_id}` und Ereignis-ID abgegrenzt;
der Inhaltshash umfasst Benutzer, Aktion, Bestätigung und erwarteten Datensatz.
Anderer gehashter Inhalt mit derselben ID führt zu JSON-409. Ignorierte
Zusatzfelder sind keine neue Idempotenzdimension. Eine gespeicherte Quittung
kann nach weiteren Aktionen einen **älteren** Status enthalten: danach GET lesen.

`/attendance` verlangt die Ereignis-ID; optionales `expected_record` bindet die Aktion an die zuvor gelesene aktive Record-ID. Ein zusätzliches `id` bleibt ignoriert.
`/setendzeit` bindet `end` an die übermittelte, integer-koerzierte `id`;
fehlend/null wird `0` und führt zum Datensatzkonflikt. Bei den beiden Aliasen
wird für fehlende/null `event_id` zufällig eine erzeugt – keine verlässliche
Wiederholung ohne vom Client gespeicherte ID. Ein aktives Pausenende zusammen
mit Gehen braucht `confirm_end_pause:true`, sonst 409.

Die vorhandenen `firefox_add_on/popup.js` und `time-tracker-extension_edge/popup.js` nutzen
Login → eigene `/users` → GET `/attendance`, sowie `/timeentries?limit=1`
nur zur Anzeige. Start/Pausen laufen über POST `/attendance`, Ende über
`/setendzeit` mit der Status-ID. Sie verlangen passende `event_id` und positive
`record_id`, speichern unbestätigte Vorgänge und lesen nach Bestätigung oder
4xx-Verweigerung neu. Nicht-JSON kann weiter den generischen Parserfehler
„Keine gültige Serverantwort erhalten.“ auslösen; Bearer-401 behandelt der
Client vor dem JSON-Parsing. Dies ist Quellenabgleich, **keine** Firefox-/Edge-,
Store-, Netzwerk- oder Browserabnahme.

## Fehler: kontrollierte native JSON-Antworten und verbleibende Grenzen

### Kanonische Form für kontrollierte native API-Fehler

Die bestehenden JSON-Handler und nun auch `abort_request` im nativen API-Pfad
behalten ihren jeweiligen HTTP-Status bei und verwenden
`Content-Type: application/json; charset=UTF-8` mit dieser bestehenden Form:

```json
{"success":false,"message":"Invalid or expired token."}
```

Keine neuen Fehlercodes, kein versteckter 200-Fehler und keine automatische
Umdeutung aller 409/503 in 400/500. `message` ist Anzeigetext, kein stabiler
maschinenlesbarer Fehlercode. Bestehende Texte sind deutsch/englisch gemischt.
Der neue Formatter ersetzt nur ungültige UTF-8-Bytes der Fehlermeldung durch
U+FFFD, damit auch diese Meldung als JSON kodiert werden kann; gültiger Text
bleibt inhaltlich erhalten. Er ergänzt keine Diagnostik oder Exceptiondetails.

Die Loginbudgetprüfung setzt weiterhin `Retry-After: 600` **vor** dem Abbruch.
CORS-Header und Reihenfolge bleiben unverändert. `OPTIONS` beendet die Anfrage
weiter vor JSON-Opt-in, Bootstrap und Auth mit HTTP 200 und leerem Body.
Weder Validierung, Berechtigungen, JWT-/Session-/CSRF-/Schlüssellogik noch
Zähler, Attendance-Quittungen und Erfolgsdaten werden verändert.

### Tatsächlich aus dem Quelltext ableitbare Antworten

| Pfad/Situation | Aktueller Status und Body |
|---|---|
| Login, fehlende/nicht-string Credentials | 400 JSON `{success:false,message:"Username and password required."}` |
| Login, ungültige Credentials | 401 JSON `{success:false,message:"Invalid credentials"}` |
| Geschützte Route, Bearer fehlt/ungültig/abgelaufen/Konto widerrufen | 401 JSON aus `abort_request`, z. B. `{success:false,message:"Invalid or expired token."}` |
| Loginbudget erschöpft | 429 JSON, `message:"Too many login attempts. Try again later."`, `Retry-After: 600` |
| Attendance-Handler, ungültige Ereignis-ID/Aktion | 400 JSON; typfalsche/malformed Eingaben werden nicht universell auf 400 normalisiert. |
| Attendance-Transition, Konto inzwischen nicht verfügbar | 403 JSON |
| Attendance-Transition, tatsächlich verweigerter Start-/Zustands-/Datensatz-/Bestätigungs-/ID-Konflikt | 409 JSON; akzeptiertes Ende mit Abwesenheitswarnung bleibt dagegen 200 und `success:true`. |
| `/workentry`, kein JSON-Array/-Objekt bzw. nichtleere historische Felder | 400 bzw. 409 JSON; `{}` ist eine gültige Alias-Eingabe. |
| DELETE mit numerischem ID-Pfad nach Auth | 409 JSON, `message:"Use an approved correction request to cancel a record."` |
| `/timeentries`, ungültiger Paginationtyp/-syntax/-bereich | 400 JSON vor SQL, z. B. `Invalid limit. Use an integer from 0 to 1000.` |
| `/timeentries`, falsches skalares Datumsformat | 400 JSON; nur Formprüfung, keine vollständige Kalenderprüfung. |
| `/users` oder `/timeentries`, gefangene `Exception` | 500 JSON, `Internal server error` |
| GET/POST Attendance und Aliase, von `attendance_json` gefangener unerwarteter Fehler | 503 JSON, `Buchung nicht bestätigt. Verbindung prüfen und denselben Vorgang erneut versuchen.` |
| Gemeinsamer Bootstrap, Speicher-/DB-Verweigerung | 503 JSON, z. B. `{success:false,message:"Database unavailable."}`; gilt auch vor dem Handler. |

Die kontrollierte Bootstrap-Sessionwiderrufung (`Please sign in again.`, 401)
verwendet im nativen API-Pfad ebenfalls diese JSON-Form. Nicht jede
PHP-/Infrastrukturstörung läuft durch `abort_request` oder einen bestehenden
Handler: eine nichtskalare Datumsquery kann weiterhin einen ungefangenen
`TypeError` auslösen. Es gibt **keinen** neuen globalen Exception-/Shutdown-
oder Warninghandler. Body und Deploymentdarstellung ungefangener Fehler sind
nicht garantiert; PHP-Warnungen werden nicht als Erfolg umgedeutet und es gibt
keine erfundene generelle JSON-500- oder „alle Invaliddaten sind 400“-Abnahme.

## Zeitfilter und Pagination: aktuelle Grenzen bewahren

`GET /timeentries` wählt eigene `zeiterfassung`-Zeilen mit `SELECT *`, sortiert
nach gespeichertem `startzeit DESC` (keine neue Instant-Sortierung). `data[].id`
ist weiterhin die Berichtszeilen-ID; `record_id` ersetzt dieses Legacyfeld
**nicht**. Timestamps werden nicht normalisiert: alte Strings können ohne
Offset vorliegen, `endzeit` kann `null` sein. Nullable Altwerte für Pause,
Beschreibung und Standort sind zulässig. Zusätzliche DB-Spalten werden nicht
herausgefiltert; numerische Lesewerte werden nicht explizit gecastet.

Am genannten Commit beziehen sich die inklusiven Grenzen `start_date`/`end_date`
auf den **Berliner Berichts-Starttag** aus `chrono_api_booking_day(startzeit)`.
Der Adapter delegiert an `report_day`: offsetfreie Altwerte werden als
`Europe/Berlin` interpretiert, Offset-/Z-Werte als Instant nach Berlin
umgerechnet. Zähl- und Seitenabfrage verwenden denselben Ausdruck vor
LIMIT/OFFSET; Nachtarbeit bleibt dem Berliner Starttag zugeordnet und wird
nicht auf Kalendertage aufgeteilt. Keine festen Stunden-SQLmodifikatoren.

Die in #9 geprüfte Korrektur (`6240cac9e797dd9bdca0ee20ec0ea2ea15f50ab0`)
ist nach Merge von PR #125 im hier genannten Quellenstand
`1b526453439f1ff6973131f0b2ebb8f40466a74b` enthalten; die alte
SQLite-`date(startzeit)`-UTC-Grenze ist damit nicht mehr der aktuelle
API-Vertrag. Leere/NULL-/vom bestehenden Parser unlesbare Startwerte ergeben
bei Datumsfilterung SQL-NULL und werden ausgeschlossen; ungefiltert bleiben
rohe Zeilen erhalten. Keine Datenreparatur oder erweiterte Parserzusage.
Geprüft wird bei Querydaten weiterhin nur `YYYY-MM-DD` per Regex, nicht
Kalendergültigkeit, Reihenfolge oder Intervallüberlappung.

`limit` und `offset` werden nun durch denselben strengen Parser in nativer
API und inaktivem Framework-Kandidaten geprüft. `limit`: Standard **100**,
Bereich **0..1000**; `offset`: effektiv **0**, Bereich **0..2147483647**.
Nur ASCII-Ziffernstrings (führende Nullen zulässig) oder interne echte Integer;
Arrays, null/bool/float, Vorzeichen, Leer-/Whitespace-, Dezimal-/Exponenten-
und Überlaufwerte werden **vor SQL** als JSON-400 verweigert. Keine implizite
PHP-Zahlkoerzierung oder stille Begrenzung. `limit=0` liefert eine leere Seite.

**Bewusste Kompatibilitätsänderung:** fehlendes Limit und Offset allein sind
nicht mehr unbeschränkt; `meta.limit` meldet die wirksame **100**, nicht `null`.
Fehlender Offset steht weiter als `null` in `meta`, wirksam werden null Zeilen
übersprungen. `meta.total` zählt eigene date-gefilterte Treffer vor Pagination,
`filtered` nur zurückgegebene Zeilen. Die bestehenden Popup-Clients senden
explizit `limit=1`; Clients mit bisher implizit vollständigem Abruf müssen
[Seiten iterieren](CH18-API-PAGINATION.md#absichtliche-kompatibilitätsänderung-und-clientumstellung).
Der gemergte `LIMIT -1`-Fallback bleibt defensiv im Builder erhalten, wird aber
von normal validierten HTTP-Querys nicht mehr benötigt.

## Geolocation und ausdrücklich nicht angebotene Funktionen

Der aktive Router hat **keinen Geolocation-Webhook** und kein automatisches
Kommen/Gehen beim Betreten/Verlassen eines Ortes. Standortauswahl im bestehenden
Bericht ist davon verschieden. Der alte README-Werbesatz wird deshalb ersetzt,
nicht durch eine neue öffentliche Route erfüllt. Keine Personenwahl,
Clientzeit-/Historienmutation oder Frameworkaktivierung wird hier ergänzt.

## Tatsächlich durchgeführte Prüfung und offene Abnahme

Historischer Laufzeitnachweis des unveränderten Codepakets
`77076181a819f50ab470f0eda4b00ba03fde998c` auf Ausgangsstand
`15d8ea5ffacc38596397202fd85c77a51cdc0f37`: PHP **8.5.12 CLI**,
Lint der geänderten PHP-Dateien und ein grober, bootstrapfreier Subprozesslauf
(`php tests/api_error_response.php`) mit zwei Gruppen:

- Nicht-API-Textbytes, Status und Terminierung bleiben erhalten; Requestheader,
  Querydaten und Scriptname aktivieren JSON nicht. Nur echtes boolesches
  serverseitiges `true` aktiviert den Formatter.
- Repräsentative kontrollierte 400/401/403/409/429/503 bleiben Nicht-Erfolg mit
  derselben Meldung im JSON-Envelope und demselben Status; gültige Sonderzeichen
  und ungültige UTF-8-Bytes bleiben JSON-kodierbar. Der Abbruch kehrt nicht zurück.

Der obige PHP-Nachweis gehört zum unveränderten ursprünglichen Codepaket
`77076181a819f50ab470f0eda4b00ba03fde998c`; er wurde für diese reine
Dokumentationsangleichung nicht erneut ausgeführt. Am neuen Elternstand wurde
nur der bestehende grobe statische Quellen-/Schemaabgleich durchgeführt:
JSON parsebar, lokale `$ref`-Ziele und Beispiele konsistent, Opt-in vor Bootstrap,
unveränderte CORS-/OPTIONS- und Retry-After-Quellen, identische App-/Helper-/
Testbytes zum geprüften Codepaket und alle nicht freigegebenen Pfade byte-
identisch zum neuen Ausgangsstand. Die optionalen Endwarnfelder und der
200-vs.-409-Unterschied sind aus dem tatsächlichen gemeinsamen Writer und
seinem Konfliktleser abgeleitet, nicht neu zur Laufzeit abgenommen.
Exakte Commit-/Baum-/Patchidentitäten, Werkzeugversionen und Protokolle stehen
im eingefrorenen Übergabepaket. Dies ist **keine** vollständige OpenAPI- oder
Laufzeitabnahme. Insbesondere beweist PHP CLI keine tatsächlichen HTTP-Header.

Nicht ausgeführt: HTTP-/Header-/CORS-/Retry-After-Funktionstest, tatsächliche
Login-/Tokenausgabe oder -prüfung, `config.php`-/`security.php`-Bootstrap,
Session-/Schlüssel-/Datei-DB-/Serverzugriff, Browser/Erweiterung, produktive
Zugangsdaten, Deployment oder ein manuell ausgelöster GitHub-CI-Neulauf.
Vor einer abschließenden Abnahme bleiben unabhängiger integrierter Review,
Abgleich mit dann aktuellem `main` sowie die vollständige autorisierte
HTTP-/Clientabnahme erforderlich. #25 wird durch den begrenzten CLI-/Quellen-
Nachweis allein **nicht geschlossen**.
