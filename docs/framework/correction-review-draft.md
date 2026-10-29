# Begründung nach abgelehnter Korrekturentscheidung (#50 / #79)

Die bestehende Laravel-Entscheidungsform behält ausschließlich den unveränderten
Begründungstext nach einer bekannten `AttendanceError`-Ablehnung mit Status 400
oder 409. Der einzelne Writer darf noch nicht zurückgekehrt sein. Seine bereits
integrierte COMMIT-Phasengrenze macht jeden Fehler ab dem COMMIT-Versuch oder bei
unbestätigtem Rollback zu einem unbekannten Ausgang; solche Fehler erlauben
keinen Entwurf und keine automatische Wiederholung. Ein unbekannter
Entscheidungsausgang wird ausdrücklich als unbestätigt gemeldet; den
gespeicherten Stand neu prüfen.

Der vorhandene reine `correction_rejected_review_draft`-Projektor wird bytegleich
wiederverwendet: kanonische positive Ganzzahl-ID, String bis 4096 Byte, keine
Trunkierung/Normalisierung. Nur für denselben aktuell sichtbaren, offenen,
nicht eigenen Antrag unter bestehender Admin-/Supervisor-Sicht und verbundener
Korrekturfunktion erscheint der Text escaped im bisherigen Formular. Die
Entscheidung wird nie vorausgewählt. Keine Session/Flash/`old_input`-Speicherung,
kein zusätzliches Formular und kein neuer Request/Retry. Ein aktueller Fehler
unterdrückt eine veraltete `saved=1`-Erfolgsmeldung aus der URL.

Queuefilter, Paging, eigene Antragseingaben, Vorschau, Rücknahme, Route-/CSRF-/
Capability-/Auth-/V5-Grenzen, Writer/Core und native `adjustments.php` bleiben
unverändert. Insbesondere enthält das Paket keine Deltas des verweigerten PR
#239. Es implementiert weder zeitbegrenzte Vertretung noch Eskalation; #50 bleibt
offen. Der Text ist Eingabehilfe, keine Bestätigung einer Freigabe, Wiederholung
oder eines sicheren COMMIT-Ausgangs.

## Begrenzte Prüfung

Basis: Main `24f3f4f9d14d969d50eeb52c4a8211c52e840744`. Grobe isolierte
Komponentenprüfung mit PHP 8.5.12 / Laravel 13.38.0 verbindet tatsächlichen
Writer, Fassade, scoped Read, Controller und kompilierte Blade. Synthetische
Zeilen haben unbrauchbare Passwortmarker; kein Login, Schlüssel, Token,
Anwendungskonto oder Betriebsdatenzugriff. Bekannte Ablehnung/rollback nach DML,
fehlgeschlagener Rollback/COMMIT, Fehler nach physischem COMMIT und nach
zurückgekehrtem Writer, Erfolg sowie aktuelle Sicht-/Formgrenzen werden geprüft.
Das ist kein echter HTTP-/Auth-/CSRF-/Browser-/Recovery-/Produktivnachweis.
Tatsächliche Browserabnahme bleibt beim Nutzer; natürliche PR-/Main-CI wird
separat dokumentiert.
