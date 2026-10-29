# Eigener Home-Stand mit Korrekturantrag — nativer Teilumfang #50

Die native Korrekturliste bietet bei einem eigenen offenen Antrag auf der
sichtbaren Seite den Link „Home-Stand mit Antrag prüfen“. Erst der ausdrückliche
GET-Aufruf berechnet einen einzigen Vorher-/Nachher-Stand. Fremde Anträge,
Endentscheidungen, Anträge anderer Listenseiten und ungültige Selektoren lösen
keine zusätzliche Saldoabfrage aus. Auch ein Administrator erhält hier keine
Fremdsaldenvorschau. Filterwechsel und Seitennavigation verwerfen die Auswahl;
ein neuer Link muss bewusst gewählt werden.

Die Vorschau verwendet den bereits vorhandenen gemeinsamen
`chrono_correction_balance_preview()` unverändert. Dieser berechnet die
vollständige bisherige Home-Projektion vor und mit genau diesem Antrag erneut,
statt eine Netto-Dauerdifferenz zum Saldo zu addieren. Dadurch können
verschobene, neue oder entfernte Buchungstage auch deren Sollanteil ändern.
Der bestehende Helper prüft Originalsnapshot, laufende Sitzung, Zeitwerte und
Überschneidungen; unklare Bestände ergeben keine Zahl und keinen Ersatznullwert.
Andere offene Anträge werden nicht kumuliert.

Queuezählung und sichtbare Antragsseite sowie das gegebenenfalls gelesene
eigene Startguthaben, sämtliche eigenen Buchungen und die kanonischen
Hilfsabfragen liegen auf derselben PDO-Verbindung in einer neuen
Lesetransaktion. Sie beginnt **nach** dem unveränderten POST-Handler und endet
vor HTML-Ausgabe. Bei einem Lesefehler wird die eigene Transaktion verworfen;
keine teilweise berechnete Vorschau wird ausgegeben. Keine Verbindung,
Transaktion oder Cache bleibt für spätere Requests offen.

## Fachliche und technische Grenzen

Die Ausgabe erklärt ausdrücklich den bisherigen Home-Vertrag: Startguthaben
plus abgeschlossene Buchungstage minus deren Soll. **Kein vollständiges
Vertragszeitkonto, keine Freigabezusage** und keine Festschreibung. Bei der
tatsächlichen Entscheidung gelten erneut die bestehenden Writerprüfungen und
die Vier-Augen-Regel. Die aktuelle Projektion und gespeicherte Abwesenheits-
gutschriften werden nicht repariert, neu bewertet oder überschrieben.

Der neue GET-Parameter benennt ausschließlich die eigene Antragsauswahl, keinen
Benutzer oder Scope. Die Auswahl stammt aus der bereits autorisierten und
paginierten Queue; Owner und Pendingstatus werden zusätzlich geprüft. Die
bestehenden Admin-/Supervisor-/Eigentümerfilter, POST-Aktionen, CSRF,
Selbstfreigabeverbot, Audit, Schema, Berechnungshilfen und native Zeitfelder
bleiben unverändert. Zusätzliche Ausgabe besteht nur aus formatierten Werten
und festen Hinweisen mit bestehendem Escaping. Keine Rohdatensnapshots,
Notizen oder neuen Downloads.

Die bereits separat vorbereitete native Datumshilfe (PR #239) und native
Rücknahme werden weder dupliziert noch aktiviert. Deren eigener Integrations-
und Freigabestand bleibt unberührt; bei späterer Zusammenführung sind die
jeweiligen Änderungen an `adjustments.php` ausdrücklich zu erhalten.

## Lokale Nachweise

Basis: `fdb98f55` (lokaler Snapshotbaum `324245ac33fc830eb4e516541678fd31a8ced126`). PHP 8.5.12,
SQLite 3.46.1, Syntax und Diffcheck. Ein grober anonymisierter Ablauf mit der
tatsächlichen Queue-Lesequelle und dem isolierten Ausgabefragment auf
`sqlite::memory:` unter `PRAGMA query_only=ON`: eigene/fremde/endgültige/
fehlende/ungültige/off-page Auswahl, vollständige Home-Projektion, geschlossener
Snapshot, stale Original, expliziter Link, Scopehinweise und Fehlerrollback.
20 Prüfbedingungen bestanden. Zusätzlich unabhängiges statisches Review ohne
Blocker und Quellerhaltung der bestehenden POST-/Scope-/Hilfsfunktionen.

Keine echte Datenbank, Betriebsdaten, Konten, Schlüssel, Appinitialisierung,
HTTP, Session-/CSRF-Ausführung, Browser, Konkurrenz- oder Großbestandabnahme.
Kein Appstart, keine Remoteaktion, neue CI, Veröffentlichung oder Aktivierung
des separaten Laravel-Kandidaten. #50 bleibt einschließlich Vertretung und
Eskalation offen.

## Reankerung auf aktuellen Main

Der bereits vorbereitete Kandidat wurde ohne Code-Neubau auf den lokal gegen
den vollständigen Remote-Baum verifizierten Main `fdb98f55` übernommen.
Alle ursprünglichen Codeänderungen und bisherigen Main-Inhalte sind erhalten;
Syntax, Diffcheck und die unveränderten groben anonymisierten Nachweise wurden
auf dieser Basis erneut ausgeführt. Keine Remoteaktion oder neue CI.
Der offene Merge-Denial von PR #239 wird nicht erneut versucht oder umgangen.
