# #16 — native Kalenderprojektion nach gemeinsamer Zeitprüfung

Der native Header diagnostizierte bereits alle eigenen Originalzeilen über
`report_record_calculation`/`report_days`, zeigte die sichtbare Datenwarnung mit
eigenen Korrekturlinks und sperrte unvollständige Summen. Das Dashboard gab
dieselben ungefilterten Zeilen anschließend dennoch an `calendar_range_events`.
Dort konnte `booking_span` bei unlesbarem Start/Ende werfen und die Seite nach
dem Header abbrechen.

Vor der Kalenderprojektion wird nun dieselbe bestehende gemeinsame Berechnung
verwendet wie in `LegacyChronoReads::calendarWeek`. Nur als `invalid` erkannte
Zeilen werden aus dem **separaten Kalenderinput** ausgeschlossen. Gültige und
offene Zeilen bleiben unverändert. Kein neuer Parser oder Berechnungsweg.

Die rohen `$records` bleiben vollständig erhalten: vorhandene Diagnose,
Summensperren, Arbeitszeitliste und begrenzte eigene Korrekturlinks gelten weiter.
Die bestehende sichtbare Warnung sagt ausdrücklich, dass auch der Kalender
unvollständig sein kann. Es wird kein Datum/Nullwert erfunden, keine ungültige
Zeile als gültiges Ereignis gezeichnet und kein Bestand automatisch geändert.
Kalender-/Feiertagshelfer, ausgewählte Woche, Cacheabfrage und Feiertagsdedup,
authentifizierter Ownscope, UI/Markup/JS, Writer und Runtime bleiben unverändert.

Ein grober PHP8.5.12-Quellfragment-/Arrayjourney reproduziert den bisherigen
Abbruch und prüft anschließend gültige/offene/Feiertagszeilen zusammen mit
unlesbarem Start/Ende, Datum ohne Uhrzeit, relativer Zeit, rückläufigem Intervall
und Pause größer als Brutto. Die tatsächliche neue Dashboardfilterung verhindert
den Fehler; gültige Events einschließlich gecachter Feiertage/Dedup bleiben
bytegleich. Originalzeilen, Diagnosen, Berichtstage und Konflikte bleiben gleich.
Der bestehende tatsächliche Header-Warnfragment zeigt weiter alle sechs
betroffenen eigenen Korrekturlinks. Nur feste Include-/Fetchstellen werden für
die bootstrapfreie Prüfung an bereits gelieferte Testdaten gebunden.

Keine DB, Konten, Credentials, Schlüssel, Session-/Authbootstrap, HTTP oder
Runtimeänderung. Kein neuer Repositorytest oder CI-Dispatch. Der Nutzer übernimmt
die Browserprüfung; sie wird hier nicht als durchgeführt behauptet. Die früheren
#16-Nachweise für gemeinsame Formel, Supervisorfallback, Native-/Laravel-CSV und
eigene Laravel-Korrekturlinks bleiben getrennt erhalten.
