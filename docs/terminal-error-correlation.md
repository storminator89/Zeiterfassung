# #66 — Anfragekennung im vorhandenen Terminal-Fehlerhinweis

Quellbasis Main `cbf3c06971f4d381637b4c85fbafaa0a6b67a1ea`, Tree
`8923740804ce0072ca79f4b5ea5a084b5807a30d`, nach PR #357.
Nur `terminal-agent/agent.py` und diese Notiz ändern sich. Keine Übernahme
anderer offener oder zurückgehaltener Pakete; kein Runtime-/Host-/Securitywechsel.

## Sichtbarer Nutzen und feste Grenzen

Der vorhandene Gerätedienst übernimmt eine bereits vom Server gelieferte
`X-Request-ID` bei einer tatsächlichen HTTP-Antwort mit Status 500–599 in den
bisherigen Fehlerhinweis: `Anfragekennung: … (kein Buchungsnachweis).`
Die Kennung muss genau einmal als Header vorkommen und exakt 32 kleine
Hexzeichen enthalten. Fehlende, doppelte oder abweichende Werte und Fehler
beim Headerzugriff lassen die bisherige Meldung unverändert. Es wird keine
Ersatzkennung erzeugt. 2xx/4xx und Netzfehler erhalten keinen Zusatz.

Das gilt auch für den vorhandenen festen Fehler nach unlesbarem JSON, aber
nur wenn die ursprüngliche HTTP-Antwort selbst 5xx war. Ein wegen defekter
2xx-Antwort lokal erzeugter 503 wird nicht nachträglich zur 5xx-Serverkennung.
Die bisherigen Statuswerte und Rückfallmeldungen bleiben bestehen.

Im bisherigen direkten Modus ergänzt der Gerätedienst nur einen vorhandenen
String-Meldungstext. Im bereits vorhandenen optionalen Durable-Modus bleiben
die festen datensparsamen Anmelde-/Unbestätigtmeldungen erhalten; nur die
zulässige Kennung wird angehängt. Ein privater, kurzlebiger Dictionary-Subtyp
trägt diese Headerinformation zwischen Transport und fester Meldung. Er fügt
kein JSON-Feld hinzu. Gleichnamige Felder im Serverbody können diese interne
Markierung nicht erzeugen. Die unveränderte Kioskansicht zeigt Meldungen über
`textContent`, nicht als HTML.

Keine zusätzliche Rohantwort, URL, Person, PIN, Device-ID, Grant, Signatur oder
Schlüssel wird neu ausgegeben oder geloggt. Die Kennung landet weder in einem
Eventpayload noch in der privaten Pendingablage; es gibt keinen neuen Logger,
Speicher, Endpoint oder Dienst. Pairing-, Heartbeat-, HMAC-, Host-/Origin-/CSRF-,
Anmelde-, Generation-/Timeout-, Vier-Augen- und Writergrenzen bleiben unverändert.
Die Änderung aktiviert den optionalen Durable-Modus nicht und ändert keine
Konfiguration oder Berechtigung.

Eine Anfragekennung ist keine Ereignis-/Idempotenz-ID und kein Beleg für
erfolgten oder fehlgeschlagenen COMMIT. Alle bestehenden Bestätigungsprüfungen,
Pendingzustände, Claim-/Uncertain-/Completeübergänge, exakten Payloadbytes,
Reset- und Wiederholungsregeln bleiben gleich. Keine automatische Wiederholung,
neue Offlinequeue oder Recoveryfunktion.

## Kurzer Betreiberhinweis

Bei einem Fehler nur die angezeigte Kennung mit Zeitpunkt privat an den
zuständigen Betreiber weitergeben; keine PINs, Schlüssel oder Rohrequests.
Der Betreiber kann dieselbe Kennung im vorhandenen privaten Serverlog suchen,
soweit der betreffende Serverpfad sie dort protokolliert. Ein fehlender Treffer
oder eine fehlende Kennung beweist weder Erfolg noch ausbleibende Verarbeitung.
Unbestätigte Buchungen weiter nach dem bestehenden Klärungs-/Korrekturvertrag
behandeln, niemals anhand dieser Kennung neu buchen.

## Grober Komponentenbeleg und offene Abnahme

Python 3.12.14, ein zusammenhängender anonymer In-Process-Vergleich von Basis
und Kandidat mit tatsächlichem `Remote.request`/`request_raw` sowie
`DurableKiosk`: zulässiger/fehlender/abweichender/doppelter Header, werfender
Headerzugriff, 2xx/4xx, Netzfehler, unlesbares JSON und reine Bodykennungen.
Anmeldung → Event → wiederholter alter Aufruf → Reset wurde mit unverändertem
synthetischem Transport und nichtpersistenter Storeattrappe verglichen;
Erfolg, Anmeldefehler und unbestätigtes Event unterscheiden sich gegenüber
der Basis ausschließlich durch den jeweils erwarteten Meldungszusatz.
Gesendete Payloads und Storeübergänge bleiben gleich; keine Kennung wird dort
eingeschleust. Keine neue Repositorytestsuite oder manuell gestartete CI.
Python-Syntax, Diffcheck und vollständige Dateierhaltung werden separat geprüft.

Das ist keine tatsächliche HTTP-/Proxy-/TLS-/Serverlog-/Browser-/Hardware-,
Pendingdatei-/Crash-/Konkurrenz- oder Betriebsabnahme. Keine realen Konten,
nutzbaren Zugangsdaten, Schlüssel, Betriebsdaten, Dateidatenbank oder Server
wurden eingerichtet oder verändert. Die Browserabnahme bleibt beim Nutzer.
Keine Behauptung einer neuen Serverkennung für alle Fehlerpfade, Alarmierung,
Logrotation oder vollständiger Gesundheit; **#66 bleibt teilweise/offen**.
