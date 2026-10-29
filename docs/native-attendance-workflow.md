# #68 — Native-Parität für eigene Anwesenheitsmetadaten

Begrenzte Ergänzung auf Main `2072a6e2e167c8cd3cf51ea81c7765840d93647b`,
Basisbaum `a4ca5657569c056af79461bf370fead89519789e`. Kein neuer Endpoint,
Writer, Client oder Dienst. #68 bleibt offen; keine produktive Zulassung.

Der bereits vorhandene authentifizierte native GET `/api.php/attendance`
unterstützt jetzt denselben ausdrücklichen `?workflow=1`-Lesevertrag wie der
Laravel-Kandidat aus PR233. Der bestehende reine Projektionskern wurde mechanisch
nach `lib/attendance_workflow_status.php` ausgegliedert; der Laravel-Wrapper
ruft denselben Kern auf. Keine zweite Zustands-/Buchungsregel.

Ohne `workflow` bleibt die native Defaultantwort bytegleich zum bisherigen
Pfad, einschließlich `start_metadata_version:1` und exaktem
`record_id_string`; auch Laravels Defaultpfad bleibt unverändert. Bereits
eingeführte Schreib-, Receipt-, Pending- und Startmetadatenverträge bleiben
vollständig unverändert. Weder bestehende UI noch Erweiterungen optieren
unbemerkt ein. Nur der exakte PHP-Querywert `workflow=1` optiert ein: leere,
abweichende oder Arraywerte erzeugen nach der bestehenden Tokenprüfung eine
feste400-Meldung vor Beginn der Statuslesetransaktion.

## Zustandsauswahl ist keine Schreibberechtigung

Der vorhandene eigene Statusread und die neue reine Projektion erfolgen innerhalb
derselben bisherigen Lesetransaktion, ohne weitere Abfrage, eigenen Actor- oder
Personenscope. `user_id` ist kein Selektor. Die bisherige Token-/Fingerprintprüfung
läuft unverändert vor diesem Read; keine neue Authentifizierungs- oder
atomare Auth-/Statussnapshotgarantie wird behauptet.

| Status | Zustandsauswahl | Erforderliche Bindung |
|---|---|---|
| absent | start | `expected_record:null` in Metadaten; optionales Feld beim neuen Start weglassen, nicht JSON-null als Bindung senden. |
| working | pause_start, end | Exakten `record_id_string` als `expected_record` übernehmen. |
| paused | pause_end, end | Gleiche exakte Bindung; für end zusätzlich bewusste menschliche Bestätigung vor `confirm_end_pause:true`. |

Der `workflow`-Block enthält `version:1`, `action_scope:state_only`,
`requires_write_recheck:true`, `allowed_actions`, `expected_record` und
`confirmation_required_actions`. Kein internes Statusfeld wird weitergereicht.
Fehlende oder widersprüchliche kanonische Status-/IDwerte werden verweigert,
nicht zu scheinbar erlaubten Aktionen umgedeutet. IDs oberhalb der exakten
JavaScript-Zahlengrenze bleiben unveränderte Strings.

Jeder spätere Writer muss aktuelle Authentifizierung, Status/erwarteten Record,
Serverzeit, Konflikte, Eventbindung, Bestätigung und COMMIT erneut prüfen.
Ein GET reserviert keine Aktion, quittiert keine Mutation und heilt keinen
Antwortverlust. Auch `absent → start` kann etwa an einer gespeicherten Abwesenheit
scheitern. Unbestätigte Vorgänge behalten dieselbe Event-ID und denselben Payload;
dieser Read ist kein Grund für eine zweite Event-ID oder das Löschen von Pending.
Pausenendbestätigung wird niemals aus dem Metadatenblock automatisch erteilt.

## Belegte Grenzen

PHP8.5.12: ein grober anonymer kanonischer SQLite-`:memory:`-Journey nutzt die
tatsächliche aus der nativen Datei extrahierte GET-Closure nach dem bestehenden
Tokenguard sowie die bisherigen Attendance-Transitionen. Für diesen isolierten
Nachweis ist nur der Helper-Requirepfad an die geprüfte Quellwurzel gebunden;
Bootstrap und Authentifizierung werden nicht ausgeführt oder ersetzt.

Abwesend → Arbeit → Pause → verweigertes unbestätigtes Ende → Weiter → Pause →
bestätigtes Ende; fünf persistierte synthetische Events. Alle beobachteten
Aktionen/Bestätigungen, ignorierter fremder Queryselektor, schreibfreie GETs und
Transaktionscleanup stimmen. Record `9007199254740994` bleibt exakt. Defaultwire
ist gegen die unveränderte Basisclosure bytegleich; neuer Kern und bestehender
Laravel-Wrapper sind zur ursprünglichen Laravelprojektion exakt identisch.
Sechs ungültige rohe Opt-ins werden400 vor Beginn des Reads verweigert; sechs
widersprüchliche kanonische Statusformen werden nicht projiziert. Reine
ID-/Allowlistvergleiche schließen `PHP_INT_MAX` ein. Bei den GETs bleiben
Quelldaten unverändert; die synthetischen Domaintransitionen mutieren ihre
Fixtures erwartungsgemäß. Native Sessionglobals bleiben unverändert,
Integritätsprüfung und PHP-Lint bestehen.

Keine tatsächliche native oder Laravel-HTTP-/Token-/JWT-/Cookie-/Browser-/
Clientabnahme, kein Datei-/Betriebsdatenbestand oder echte Credentials verwendet.
Kein Authbypass als Produktivnachweis; kein Konkurrenz-, Wiederanlauf-, Lost-COMMIT-
oder allgemeiner Korruptionsnachweis. Vorbestehende Bootstrap-/Runtime-/V5-/
Writer-/Cutovergrenzen bleiben geschlossen bzw. separat zuzulassen. Keine
Benachrichtigung, Freigaberoute, neue Konto-/Host-/Sicherheitseinstellung,
Workflowänderung, Deployment oder Betriebsgarantie.

## Reanchor der vorhandenen Vorarbeit

Die vier Funktionsdateien sind unverändert zum früheren unabhängigen CLEAR
übernommen; nur diese Basisdokumentation ist aktualisiert. Defaultantwort,
Tokenprüfung, Own-Scope, ursprüngliche API-Datei nach Inverse der zwei Zusätze
und sämtliche Writer-/Replay-/Pending-/Receiptdateien bleiben erhalten. Der
bisherige grobe anonyme Memory-Journey wurde auf obiger Basis erneut ausgeführt
und um eine Wiederholung desselben initialen Start-Events nach Reads und
abgeschlossener Sitzung ergänzt: exakt derselbe gespeicherte Originalreceipt,
kein weiteres Event und weiterhin abwesender eigener Status. Keine neue
Repository-Testbatterie, Runtimeaktivierung oder Remoteaktion. PR #239 und dessen
offener Merge-Denial bleiben vollständig unberührt.

Der finale Reanchor liegt nach #60 PR #257. Dessen native offene-Sitzung-
Hinweise, eigene Auswahl und Sprachdateien sind vollständig bytegleich erhalten;
die Anwesenheitsseite und der kanonische Status-/Transitionkern sind nicht Teil
dieses API-Leseslices.
