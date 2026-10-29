# #68 — eigenes gespeichertes Arbeitszeitmodell per GET

`GET /api.php/work-model` liefert ausschließlich die gespeicherte Modellversion,
die für das aktuell authentifizierte Konto an einem gewählten Berlin-Tag gilt.
Administration/Vorgesetzte erhalten ebenfalls nur ihre eigene Version. Keine
Personen-/Modell-ID als Auswahl, keine neue Schreib-/Freigabeberechtigung,
Benachrichtigung, Änderung oder Aktivierung.

## Kleiner Vertrag, keine neue Sollberechnung

- Optional `date=YYYY-MM-DD`, Jahre 0001..9999, echter Kalendertag. Ohne Parameter
  wird der aktuelle Server-Tag in Europe/Berlin innerhalb desselben Lesesnapshots
  gewählt. Gerätezeitzone und Clientzeit entscheiden nicht über den Default.
- Rohe PHP-Query wie bei den vorhandenen API-Lesern; leere/mehrteilige Werte,
  Arrays, Whitespace, ungültige Daten und unbekannte Parameter einschließlich
  `user_id`, `role` oder `model_id` ergeben 400. Keine Normalisierung zu „heute“.
  Mehrfach gleich benannte Queryfelder folgen unverändert PHPs parse_str-Regeln.
- Genau die bestehende Auswahl aus `planned_minutes`: eigene Modelle mit
  `valid_from <= date`, danach `valid_from DESC,id DESC LIMIT1`. Kein UI-Seitenlimit
  entscheidet darüber. Eine künftige Version wird vor ihrem Datum nicht gewählt;
  mehrere Versionen desselben Datums verwenden die größte exakte Quell-ID.
- `data` ist das Objekt `{model_id, valid_from, weekday_minutes}`. Die ID ist eine
  positive exakte signed64-Dezimalzeichenfolge, auch oberhalb der JavaScript-
  Safeintegergrenze. Sie ist keine Berechtigung oder Revision für einen Writer.
  Sieben Minutenwerte folgen Montag bis Sonntag, jeweils ganze 0..1440. Die
  ursprüngliche SQLite-Startmodell-Triggerdarstellung wie 480.0 wird als dieselbe
  ganze Minutenzahl ausgegeben. Exakte Quelllexeme statt Float-Decoding:
  ausschließlich nichtnegative einfache JSON-Zahlen mit optional ausschließlich
  Null-Nachkommastellen. Exponenten, Vorzeichen (auch -0), Zeichenfolgen,
  Bruchteile, falsche Anzahl, ungültiges Datum oder anderweitig nicht
  repräsentierbare ausgewählte Version ergeben 503,
  keine still reparierten oder teilweise ausgegebenen Daten.
- Ohne gespeicherte für den Tag maßgebliche Version ist `data:null`: **nicht** kein Arbeitssoll,
  kein Anspruch und keine Erlaubnis für Nullgutschrift. Der mögliche bisherige
  werktägliche `regelarbeitszeit`-Fallback wird ausdrücklich **nicht berechnet**
  oder offengelegt. Das betrifft ebenso keine Reparatur eines Bestandsproblems.
- `meta` nennt Datum, `time_zone:Europe/Berlin`, `scope:own`,
  `selection:valid_from_desc_id_desc`, `holiday_adjustment:not_applied` und
  `legacy_fallback:not_returned`. Keine Feiertags-/Abwesenheitsgutschrift,
  Monats-/Vertragszeitkonto-, Eintritt-/Austritts- oder sonstige Neuberechnung.
  Ein Feiertag verändert deshalb nicht die gespeicherten Wochenminuten.

Keine Begründungen, Autor-/Owner-/Revieweridentitäten, Roh-JSONfelder, andere
Modellversionen, Kontodaten, Credentials oder Auditsnapshots werden ausgegeben.
Ein spätere erneute Abfrage liest einen neuen aktuellen Snapshot; der GET ist
kein unveränderlicher Export oder Änderungsfeed. Er enthält keine Zusage zur
Richtigkeit/Vollständigkeit historischer Modelle oder Feiertagsbestände.

## Bestehende Grenzen unverändert

Native verwendet denselben neuen definitions-only PDO-Reader in ihrer bestehenden
JWT-/Passwortversionsgrenze und einer eigenen Lesetransaktion. Laravel ergänzt
nur eine optionale Lesefähigkeit neben dem unveränderten `ChronoReads`-Vertrag
und delegiert im bestehenden authentifizierten Snapshot. Ein nicht angebundener
Adapter ist 503, kein Fallback auf die administrative Modellliste.

Laravel behält getrennte APIopt-in-/Admission-/JWT-/Generations-/Betriebspolicy-
gates und rohe Methoden-/URL-Prüfung. Diese zusätzlichen Laravel-Betriebsgates
werden nicht still auf Native übertragen. Bestehende Auth-/OPTIONS-/HEAD-/
Methodenformen bleiben erhalten; Antworten sind no-store. Lesefehler ergeben
nur `{success:false,message:"Work model unavailable."}` mit 503, keine
Exceptiondetails oder Teilversion. Kein Schema-/Provider-/Writer-/Auth-/Key-/
Host-/Runtime-/UIwechsel oder neue Abhängigkeit. Die bestehende UI bleibt exakt.

## Grober begrenzter Komponentennachweis

Basis Main `4d6efa38dfa9175fbb14a3ca25812686bd701154`, Baum
`5d0ce27e13a74841ae7b4b2ec6982931ce8c4be6`. PHP 8.5.12 / SQLite 3.46.1 /
Laravel 13.38.0: ein anonymer query-only Memory-Journey mit originalem Schema,
Migratoren, Startmodelltrigger und Audit; tatsächlicher gemeinsamer Reader,
Laravel-Readadapter/Router/Wire/RequestGuard mit bewusst künstlichem Identity-
Resolver und tatsächlicher extrahierter nativer Handler. Aktuelle/frühere/
künftige Version und Datumsgleichstand, große/maximale exakte IDs, originale
integrale Triggerzahlen, Zurückweisung der sonst rundenden/unterlaufenden
Bruchlexeme `480.00000000000000001`/`1e-400`, reine eigene Admin-/Supervisorsicht, rohe Query-/
Methodenfehler, neuer Snapshot nach späterem Fixture-Append und feste 503 bei
ungültigem/fehlendem Source. Daten/Schema/Änderungszähler, Guardwechsel,
Transaktionsabschluss, Sitzung und Zeitzone bleiben im Readsegment unverändert.
Null-Version wurde ausschließlich an der gemeinsamen Helpergrenze mit fehlendem
Trusted-Owner-Fixturewert geprüft; der geschützte Reader weist den fehlenden
Actor stattdessen 503 zurück. Keine positive Bestands-/Accountzulassung daraus.

Der Identity-Resolver ist **kein** tatsächlicher JWT-/Passwort-/Generations-/
Betriebspolicy- oder positiver V5-Nachweis. Keine komplette App-/HTTP-/CSRF-/
Browser-, Konkurrenz-/Großbestands-/Produktivabnahme; keine echten Konten,
Credentials, Schlüssel, Dateidatenbank, Aktivierung oder Benachrichtigung.
Notwendige PHP-/JSONsyntax und vollständige Quellerhaltung separat geprüft;
keine neue Repository-Testsuite oder manuell gestartete CI.

#68 bleibt teilweise/offen: Status-GETs werden nicht dupliziert; ausgehende
Antragsbenachrichtigungen, Scope/Dedupe/Retry, weitere konkret benötigte
Workflowverträge und reale Integrationsabnahme bleiben gesonderte Arbeit.
