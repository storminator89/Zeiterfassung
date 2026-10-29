# #68 / CH-60: kleiner Laravel-Anwesenheitsworkflow

Basis: veröffentlichter Main `83717e8e56484b45a93c55d2901f054bfdcabdb9`,
Baum `c177eba798eef8e77f23a0500eb843da6c78dfa6`, 28.10.2026.
**Historische Paketprovenienz; #68 bleibt offen.** Keine Benachrichtigung,
neue Freigaberoute, UI, Konto-/V5-Änderung oder neue Runtimeaktivierung.

## Ausdrücklicher additiver Lesevertrag

Der vorhandene, bereits separat zuzulassende Laravel-GET-Kandidat
`GET /api.php/attendance?workflow=1` und nun der native GET liefern auf ausdrücklichen Wunsch
zusätzliche Workflowmetadaten. Ohne diesen Parameter bleibt exakt die bestehende
Defaultantwort erhalten (inzwischen mit zusätzlichem `start_metadata_version:1`
aus #51 neben `success,state,record_id,record_id_string`). Der Native-Teil und
die mechanische gemeinsame Projektion sind in
[native-attendance-workflow.md](../native-attendance-workflow.md) abgegrenzt.
Ein Client muss deshalb `workflow.version === 1` tatsächlich beobachten, statt
sie aus einer Versions-/Hostannahme zu erfinden.

Beispiel für eine laufende Pause:

```json
{
  "success": true,
  "state": "paused",
  "start_metadata_version": 1,
  "record_id": 42,
  "record_id_string": "42",
  "workflow": {
    "version": 1,
    "allowed_actions": ["pause_end", "end"],
    "action_scope": "state_only",
    "requires_write_recheck": true,
    "expected_record": "42",
    "confirmation_required_actions": ["end"]
  }
}
```

| Aktueller Status | Zustandsabhängige Aktionen | Weitere Grenze |
|---|---|---|
| `absent` | `start` | `expected_record:null`: im neuen Startpayload dieses optionale Feld weglassen, nicht JSON-null weiterreichen. |
| `working` | `pause_start`, `end` | Exakten `expected_record`-String für die aktive Arbeitszeit weitergeben. |
| `paused` | `pause_end`, `end` | `end` erfordert zusätzlich die bewusste Bestätigung des Menschen vor `confirm_end_pause:true`. |

Diese Tabelle folgt den bestehenden `attendance_transition_locked`-Zweigen;
es gibt keinen neuen Transition-/Zeitberechnungsdienst. `allowed_actions`
bedeutet ausdrücklich **nur diese Zustandsauswahl**, keine persistente
Autorisierung oder Garantie einer erfolgreichen Buchung. Dieselbe
authentifizierte eigene `apiAttendanceStatus()`-Snapshotgrenze liefert genau
einmal den Status; die reine Metadatenprojektion öffnet keine weitere DB und
akzeptiert weder Actor, Konto noch fremde `user_id` als Auswahl.

Der bestehende Writer muss jeden späteren Schreibversuch erneut prüfen:
aktuelle Authentifizierung/Anmeldung, Datensätze und erwartete Arbeitszeit,
Serverzeit, Überschneidungen, Pause und erforderliche Bestätigung,
Ereignisbindung, Fachkonflikte und COMMIT. Ein zwischenzeitlicher Zustandswechsel
kann die gerade gelesene Auswahl überholen. Insbesondere kann `start` trotz
abwesendem Status an einer vorgebuchten Abwesenheit scheitern. Die GET-Antwort
enthält keine Zeit-/Zeitzonenberechnung, Rechteausweitung, Sperrreservierung,
Mutationsbestätigung oder neuerfundene Geschäftsregel.

`expected_record` ist der vorhandene **exakte String**, nicht eine durch
JavaScript gerundete Zahl; auch IDs oberhalb `Number.MAX_SAFE_INTEGER` bleiben
erhalten. Der Client erzeugt für einen bewusst neuen Vorgang eine eigene
stabile Event-ID. Bei unbestätigter/unterbrochener Buchung muss derselbe Vorgang
mit derselben Event-ID und demselben Payload erneut aufgelöst werden. Ein
Workflow-GET ersetzt keinen gespeicherten Receipt und ist kein Grund, Pending
zu löschen oder eine zweite Ereignis-ID zu erzeugen. Ein pausiertes `end`
darf niemals automatisch Zustimmung aus den Metadaten ableiten.

## Erhaltene Transport-/Fehlergrenzen

Der bereits registrierte GET bleibt hinter unverändertem
`LegacyApiWire → RequireLegacyApiToken → Readcontroller → ChronoReads`.
Keine weitere Route oder Middleware wird registriert; aktuelle Bearer-/Scope- und
Runtimezulassung bleiben Voraussetzung. CORS/no-store, OPTIONS vor Auth,
rohe HEAD-/Method-/Pfadregeln und Guardcleanup bleiben unverändert.

Nur der exakte rohe PHP-Querywert `workflow=1` optiert ein. Vorhandene Werte
wie leer, `01`, ` 1 ` oder Arrayform werden nach dem bestehenden Guard, aber
vor dem Statussnapshot als JSON400 `Invalid workflow option.` abgewiesen.
Laravels Trim-/Nullnormalisierung macht sie nicht gültig. Bestehende
`AttendanceError`-Status bleiben erhalten; unerwartete Status-/Projektionsfehler
bleiben die vorhandene feste JSON503-Antwort, ohne Rohdaten oder Exceptions.
Die Projektion verwirft fehlende/widersprüchliche Zustands-/IDkombinationen,
statt daraus scheinbar gültige Aktionen zu erfinden. Nur die bisherigen öffentlichen Statusfelder einschließlich
`start_metadata_version` werden in die optierte Antwort übernommen.

## Tatsächlich geprüfter grober Ablauf

Ein interner Laravel13.38.0-Router-/Own-Reader-Journey unter PHP8.5.12 nutzte
eine anonyme Memory-V2-Quelle mit ausschließlich synthetischen Personen und
den echten nativen Attendance-Transitionen, nicht einen zweiten Buchungsalgorithmus:

- Abwesend → Start → Pause → Weiter → Pause → bestätigtes Ende; fünf persistierte
  synthetische Events. Workflowaktionen und erforderliche Bestätigung für das
  Arbeitsende in Pause passten in jedem beobachteten Zustand. Ohne sie kam409,
  blieb pausiert und schrieb keinen weiteren Event.
- Eigener Read trotz fremdem `user_id=2`, exakte Stringbindung
  `9007199254740994`, unveränderte Default-Vierfeldantwort; kein Read mutierte
  Daten. Ungültige Opt-ins400 vor Statussnapshot, fehlende synthetische
  Identität401, OPTIONS200, HEAD405 und Guardcleanup erhalten.
- Keine offene Transaktion, `integrity_check=ok`, native Sessionglobals
  unverändert. Die Baseline scheitert am fehlenden Workflowteil. Syntax,
  Composer-/Plattform und Diffcheck sind zusätzliche notwendige Quellprüfungen,
  kein neuer kleinteiliger Testsatz.

Die prozessinterne Identitätsinjektion ist ausdrücklich **kein** JWT-, Login-,
physischer Zulassungs-, HTTP-/Browser-/Client- oder Produktionsnachweis.
Der Test verwendete keinen Dateidatenträger, echten Account, Schlüssel,
Serverport, externen Benachrichtigungsdienst oder Deployment. Nicht bewiesen
sind Konkurrenz-/Wiederanlauf-/COMMITfehler, alle Konflikt-/Korruptionsfälle,
reale Authentifizierung oder produktive Bedienung. Die bestehende separate
Runtime-/Writer-/Cutoverzulassung wird nicht ersetzt. Benachrichtigung,
Empfängerscope, Dedupe/Retry und weitere Workflowrouten bleiben offene #68-Arbeit.
