# #66 / CH-58: datensparsame Laravel-Requestkorrelation

Quellbasis: veröffentlichter Main `e954a4487ec62f507c70e4f86e2b6ffd7ad02e14`,
Baum `5f31f4a35d8e17f803a5c0fbc684afd3e0ef0672`, 28.10.2026.
**Teilfortschritt für #66, keine vollständige Betriebs-/Alarmierungsabnahme.**

## Verwendeter Pfad, keine zusätzliche Aktivierung

`bootstrap/app.php` ergänzt den bereits verwendeten Laravel-HTTP-/Fehlerpfad.
Normale Laravel-Anfragen erhalten vor den gewöhnlichen globalen Middlewares
eine neue zufällige 128-Bit-ID (32 kleine Hexzeichen). Die ID liegt nur als
internes Requestattribut vor; sie wird weder aus Clientheadern noch aus
Formular, Query, Cookie oder Body übernommen. Auch ein vorhandenes Attribut
wird beim Middlewareeintritt ersetzt. Es gibt keinen globalen Loggerkontext,
keine persistente Session-ID und kein personenbezogenes Korrelationsmerkmal.

Die Antwort trägt `X-Request-ID`. Status, Body, Weiterleitung, Cookies und
bestehende API-Fehlerformen werden nicht ersetzt. Laravels abschließender
Exception-Responsecallback erhält die ID auch bei einer außerhalb der
Middleware gerenderten Ausnahme. Die bestehende dedizierte Raw-Terminalgrenze
bleibt **davor**: deren Kurzschluss und Protokoll werden nicht umgebaut und
erhalten durch dieses Paket keine ID. Native PHP-Einstiege bleiben unverändert.

Der bestehende datensparsame Exceptionreporter behält seine feste Nachricht
`Framework request failed.` und ergänzt die ID, sofern eine solche Anfrage
vorliegt. `exception_type` verwendet `get_debug_type()`, damit anonyme
Exceptionklassen keinen in `::class` enthaltenen Quelldateipfad verraten.
Der bisherige `stop()`-Vertrag verhindert weiterhin das zusätzliche Standardlog
mit Exceptiontext/Stack/SQL. Console- oder sehr frühe Bootstrapfehler ohne
zugewiesene HTTP-ID erfinden keine ID.

Eine normale, von dieser Middleware zurückerhaltene 5xx-Antwort erzeugt
zusätzlich genau eine feste Warnung `Framework response failed.` mit
`request_id` und numerischem `status`. Damit lassen sich auch kontrollierte
Fehlerantworten korrelieren, deren Controller eine Ausnahme bewusst abfängt.
Ein geworfener 500 kann somit einen Exceptiondetail- und einen
Antwortsummeneintrag **mit derselben ID** haben; das sind keine zwei Anfragen.
Eine Störung des Warnlogtransports ersetzt die bestehende Fehlerantwort nicht;
die Antwortsumme ist best-effort, ohne zweiten Logtransport oder Garantie.
Erfolgreiche Antworten und gewöhnliche 4xx erzeugen keine neue Requestlogzeile.
Von Laravel nicht berichtete Fehler werden nicht pauschal berichtspflichtig.

Keine URLs, IPs, User-Agent-/Authorizationheader, Bodies, Querys, Kontonamen/-IDs,
Exceptionnachrichten, SQL/Bindings, PINs, Schlüssel, Tokens oder Datei-/DNPfade
werden diesen Einträgen hinzugefügt. Kein externer Dienst, eigenes Logfile,
Rotationsjob, öffentlicher Statusendpunkt, CORS-Expose-Header oder neues
Konfigurations-/Runtime-Opt-in wird angelegt. Die ID ist **keine**
Authentifizierungs-, Idempotenz-, Buchungs- oder Ereignis-ID.

## Kurzes Betreiber-Runbook

1. Bei einer fehlerhaften Laravel-Anfrage den HTTP-Status, Zeitpunkt und den
   Antwortheader `X-Request-ID` privat erfassen. Keine Zugangsdaten, vollständigen
   URLs, Anfragebodies oder Personaldaten in Support/GitHub übernehmen. Eine
   externe Fehlerseite/Proxyantwort muss nicht von Laravel stammen und kann
   deshalb ohne diese ID sein.
2. Nur eine ID mit exakt 32 kleinen Hexzeichen als Suchwert verwenden und sie
   im **bestehenden privaten stderr-/Containerlog** des betreffenden Prozesses
   suchen. Nicht öffentliche Logdownloads oder `phpinfo()` einrichten.
   Antwortsummen nach eindeutiger `request_id` zählen, nicht zusätzlich die
   Exceptiondetailzeilen derselben Anfrage als neue Fehler zählen.
3. Ein 5xx-Eintrag ist ein konkreter degradierter Anfrageausgang, kein Beweis
   für Datenverlust oder einen fehlgeschlagenen Commit. Bei unbestätigter
   Buchung zuerst den vorhandenen Pending-/Event-ID-Wiederholungsvertrag
   beachten; niemals aufgrund der Support-ID einen neuen Buchungsversuch mit
   neuer Ereignis-ID erzeugen oder Bestandsdaten reparieren.
4. `/up` mit 200 beweist nur den vorhandenen Framework-Healthpfad. Es beweist
   **nicht** Datenbank-/Writer-/Schlüssel-/LDAP-/Gerätegesundheit, freien Speicher,
   ein aktuelles Backup, dauerhafte Logzustellung oder einen vollständigen
   Cutover. Ein anschließender erfolgreicher Request besitzt eine neue ID und
   löscht keine frühere Fehlermeldung.
5. Vor betrieblicher Alarmierung Verantwortliche, Aufbewahrung/Zugriff und
   vorhandene stderr-Erfassung/Rotation prüfen. Wiederholte 5xx anhand eindeutiger
   IDs und Zeitfenster bewerten; Schwellen und Empfänger bedürfen einer
   Betriebsentscheidung. Dieses Paket startet keinen Alarmversand und ändert
   keine Hosting-, Logging-, Sicherheits- oder Netzkonfiguration.

## Grober synthetischer Nachweis

Ein HTTP-Kernjourney mit tatsächlichem Laravel `13.38.0` und PHP `8.5.12`
verwendete nur prozessintern registrierte synthetische Routen und einen
Memory-Loghandler; kein Serverport, Browser, Konto oder Bestandsdatenträger.
Ein ausschließlich im Runner gesetzter synthetischer Frameworkschlüssel
ermöglichte den Standardkernel, ohne einen Schlüssel zu provisionieren.

- 200 → geworfener JSON-500 → kontrollierter 503 → 302 → 404 → 200 → `/up`200:
  jeweils eine neue formatgültige serverseitige ID; bestehende feste Bodies,
  Weiterleitungsziel und `Retry-After` erhalten.
- Anonyme geworfene Exception: derselbe Antwort-/Logwert, kein Exceptiontext,
  anonymer Quelldateipfad, Client-ID, synthetischer Bearertoken, Cookie oder
  Querywert in den fest begrenzten Logkontexten. 500 und kontrollierter503
  besitzen je eine Antwortsumme; erfolgreiche und404-Folgeantworten keine.
- Default-Terminal bleibt leerer404 ohne neue ID/Logzeile; native Sessionglobals
  unverändert, null Datenbankverbindungen.
- Ein absichtlich ausgefallener Memory-Logtransport belässt die kontrollierte
  503-Antwort samt Body, Support-ID und `Retry-After` unverändert.
- Die unveränderte Baseline hat noch keinen servergenerierten ID-Header und
  scheitert an genau diesem groben Nachweis. PHP-Syntax und `git diff --check`
  sind die notwendigen Quellprüfungen, kein zusätzlicher kleinteiliger Testsatz.

Keine reale HTTP-/Reverse-Proxy-/Browser-/Multiworker-/Logginghostabnahme,
Speicher-/Backupalter-/Heartbeatmessung, Restoreprobe, Rotation oder
Alarmzustellung ist damit bewiesen. Fehler vor dem Korrelationsmiddlewareeintritt,
nach dem Versand einer Streamingantwort und Ausfälle des Logtransports sind
nicht vollständig abgedeckt. #66 bleibt offen; keine Remoteveröffentlichung,
Laufzeitaktivierung, DB-/Schlüssel-/Hoständerung oder neuer CI-Lauf.

Frameworkverträge: [Laravel13 Middleware](https://laravel.com/docs/13.x/middleware),
[Laravel13 Fehlerbehandlung](https://laravel.com/docs/13.x/errors).
