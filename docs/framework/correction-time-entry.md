# Geführte Korrektur-Zeitwahl (#50/#79)

Optionaler aufklappbarer Eingabehelfer in der bestehenden Laravel-Korrekturkarte,
kein neuer Korrekturweg. Die sichtbaren manuellen ISO-Felder und ihre bisherigen
POST-Feldnamen bleiben maßgeblich. Nur **In ISO-Feld übernehmen** kopiert einen
Wert; der Helfer sendet nichts. Seine unbenannten Bedienelemente stehen außerhalb
des Formulars und verändern dessen Validierung ohne JavaScript nicht.

Datum und Uhrzeit werden ausdrücklich als Europe/Berlin behandelt, unabhängig
von der Gerätezeitzone. Die vorhandene Browser-IANA-Zone liefert die Offsets;
jeder Kandidat muss exakt zu denselben Berliner Komponenten zurückführen.
Die doppelte Herbststunde verlangt ausdrücklich Erstes/Zweites Mal samt Offset.
Eine Frühlingslücke wird nicht verschoben oder übernommen. Bei fehlender
Browserunterstützung bleibt nur das unveränderte manuelle Formular sichtbar.
Storno blendet den Helfer aus; vorhandene ISO-Werte bleiben unverändert.

Kein Controller-/DTO-/Parser-/Reader-/Writer-/SQL-/Auth-/Routen-/Assetdelta.
Der gemeinsame `correction_time`-Parser und Korrekturdienst bleiben alleinige
Backendautorität; Anzeigehilfe ist keine Berechtigungs- oder Freigabeprüfung.
Vorhandene Auswahl, Queue, Dauervorschau, Vieraugenentscheidung und UI bleiben
erhalten. Kein Saldo, Delegations-/Rücknahmeverfahren oder Zeitzonenregelwerk wird
neu implementiert. Browser- und Server-TZ-Daten können unterschiedliche Versionen
haben; der gespeicherte explizite ISO-Offset bleibt der bestehende Vertrag.

Nur grober erfundener Script-/DOM-Ablauf und notwendige JS-/Blade-Syntaxprüfung;
keine echte DB, Konten, Secrets, HTTP-/Session-/Browserabnahme, Server, GitHubwrite
oder Aktivierung. Ein späterer tatsächlicher Browserjourney bleibt gesondert.


## Eingefrorener lokaler Stand und begrenzte Prüfung

Basis ist der am 28.10.2026 erneut lesend bestätigte GitHub-Main
`4d78a7003ca8e265fe7657c3e8af9ab762cd6e2c`, Baum
`ba3a5bdbe563940d10b340c3f8b8680901e62ce7`. Genau drei Pfade: bestehende
Korrektur-Bladevorlage, neues Inline-Partial und dieses Dokument. Die bereits
begonnene Implementierung wurde wiederaufgenommen, nicht parallel neu gebaut.

Die additive Rückprojektion der vorhandenen Bladevorlage ergibt bytegenau Main:
Auswahlschutz, ISO-Feldnamen, Storno, CSRF, Statusfilter/Seiten, Antragskarten,
Dauervorschau und Sichtbedingung der Vier-Augen-Entscheidung bleiben erhalten.
Der Helfer bleibt ohne JavaScript, ohne unterstützte Browserzone, bei ungültiger
Buchungsauswahl oder bei abgeschaltetem Korrekturformular unsichtbar. Er erteilt
keine Sicht- oder Schreibrechte. Storno verbirgt ihn, löscht nur seine unbenannten
Hilfswerte und verändert die manuellen ISO-Felder nicht.

Ein vorhandener grober erfundener DOM-/Scriptablauf mit echten
`Intl`-Berlinregeln prüft explizites Übernehmen, beide Herbst-Offsets erst nach
bewusster Wahl, Zurückweisung der Frühlingslücke und ungültiger Daten ohne
ISO-Umschreiben, Storno und manuellen Rückfall. Die vorhandenen Läufe unter
UTC, America/New_York und Asia/Tokyo belegen Gerätezeitzonenunabhängigkeit
innerhalb dieses Modells; es ist ausdrücklich kein tatsächlicher Browserlauf.
Zur abschließenden Bindung an dieselbe Quelle wurde nur der vorhandene grobe
UTC-Ablauf erneut ausgeführt, keine neue Testsuite ergänzt.

Notwendige Syntaxprüfung: Node **24.22.1**, PHP **8.5.12** und Bladecompiler aus
Laravel **13.38.0**; JS-Syntax, beide kompilierten Bladeausgaben und
`git diff --check` bestanden. Kein neuer Frontendbuild oder Composerinstall.

Keine reale DB, Konten, Zugangsdaten oder Geheimnisse gelesen/geändert; keine
HTTP-/Session-/Login-/Browser-/CSRF-/Produktivabnahme, kein Serverstart, keine
Aktivierung und keine GitHubschreibvorgänge oder neuen CI-Läufe. Der lokale
Dreipfadstand bleibt uncommittet. Browser-IANA und Server-TZ-Versionen bleiben
möglicherweise verschieden. Der Backendparser bleibt allein maßgeblich.
Kontosaldovorschau, Rücknahme, Vertretung/Eskalation und vollständiges #50/#79
sind damit nicht erledigt oder freigegeben.
