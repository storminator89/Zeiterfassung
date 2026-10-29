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
Die damals unveränderten Korrekturlinks der jüngsten Zeilen verwendeten noch
`Number(record.id)`; der unten abgegrenzte #14-Nachtrag behebt genau diesen
Darstellungspfad. Keine allgemeine Big-ID-Kompatibilität sämtlicher UIpfade wird behauptet.
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

## #14-Nachtrag: jüngste Korrekturlinks behalten die exakte Zeilen-ID

Quellschritt auf Main `830b30f92066ba152e1d6f1aedd8da7a7fe671d5`, Baum
`c332690c347fe133175753a2544a7897a754b47e`. Der bisherige tatsächliche
`renderRecent` erzeugte nach JSONzahlen-Decodierung für9007199254740993 den Link
auf9007199254740992. Das ist isoliert am unveränderten Renderer reproduziert,
keine Behauptung einer real fehlgeleiteten Korrektur.

Native `attendance.php?format=status` und der vorhandene Laravel-Webstatusreader
fügen in ihrer selben eigenen30er-Abfrage nur `CAST(id AS TEXT) AS id_string`
hinzu. Numerische `id`, alle anderen alten Felder/Typen/Werte, Reihenfolge,
Ownerprädikat, Limit, Transaktion, Kontext und Status bleiben erhalten. Kein
weiterer Read, Subjectparameter oder neuer Pfad. Der getrennte öffentliche
APIstatus/-vertrag und alle Schreib-/Receipt-/Terminalwege bleiben unverändert.

Der gemeinsame Client baut diesen Korrekturlink ausschließlich aus dem exakten
String. Zulässig sind positive kanonische Dezimalstrings im bereits bestehenden
SQLite-Row-ID-Bereich bis9223372036854775807. Der alte numerische Wirewert dient
nur einer Plausibilitätskontrolle: `Number(exact)` wird niemals als Link-ID
verwendet oder zur Rekonstruktion einer verlorenen Zahl betrachtet. Ein
widersprüchlicher oder fehlerhafter neuer Wert fällt nicht auf eine andere ID
zurück. Auch fehlende/negative/Null-/gebrochene/zu große Werte ergeben keinen
Link und insbesondere kein `record_id=0`. Stattdessen steht fester Hinweistext
in derselben Aktionszelle, ohne automatischen Nachtrag oder Navigation.

Für alte Server bleiben sichere positive JS-Integer und bereits kanonische
String-IDs kompatibel. Eine alte unsichere JSONzahl ohne exakte Zusatzangabe ist
nicht rekonstruierbar und wird nicht mehr als möglicherweise anderer Record
verlinkt. Alte Clients ignorieren das additive Feld und lernen die neue
Genauigkeit nicht selbst; das Update benötigt die passende Clientdatei bzw.
ein bewusstes Neuladen, keine behauptete Fernsteuerung von Browsercaches.
Die bisherige serverseitige eigene IDauswahl außerhalb der200er-Liste sowie
Ablehnung fremder/fehlender/ungültiger IDs bleiben letzte Autorität. Kein
`adjustments.php`-/Selector-/Form-/CSRF-/Freigabe-/Parser-/Writerdelta.

Source/Public bleiben bytegleich, genau ihr bestehender Assethash und die
zugehörige Tabelle folgen den neuen Bytes. Alle anderen fünf Pins, Validator,
Startupadapter, aktuelle #66-Fehlerkennung, Pending-/Event-/Metadaten-/Retry- und
Sitzungsbindung sowie V5-/Runtimegates bleiben erhalten.

Ein grober erfundener Memory-/Renderervergleich unter PHP8.5.12/Node24.22.1 nutzt
beide tatsächlichen festen SQLtexte und die tatsächlichen extrahierten
Rendererfunktionen. Beide Webprojektionen sind gleich, liefern dieselben30
Ownrows und erhalten nach Entfernen von `id_string` alle alten Werte/Typen/Order.
IDs über der JS-Grenze und PHP_INT_MAX bleiben im erzeugten JSONstring exakt,
und der bestehende eigene Selector findet damit denselben synthetischen Record.
Der alte Renderer verlinkt zwei solcher Werte gerundet, der neue alle exakt.
Alte sichere Nummern/Strings bleiben verwendbar; fehlerhafte/fehlende/
widersprüchliche/unsafe-/Overflowwerte bleiben ohne Link. Andere Zellen und
Texte unverändert, kein Quell-/Transaktions-/Changezählerdelta. Der bestehende
grobe Publicclient-/Startup-/Pending-/Fehlerjourney sowie reine Assetmatrix
und notwendige Syntax/Quellerhaltung werden separat geprüft.

Keine neue Repositorytestsuite, CI, echte App-/Auth-/HTTP-/Browser-/Navigation-/
Datenbank-/Form-/Korrektur-/Produktivabnahme oder tatsächliche Mutation.
#14 bleibt für echte UI-/Integrationsabnahme und weitere nicht geprüfte Pfade offen.
