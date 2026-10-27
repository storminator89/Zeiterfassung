# #36 – Webaktionen an die bestätigte Sitzung binden

Basis Main `993d7e659219040cf8d0f9dbb64b54da4c80b286`. Bestehende UI, eigener
Benutzer, Serverzeit, Pausen-, Audit-, Idempotenz- und Transaktionsregeln bleiben
erhalten. Keine neue Route, Standardaktivierung, API-/Terminalumschaltung,
Konten-/Schlüsseländerung, Zeitkorrektur oder neue Oberfläche.

## Exakter Web-Requestvertrag

Neue `pause_start`, `pause_end` und `end`-Events enthalten `expected_record`: einen
rohen positiven ASCII-Dezimalstring (`[1-9][0-9]*`) innerhalb `PHP_INT_MAX`.
Vor dem Integercast werden Typ, Syntax und Länge/lexikalische Obergrenze geprüft.
Keine Arrays, bool/null, Leerzeichen, führenden Nullen, Vorzeichen, Dezimalpunkte,
Exponenten, negativen Werte oder Overflow-Koercion. Vorhandene ungültige Eingaben
ergeben400. Ein fehlendes Feld bleibt technisch null, berechtigt aber **nicht**
mehr zu einer neuen Nicht-Start-Webmutation. Neue Start-Events lassen das Feld
weg; der unveränderte Startguard verweigert jede bereits offene Arbeitszeit.

Der Client übernimmt Zustand und Record gemeinsam aus servergerendertem HTML
oder demselben gültigen Webstatussnapshot. Der Webstatus ergänzt dafür
`expected_record: string|null`, während seine vorhandene numerische `record_id`
und der separate APIstatus unverändert bleiben. Die `expected_record`-Sitzungsbindung
wird im Client nie über `Number` konvertiert: auch eine vorhandene SQLite-ID
jenseits der sicheren JavaScript-Integergrenze bleibt dafür ein exakter String.
Unveränderte Korrekturlinks der jüngsten Zeilen verwenden weiterhin `Number(record.id)`;
keine allgemeine Big-ID-Kompatibilität sämtlicher UIpfade wird behauptet.
Keine neue ID-Betragsgrenze für die Sitzungsbindung.
Ohne bestätigte Bindung sind neue Nicht-Start-Aktionen deaktiviert.

Ein neues Pendingevent erfasst seine Recordbindung **einmal**. Statusrefresh,
Reload und gleicher Retry dürfen sie niemals ersetzen. Ereignis-ID, Aktion,
Bestätigung und Bindung bleiben derselbe Payload; kein automatisches Neubuchen.

## Vorhandener Replay vor neuer Webanforderung

`attendance_transition_locked()` behält seine bestehende Validierung, Hashbildung
aus Benutzer/Aktion/Bestätigung/expectedRecord und die vorhandene Replayabfrage.
Der neue vertrauenswürdige Parameter `requireExpectedRecord` ist **kein**
Requestfeld und kein Teil dieses Payloadhashes. Sein Defaultfalse erhält API-,
Terminal- und sonstige bestehende Aufrufer. Ausschließlich feste Webaufrufer
setzen true: native `attendance.php`, vorhandener `save.php`-Aktionsalias und der
Laravel-Webdienst. Dessen Writer leitet den Parameter im vorhandenen Lock weiter;
kein zweiter Writer, BEGIN, Savepoint oder neuer generischer Transaktionsmotor.
Der vorhandene Laravel-`save.php`-Aktionsalias bleibt410 und wird nicht aktiviert.

Nach einem möglichen gespeicherten Replay, aber **vor** Auditkontext/neuer
Mutation, wird eine neue Nicht-Start-Webaktion ohne Bindung mit409 verweigert.
Mit positiver Bindung gilt der bestehende Vergleich zur aktiven Record-ID.
Ein Konflikt rollt über denselben vorhandenen Transaktionspfad zurück.

| Vorhandener Zustand | Ergebnis |
|---|---|
| Alte bereits committed Null-Payload, gleiche ID/Aktion/Bestätigung | Vorhandene gespeicherte Antwort unverändert200, keine neue Mutation |
| Alter noch nicht committed Nicht-Start-Payload ohne Bindung |409, keine neue Session-/Event-/Auditänderung; bewusst neu laden |
| Neue positive Bindung auf inzwischen andere Sitzung | Vorhandener409; neue Sitzung bleibt unverändert |
| Bereits committed positive Bindung, gleicher Retry | Vorhandene Antwort vor aktuellem Sitzungsvergleich, unveränderte Idempotenz |
| Gleiche Event-ID mit anderer Bindung/Payload | Vorhandener Payloadkonflikt409 |

**Bewusste Kompatibilitätsänderung:** Alte Webclients können keine neuen
ungebundenen Nicht-Start-Aktionen mehr ausführen; sie erhalten409 und müssen neu
laden/aktualisiert werden. Bereits committed alte Events bleiben wiederholbar.
Ein unbestätigter alter Payload wird nicht mit dem aktuell gelesenen Record
ergänzt und nicht durch eine neue Ereignis-ID ersetzt. API-/Terminalclients
behalten ausdrücklich ihren bisherigen optionalen Bindungsvertrag. Das ist
kein stilles APIupgrade und keine vollständige kanalübergreifende #36-Abnahme.

## Bestätigung, Konflikt und unklare Antwort

Ein bereits gespeicherter Legacy-Pendingwert ohne neues Feld bleibt unverändert
für die Replay-zuerst-Entscheidung erhalten. Ein vorhandenes fehlerhaftes neues
Bindungsfeld bleibt gesperrt gespeichert; fremde Konto-/Tokenbindung bleibt an
der bestehenden Framework-Startgrenze gesperrt. Kein neuer Cross-Session-Reset.

200-Bestätigung entfernt dieses Pendingevent, liefert aber keine neue aktuelle
Bindung: eine gecachte Antwort kann historischen Zustand enthalten. Bis zum
folgenden frischen Status bleiben Nicht-Start-Aktionen deaktiviert. Ein
definitiver409 entfernt den abgelehnten Versuch und lädt nur den Status neu;
die gewünschte Aktion wird nicht automatisch auf der neuen Sitzung ausgeführt.
Timeout,503,401/403 und unlesbare erfolgreiche/ungeklärte Antworten behalten
dasselbe Pendingevent mit derselben Bindung zur Wiederholung. Bei einem definitiven
Nicht-OK-Status unter500 außer401/403 verwirft der vorhandene Client dagegen schon
vor dem JSONlesen; unlesbarer Inhalt eines solchen Fehlers ändert diese bestehende
Grenze nicht. Bestehende Reportfinalitäts-/Warnmasken und
deren Aktualisierung bleiben unverändert; kein neues Zeitkonto im Browser.

## Genau 17 Quellpfade / begrenzter Nachweis

Native Core/Wrapper/Parser, beide vorhandenen Webaufrufer, Header/Attendanceclient;
Laravel-Webstatusreader, Writerweiterleitung, Webdienst/-controller, vorhandenes
Page-DTO/Layout und Startadapter; dieses Dokument und die bestehende
Webintegrationsbeschreibung. Keine anderen Reader-/Writer-/API-/Terminalmethoden,
Dashboard-/Teamsummen, Schema-, Bootstrap-, Middleware- oder Authänderung.
Zusätzlich erhalten drei bereits vorhandene Fixturedateien ausschließlich die
bereits bekannte Record-ID in ihren bisherigen erfolgreichen Webaufrufen:
`rehearse-web-attendance.php`, `tests/terminal_http.py`, `tests/practice_http.py`.
Keine Assertion, Fallauswahl oder API-/Terminalanfrage wird abgeschwächt oder
neu ausgeführt. Die einmalige statische Aufruferaufnahme fand keine weiteren
erfolgreichen direkten Nicht-Start-Webaufrufe ohne erforderliche Bindung.

Nur ein grober erfundener Speicherablauf für alte/neue Sitzung und Replay sowie
ein zugehöriger synthetischer Client-Pendingablauf plus notwendige PHP-/JS-/Blade-
Syntax- und Quellerhaltungsprüfung. Keine tatsächlichen Stempelaktionen, Konten,
Produktivdaten, Uhrverstellung, Session-/Authinjektion, HTTP-/Browser-/Server-
Ausführung oder neue CI. Echte Mehrtab-/Reload-/Betriebsabnahme bleibt offen,
ebenso #57-Konto-/Tokenwechsel und ungeklärte sitzungsübergreifende Ereignisse.
Ein produktiver gemischter Alt-/Neuwriterrollout wird nicht freigegeben; die
bestehenden Ein-Writer-/Cutover-/Rücknahmegates gelten weiter.
