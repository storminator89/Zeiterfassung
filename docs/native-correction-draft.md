# #50 — Native Eingaben nach bekannter Antragsablehnung erhalten

Explizite Union auf Main `ea3f5ac215ae381254c497ee6350251f2c7c48b0`,
Basisbaum `6eda68929e097950f8a465df573cd687b96a8dea` nach PR259.
Die ursprüngliche Draftquelle auf Main70437d8a bleibt nachvollziehbar; die nun
gemergte native Saldovorschau samt vollständigem Queue-/Own-Pending-GET-/
Snapshot-/Ausgabepfad wird bytegenau bewahrt. Dies ist keine Abhängigkeit von
PR239. Dessen Berlin-Helfer und Rücknahmeaktion werden weder vorweggenommen
noch als Umweg integriert.

## Nur dieselbe Antwort, keine neue Antragspolitik

Die native `adjustments.php` zeigt nach bekannter fachlicher
`AttendanceError`-Ablehnung mit Kernstatus400/409 die begrenzten Eingaben wieder
im vorhandenen Antragsformular. Voraussetzungen sind dieselbe gültige eigene
Auswahl, POST mit exakt `action=request`, noch nicht zurückgekehrter Kernbefehl
und bestätigtes ROLLBACK ohne offene Transaktion. Die bestehende HTTP-/Fehler-
und Erfolgsweiterleitungssemantik wird nicht umgestellt.

Kein Entwurf bei GET, ungültiger/fremder Auswahl, Review, Rücknahme, Erfolg,
401/403 oder technischem/unklarem Ausgang. Ein bereits zurückgekehrter Kernbefehl
führt auch bei einer späteren geeigneten Fehlerklasse nicht zur Wiederanzeige.
Bei fehlender Rollbackbestätigung wird nichts wiederbefüllt. Das ist kein neuer
Transaktions-/Commitfehler- oder Wiederanlaufmechanismus und bestätigt keinen
unklaren Schreibausgang. Es wird kein Befehl automatisch wiederholt oder gesendet.

Der reine bestehende Laravel-Draftbody aus PR220 wurde mechanisch nach
`lib/correction_request_draft.php` ausgegliedert; der bisherige
`CorrectionsPage::rejectedRequestDraft()`-Wrapper delegiert unverändert.
Keine zweite Normalisierungsregel: nur ursprüngliche Stringwerte für
Beginn/Ende/Pause bis128 Bytes, Grund bis4096 Bytes und die exakte Stornoauswahl
`'1'`. Keine Coercion, Trimmung, Offsetumrechnung oder stille Kürzung; ungeeignete
oder überlange Werte fehlen im Entwurf. HTML-Ausgabe bleibt mit dem vorhandenen
Escaper geschützt. Der Backendparser, die tatsächlichen Grenzwerte und die
Vier-Augen-Entscheidung bleiben alleinige Autorität; Anzeige macht ungültige
Eingaben nicht gültig.

Der Entwurf lebt nur als lokales Array in dieser einen Antwort. Keine Session-,
Flash-, LocalStorage-, URL-, Log- oder Datenbankpersistenz. Bestehende Recordwahl,
Queuefilter, CSRF-Felder, Formularnamen, Statuscodes, Audit-, Auth-/Writer- und
Rollenregeln bleiben erhalten. Keine neue Route, UI-Welt, JavaScriptaktion,
Bibliothek, Sicherheits-/Host-/Runtimeeinstellung oder V5-Freigabe.

## Grober isolierter Nachweis

PHP8.5.12: die zehn ursprünglichen Fälle und ein zusätzlicher expliziter
Own-Pending-GET-Vorschaufall nach der Union mit der tatsächlichen nativen
Seitendefinition und dem unveränderten Korrekturkern gegen anonyme kanonische
SQLite-`:memory:`-Fixtures.
Nur die beiden Bootstrap-Includes (`config.php` und `app_ui.php`) werden für
diesen Nachweis ausgelassen, feste Includewege an die geprüfte Quellwurzel
gebunden und reine Rendering-/CSRF-Ausgabehelfer bereitgestellt. Es ist keine
Authentifizierungs-, Session- oder CSRF-Middlewareabnahme.

Beobachtet: bekannte400-Zeit-/Grundablehnung und409 für Storno ohne bestehende
Buchung, exakte Herbstoffset-/Textwerte, HTML-Escaping, erhaltene abgewiesene
Stornoauswahl, kein Formular nach fremder Auswahl, kein Entwurf bei GET/Review/
Rücknahme und unbekanntem Writerfehler, Auslassung falscher Typen/Übergrößen.
Eine ausdrücklich synthetische Ausnahme vor COMMIT nach zurückgekehrtem
Kernbefehl prüft nur den Anzeigeausschluss, keinen realen Commitverlust.
Geschäfts-/Auditzeilen und native Sessionglobals bleiben in diesen abgewiesenen
bzw. zurückgerollten Fällen unverändert; Integritätsprüfung und Cleanup bestehen.
Der zusätzliche bereits gemergte Vorschaupfad zeigt nach der Union unverändert
`+01:00` für den einen synthetischen eigenen offenen Antrag und verändert keine
Quelldaten. Sieben reine Eingabeformen stimmen exakt mit dem ursprünglichen
Laravelbody und dem neuen delegierenden Wrapper überein. PHP-Lint/Diffcheck bestehen.

Eine frühe Fixture erwartete fälschlich bereits beim Antragsstellen einen
Überschneidungsfehler. Der unveränderte Kern prüft dies erst bei der Entscheidung;
der Memoryprozess beendete sich deshalb regulär nach synthetischem Erfolg.
Der409-Fall wurde auf den tatsächlich vorhandenen Storno-ohne-Ziel-Guard
korrigiert, ebenso eine ungültige GET-`record_id=0`-Fixture. Keine Produktregel
wurde angepasst, um den Nachweis grün zu machen.

Keine echten Konten/Credentials/Datei- oder Betriebsdatenbank, tatsächliche
Bootstrap-/Login-/HTTP-/Browser-/DOM-/CSRF-/JWT-/Cookie-/Layoutabnahme,
Prozesskonkurrenz oder Lost-COMMIT-/Störprofilabnahme. Keine erfolgreiche reale
Antrag-/Reviewreise, kein Deployment oder Runtime-/V5-Cutover. #50 bleibt offen;
separate Saldovorschau, PR239-Grenze und weitere Fachfunktionen bleiben getrennt.
