# Explizite Sprache in nativen Einstellungen — Teilumfang #62/#18

Ursprüngliche Quellbasis Main `de77af1e7da653112ba45fd5224c7c007bc36659`, Tree
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
Auch ein Passwortformular löst inzwischen die vorhandene explizite GET-Sprachwahl
vor dem ersten Wörterbuch und Benutzer-Read auf. Zuvor konnte eine bekannte
Passwortformablehnung noch das gespeicherte Wörterbuch verwenden und der spätere
Header ein zweites Wörterbuch laden: Die Sessionwahl war dann neu, Titel und
Fehlermeldung blieben aber alt, begleitet von Doppeldefinitionswarnungen.
Die bestehende POST-Sprachwahl bleibt bei Passwortformularen ausgeschlossen;
gemischte POST-Payloads werden weiterhin vom unveränderten Passworthelper
verweigert. Die validierte GET-Priorität und ihr deutscher Invalid-Fallback ändern
sich nicht. Bei einem frühen Abbruch vor dem Header wird die explizite GET-Wahl
nun ebenfalls schon berücksichtigt; pauschale Gleichheit des bisherigen
Präferenzzustands bei Infrastruktur-/Logoutfehlern wird nicht behauptet.

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

### Additiver Passwort-/GET-Sprachstand

Basis Main `2ea273e6eddd294ff210a25b7bf65d535337aee5`, Tree
`35f86aaf85c32cc149cae6f2b017ff2ca7aad109`, 29.10.2026. Nur die vorgezogene
GET-Auswahl in `settings.php` und dieses Dokument ändern sich. Klassifikation,
vollständiger Passwort-/Logout-/POST-/SQL-/Themekörper, Header, Security, Validator
und Sprachdateien bleiben bytegleich. Kein Bezug zur getrennten Korrekturarbeit
in PR #239; keine Änderung an Auth-, Runtime-, Schlüssel- oder Hostgrenzen.

PHP 8.5.12: elf grobe Baseline-/Kandidatenfälle verwenden den tatsächlichen
extrahierten Settingskörper vor dem Header, den tatsächlichen Passworthelper mit
absichtlich ungültiger Form und dessen 400-Ablehnung **vor** Quoten-/Passwortarbeit,
den vorhandenen CSRF-Zweig, Header-Sprachblock und DE-/EN-/ZH-Wörterbücher.
Die einzige Importanpassung im externen Harness lädt die unveränderten
Helperdefinitionen vorab vom Quellpfad; kein Anwendungsbootstrap wird ausgeführt.
Eine query-only SQLite-Memoryzeile mit unbrauchbarem Passwortmarker bleibt
unverändert, keine Transaktion bleibt offen. Kein Passwort, Hash, Schlüssel oder
Token wird erzeugt; keine echte Session gestartet.

DE→EN, EN→ZH, ungültiger GET-Fallback und gemischtes POST zeigen jetzt konsistente
Titel/Fehler ohne Doppeldefinitionen; die alte DE→EN-Konstellation reproduziert
den falschen Titel und Warnungen. Ohne GET bleibt Passwort-POST-Sprache
ausgeschlossen. Normaler GET/POST, bisherige GET-Priorität und gespeichertes ZH
bleiben identisch; CSRF-Ablehnung erfolgt unverändert vor jeder Sprachmutation.
Keine tatsächliche Passwortänderung/-prüfung, Liveanmeldung, HTTP-/Session-/
Browser- oder Betriebsabnahme wird aus diesen Komponentenfällen abgeleitet.
