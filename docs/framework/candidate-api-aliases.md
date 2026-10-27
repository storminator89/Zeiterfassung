# FW-06 / #77 – inaktive vorhandene APIaliase

Der weiterhin unregistrierte Kandidat ergänzt die vorhandenen POST-Pfade
`/api.php/workentry` und `/api.php/setendzeit`. Keine neue RESTressource,
produktive Route, Datenbank-, Schlüssel-, Loginbudget- oder UIänderung.
`api.php` und alle produktiven Vertragshandler bleiben bytegleich.
Die [Login-/direkte APIgrenze](candidate-api-actions.md) und deren offene
Shared-Budget-/Cutovergates gelten unverändert.

## Startalias `/workentry`

- Rohes JSON muss wie bisher ein Array sein, sonst HTTP 400 mit JSON-`{success:false,message}` und `Invalid request.`.
- Die bisherigen PHP-`!empty(endzeit) || !empty(pause)`-Regeln bleiben exakt:
  historische Eingaben werden mit HTTP 409 im selben JSON-Fehlerumschlag und
  `Use a correction request for historical entries.` verweigert. Keine neue
  Datum-/Bool-/Ganzzahlvalidierung ersetzt diese bestehenden Bedingungen.
- `startzeit`, Standort, Beschreibung und andere Clientfelder werden **nicht**
  als autoritative Buchungsdaten verwendet. Die bestehende Domäne stempelt mit
  Serverzeit; der Alias erzwingt die Aktion `start`.
- Nur eine fehlende oder `null`-`event_id` erhält wie bisher
  `bin2hex(random_bytes(16))`. Ein explizit leerer/ungültiger Wert wird nicht
  still repariert, sondern im bestehenden Domänenpfad behandelt.

## Endalias `/setendzeit`

Rohes JSON wird wie im Bestand ohne zusätzliche Arraypflicht dekodiert.
Die vorhandene PHP-Koerzierung `(int)(id ?? 0)` bleibt erhalten; kein neuer
422-/Required-Vertrag. `id` ist die **erwartete aktive eigene Projektion**,
keine beliebig zu ändernde Datensatz-ID. Die durch den Token gewählte Person,
ihr aktueller Stempelzustand und der bestehende Writerlock bestimmen allein
den möglichen Übergang. Eine veraltete/fremde ID kann nicht eine andere
Zeile beenden; die bestehende 409-Prüfung bleibt unverändert im Domänendienst.

Die Aktion ist immer `end`, Bestätigung ausschließlich
`confirm_end_pause === true`. Fehlende/null-Event-ID wird wie oben erzeugt;
Client-Endzeiten und fremde Akteur-/Quellfelder werden nicht ausgewertet.
`ApiAttendance` nimmt dafür einen optionalen `expectedRecord` entgegen und
leitet ihn unverändert an denselben #75-Writer weiter. Der direkte
`/attendance`-Controller lässt diesen Parameter weiterhin weg;
[#36](https://github.com/storminator89/Zeiterfassung/issues/36) wird nicht
heimlich mitgelöst. Startalias und direkter Start bleiben beim bisherigen null.

## Gemeinsame Auth-, Replay- und Antwortgrenze

Beide Aliase müssen durch dieselbe Bearermiddleware mit aktueller
Passwortwiderrufsprüfung. `LegacyApiAttendance` bildet weiterhin ausschließlich
`api:{tokenUserId}`; kein zusätzlicher Aliasnamensraum, Webdienst, PDO,
Transaktions-/Idempotenzadapter oder fachlicher Rechenpfad. Stale-ID-Prüfung,
Audit und Replay kommen ausschließlich aus demselben vorhandenen Writer.
Der Controller verwendet für alle drei Stempelvarianten einen gemeinsamen
Wirefehler-/Erfolgsadapter, keine nachgebaute Buchungsantwort.

Erfolg bleibt die vorhandene Buchungsbestätigung mit **`record_id`**, Zustand,
Event-ID und Serverzeit, **nicht** `{data:{id:…}}`. Ein fehlerhafter Popupclient
wird nicht durch einen erfundenen Serververtrag kompensiert. Die
Erweiterungskorrekturen #22/#23/#25 bleiben gesonderte Arbeiten; reale
Browserstore-/Verteilungsfunktion ist hier nicht behauptet.

**Bestehende Retryschwäche:** Ohne clientseitig stabile Event-ID erzeugt jeder
Aliasrequest eine neue ID. Ein Netzretry ist dadurch nicht sicher als dasselbe
Ereignis erkennbar. Das wird bewahrt und dokumentiert, nicht als behoben
verkauft. Mit vorhandener stabiler ID gelten dieselben gespeicherten
Replay-/Payloadkonfliktregeln wie im bisherigen `api:`-Namensraum.

## Ein vorhandener grober Ablauf, keine neue Suite

`php framework/tools/rehearse-api-attendance.php` erhält genau ein angehängtes
Clientsegment: Start mit ignorierter historischer Clientzeit und ohne Event-ID
→ normales `record_id` → stale-ID-Ende wird ohne Mutation verweigert → dieselbe
aktuelle ID als Clientstring beendet die eigene Projektion. Verwendet werden
dieselbe rein synthetische private Fixture, Token, Budgetinstanz und der
bestehende Writer. Keine zusätzlichen Dateien/Testsuites, UI-Mikrotests,
realen Daten, Schlüssel, Netzdienste oder CI-Auslösung. Die bestehenden groben
Request-/Tokenprofilprüfungen bleiben wiederverwendet.

Vor einer echten Gesamtaktivierung bleiben persistentes geteiltes Loginbudget,
`nbf`-/exotische Methoden-/Datums-/Paginationentscheidungen, vollständige
Clientabläufe/Swagger und der explizite Routencutover gesperrt. Ausgeliefert
bleibt ausschließlich `/up`.
