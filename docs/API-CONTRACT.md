# CH-19 / #25 – nativer API-Vertrag und verbleibende Fehlergrenzen

## Geltung und Nachweisstand

Quellenstand: [`1b526453439f1ff6973131f0b2ebb8f40466a74b`](https://github.com/storminator89/Zeiterfassung/commit/1b526453439f1ff6973131f0b2ebb8f40466a74b),
`main` am 27.10.2026. Dieses Paket korrigiert **nur Dokumentation und Schema**:
`swagger.json`, diese Datei und die falsche Geolocation-Werbung im README.
Es verändert weder `api.php`/Security/Framework noch UI, Auth, DB oder Hosting.

Maßgeblich ist der aktive native Router, nicht ein unregistrierter
[Framework-Kandidat](framework/api-read-compatibility.md). Die alten
`@OA`-Kommentare in `api.php` sind teilweise überholt; `apidoc.html` lädt
`swagger.json`. Eine spätere automatische Neugenerierung aus diesen Kommentaren
würde die Korrekturen nicht zuverlässig bewahren.

**Keine vollständige Erledigung von #25:** JSON-Fehler werden unten als Ziel
festgelegt, sind aber im aktuellen Router **nicht durchgängig implementiert**.
Der Text/JSON-Vertragsbruch bleibt eine gesonderte Laufzeitkorrektur. Dieses
Dokument ist weder eine Abnahme noch eine Aussage über einen Produktiv-Rollout.

**CH-18 / #24-Nachtrag:** Die Pagination wurde auf Basis von
`d01e569593bcc969d854ebc622ef6169fd671629` bewusst strenger und begrenzt:
Default `limit=100`, Maximum `1000`, Offset `0..2147483647`; Invalidwerte
JSON-400 vor SQL. Fehlendes Limit/Offset-only sind **nicht mehr unbegrenzt**,
`meta.limit` ist die wirksame Ganzzahl. Details, Clientumstellung und getrennte
Nachweisgrenzen: [API-Paginierung](CH18-API-PAGINATION.md). Die obige Beschreibung
des ursprünglichen reinen Dokumentationspakets ist historisch; dieser Nachtrag
betrifft auch nativen und inaktiven Kandidat-Lesecode. Keine allgemeine
Fehlernormalisierung aus #25, kein Auth-/Writer-/Produktiv-Cutover.

## Routen und Erfolgsformen des aktuellen Bestands

Alle Pfade beginnen exakt mit `/api.php`; kein beworbener Subpfad-/Slashalias.
Außer Login und OPTIONS ist `Authorization: Bearer <token>` erforderlich.
Login akzeptiert lokale Konten; kein LDAP-Login über diesen Endpunkt. Das Token
läuft nach einer Stunde ab; Passwortänderung oder Kontolöschung widerrufen es.
Auch Administratoren lesen hier nur das eigene Konto, keine Mitarbeiterliste.

| Methode/Pfad | Eingabe und Erfolg (HTTP 200) |
|---|---|
| `POST /login` | JSON-Zeichenketten `username`, `password`; `{success:true,token}`. |
| `GET /attendance` | Nur eigener Status: `{success:true,state,record_id}`. Kein Event, Zeitstempel oder Schreibvorgang. |
| `POST /attendance` | `action`: `start`, `pause_start`, `pause_end`, `end`; `event_id` erforderlich; optional `confirm_end_pause` (nur echtes JSON-`true` bestätigt). Liefert die Buchungsquittung unten. |
| `POST /workentry` | Legacy-Startalias mit JSON-Objekt; `event_id` dringend mitsenden. Kein historischer Writer. Nichtleere `endzeit`/`pause` werden verweigert; `startzeit`, Beschreibung und Standort bestimmen die Buchung nicht. |
| `POST /setendzeit` | Legacy-Endalias, `id` muss zur eigenen offenen Buchung passen; `event_id` und `confirm_end_pause` mitsenden. Antwort ist dieselbe Quittung mit **`record_id`**, nicht `id`. |
| `GET /users` | `{success:true,data:[{id,username,email,role}]}` für das eigene Konto. Normalerweise genau ein Element; bei Kontolöschung zwischen Prüfung und Lesen kann die Liste leer sein. |
| `GET /timeentries` | Eigene rohe Berichtszeilen und `meta`; siehe Filtergrenzen unten. |
| `DELETE /timeentry/{numericId}` | **Kein Erfolgspfad:** authentifizierte Text-409-Sperre, auch ohne Datensatzabfrage. Keine direkte Löschung und keine implizite API zum Einreichen eines Korrekturantrags. |

`OPTIONS` auf beliebigem Pfad endet vor Bootstrap/Auth/DB mit HTTP 200 ohne
Body; die bestehende CORS-Methodenliste ist GET, POST, OPTIONS, DELETE.
Unbekannte GET-/POST-/DELETE-Pfade liefern nach erfolgreichem Bootstrap JSON-400
`Invalid endpoint`; andere Methoden (auch HEAD) JSON-405 `Method Not Allowed`.
Das sind keine neuen 404-/422-Verträge.

### Status ist nicht Quittung

Aktueller Status, ohne offene Buchung:

```json
{"success":true,"state":"absent","record_id":null}
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

### Wiederholung und bestehender Erweiterungsclient

`event_id` hat 16–80 Zeichen aus `A-Z`, `a-z`, `0-9`, `_`, `-`.
Nach unbestätigter Antwort dieselbe ID mit derselben Aktion/Bestätigung und
gegebenenfalls erwarteten Datensatz-ID wiederholen, nicht eine neue Buchung
raten. Die Speicherung ist nach `api:{user_id}` und Ereignis-ID abgegrenzt;
der Inhaltshash umfasst Benutzer, Aktion, Bestätigung und erwarteten Datensatz.
Anderer gehashter Inhalt mit derselben ID führt zu JSON-409. Ignorierte
Zusatzfelder sind keine neue Idempotenzdimension. Eine gespeicherte Quittung
kann nach weiteren Aktionen einen **älteren** Status enthalten: danach GET lesen.

`/attendance` verlangt die ID und ignoriert ein zusätzliches `id`.
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

## Fehler: heutiger Drahtvertrag und Ziel strikt getrennt

### Festgelegtes JSON-Ziel (noch nicht überall umgesetzt)

Für API-Fehler soll der vorhandene jeweilige HTTP-Status erhalten bleiben und
`Content-Type: application/json; charset=UTF-8` mit dieser bestehenden Form
verwendet werden:

```json
{"success":false,"message":"Invalid or expired token."}
```

Keine neuen Fehlercodes, kein versteckter 200-Fehler und keine automatische
Umdeutung aller 409/503 in 400/500. `message` ist Anzeigetext, kein stabiler
maschinenlesbarer Fehlercode. Bestehende Texte sind deutsch/englisch gemischt.
Die Normalisierung der unten genannten Textpfade sowie ungefangener Fehler
muss separat implementiert und überprüft werden; hier bleibt sie offen.

### Tatsächlich aus dem Quelltext ableitbare Antworten

| Pfad/Situation | Aktueller Status und Body |
|---|---|
| Login, fehlende/nicht-string Credentials | 400 **text/plain**, `Username and password required.` |
| Login, ungültige Credentials | 401 JSON `{success:false,message:"Invalid credentials"}` |
| Geschützte Route, Bearer fehlt/ungültig/abgelaufen/Konto widerrufen | 401 **text/plain** aus `abort_request`, z. B. `Invalid or expired token.` |
| Loginbudget erschöpft | 429 **text/plain**, `Too many login attempts. Try again later.`, `Retry-After: 600` |
| Attendance-Handler, ungültige Ereignis-ID/Aktion | 400 JSON; typfalsche/malformed Eingaben werden nicht universell auf 400 normalisiert. |
| Attendance-Transition, Konto inzwischen nicht verfügbar | 403 JSON |
| Attendance-Transition, Zustand/Überlappung/Datensatz/Bestätigung/ID-Konflikt | 409 JSON |
| `/workentry`, kein JSON-Array/-Objekt bzw. nichtleere historische Felder | 400 bzw. 409 **text/plain**; `{}` ist eine gültige Alias-Eingabe. |
| DELETE mit numerischem ID-Pfad nach Auth | 409 **text/plain**, `Use an approved correction request to cancel a record.` |
| `/timeentries`, ungültiger Paginationtyp/-syntax/-bereich | 400 JSON vor SQL, z. B. `Invalid limit. Use an integer from 0 to 1000.` |
| `/timeentries`, falsches skalares Datumsformat | 400 JSON; nur Formprüfung, keine vollständige Kalenderprüfung. |
| `/users` oder `/timeentries`, gefangene `Exception` | 500 JSON, `Internal server error` |
| GET/POST Attendance und Aliase, von `attendance_json` gefangener unerwarteter Fehler | 503 JSON, `Buchung nicht bestätigt. Verbindung prüfen und denselben Vorgang erneut versuchen.` |
| Gemeinsamer Bootstrap, Speicher-/DB-Verweigerung | 503 **text/plain**, z. B. `Database unavailable.`; betrifft auch normalerweise JSON liefernde Routen. |

`abort_request` überschreibt den Content-Type ausdrücklich auf Text; der
JSON-Header oben in `api.php` ist also **keine** Gesamtgarantie. Nicht jede
PHP-/Infrastrukturstörung läuft durch einen der Handler: z. B. kann eine
nichtskalare Datumsquery einen ungefangenen `TypeError` auslösen. Body und
Deploymentdarstellung ungefangener Fehler sind hier nicht garantiert; keine
erfundene generelle JSON-500- oder „alle Invaliddaten sind 400“-Abnahme.

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

Nur lokale JSON-/Schema-/Quellenkonsistenz: JSON parsebar; lokale `$ref`-Ziele
auflösbar; dokumentierte Methoden/Pfade mit dem nativen Router verglichen;
Beispiele gegen ihre Schemas geprüft; Änderungen auf drei Dokumentationsdateien
begrenzt. Python-CLI, **keine Anwendungslaufzeit gestartet**. Prüfprotokoll und
exakte Werkzeugversion liegen im eingefrorenen Übergabepaket.

Nicht ausgeführt: HTTP-/Auth-/Netzwerkfunktionstest, PHP-Bootstrap,
Browser/Erweiterung, DB/Server, reale Zugangsdaten oder GitHub-CI-Neulauf.
Original-UI und Laufzeitdateien bleiben byte-identisch. Vor einer späteren
Freigabe separat nötig: unabhängiger integrierter Review, erneuter Abgleich mit
dann aktuellem `main`, autorisierte Veröffentlichung sowie eigenständige Umsetzung
und Abnahme der JSON-Fehlernormalisierung. Der Quellenabgleich nach #9-Merge ist
für den oben genannten Commit durchgeführt und im Übergabepaket belegt; dies
ist keine Laufzeitabnahme. #25 wird durch diese Dokumentation allein
**nicht geschlossen**.
