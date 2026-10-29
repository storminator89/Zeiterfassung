# Explizite Sprache in nativen Einstellungen — Teilumfang #62/#18

Quellbasis Main `de77af1e7da653112ba45fd5224c7c007bc36659`, Tree
`47c64ac2b65f69d91e46d854f086e7ca6e177cac`. Kein neuer Sprachvertrag,
keine Abhängigkeit von PR #239 und kein Laufzeit-/Hostwechsel.

`settings.php` lud bisher zuerst die gespeicherte Sprachdatei und bei einem
normalen Einstellungs-POST danach eine andere. Die vorhandenen globalen
Konstanten werden dadurch nicht ersetzt: Ein isolierter tatsächlicher
de→en-Ladeablauf unter PHP8.5.12 behielt den deutschen Einstellungstitel trotz
englischer Sessionauswahl und erzeugte Doppeldefinitionswarnungen.

Nun wird vor dem einzigen Laden der Settings-Sprachdatei dieselbe bereits
validierte explizite Auswahl ermittelt. Die vorhandene Passwortform-Erkennung
ist unverändert nur nach vorn verschoben; ein Passwortformular übernimmt wie
bisher keine zusätzliche POST-Sprachwahl. Eine vorhandene GET-Sprachwahl hat
bei normalen Einstellungsanfragen wie bisher im späteren Header Vorrang vor
POST/gespeicherter Auswahl. Der
Header bleibt unverändert und lädt danach dieselbe bereits geladene Datei mit
`require_once`. Ungültige explizite Werte behalten den bestehenden deutschen
Fallback über `valid_language`.

Keine Änderung an `initial_language`, Browser-/Accept-Language-Prioritäten oder
den drei Sprachdateien: gespeicherte Auswahl bleibt maßgeblich; Chinesisch
wird nicht neu automatisch erkannt. Die Änderung liegt weiterhin **nach** dem
bestehenden Security-/CSRF-Bootstrap, Konfigurationsladen und Angemeldet-Guard,
vor Ausgabe. Kein neues Ausgabestatement oder Redirect. CSRF-Ablehnung muss vor
einer Einstellungsänderung erfolgen. Passwortwriter, Logout/Revokation,
Rollenprüfung, SQL, Übertrag, Theme und Erfolgsablauf bleiben unverändert.
Passwortformulare behalten auch den bisherigen Zeitpunkt ihrer GET-Sprachwahl;
gemischte Passwort-/GET-Sprachfälle werden hier nicht neu aufgelöst. Bei normalen
Einstellungsanfragen erfolgt die Sessionauswahl nun früher vor dem ersten
Wörterbuch und Benutzer-Read. Keine pauschale Failure-Semantikgleichheit bei
einem späteren Infrastrukturabbruch wird daraus abgeleitet.

## Begrenzte Nachweise

Ein grober isolierter Quellfragment-Journey verwendet den tatsächlichen
vorhandenen CSRF-Zweig, die tatsächlichen Settings-/Header-Sprachladeblöcke
und echten Sprachdateien mit erfundenen Request-/Sessionarrays. Der
Abort-Adapter verweigert per Exception statt Request-Exit; es wird keine echte
Session eröffnet. Er deckt gültigen expliziten Wechsel, bisherige GET-Priorität,
unveränderten gespeicherten Wert/Validatorfallback, Passwortform-Abgrenzung
und vorherige CSRF-Ablehnung ab. Kein Output vor dem Ergebnisbericht; genaue
Quellinverse und Syntaxprüfung ergänzen den Nachweis.

Keine tatsächliche App-/Schlüssel-/Bootstrap-/Auth-/CSRF-HTTP-/Cookie-/DB-/
Passwortänderungs-/Persistenz-/Browser-/Layout-/Produktivabnahme. Keine neue
Testbatterie oder Repository-CI-Konfiguration. #62/#18 bleiben für weitergehende
Einrichtung und echte UI-/Integrationsabnahme offen; kein Produktionsdeploy.
