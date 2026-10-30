# Vorhandene gemeinsame UI-Assets im Laravel-public-Webroot

Begrenzte Quellergänzung auf Main `e372faab83fb5eb0703af6cbece37858c719b09f`.
Sechs bereits referenzierte lokale Dateien fehlen im normalen `framework/public/`-
Webroot; die bisherige synthetische Router-Whitelist liefert sie stattdessen aus
Repositoryquellen. Diese Ergänzung macht nur die festen statischen Kopien bereit.
Keine Fixture wird zur echten Installation umgedeutet; kein Repositoryrouter,
zusätzlicher Webroot oder Zugriff auf private Frameworkquellen wird eingeführt.

## Bytegleiche Quellen

| Bestehende Quelle | Neue öffentliche Kopie | Bytes | SHA-256 |
| --- | --- | ---: | --- |
| `assets/kolibri_icon.png` | `framework/public/assets/kolibri_icon.png` | 20733 | `b450f436baec5dbde264c4fe21d9001c0b466ff44ae1311f5919315bd32430e4` |
| `assets/css/main.css` | `framework/public/assets/css/main.css` | 19247 | `d293dcf9d31e0adfc0c6cda1c2f03f4c57afe325c79e223794460dc9f94c4e6f` |
| `assets/css/app_ui.css` | `framework/public/assets/css/app_ui.css` | 6546 | `4f8bf76f83a5c5d3ea3807818a792aa2b4c12ec83aaf46a33342d1aadf4adc1e` |
| `assets/js/main.js` | `framework/public/assets/js/main.js` | 9767 | `2f9ed4299e2acca7b8b472c3099f5b14e499ed221d33b4be77ecabfade9f5ae3` |
| `assets/js/attendance.js` | `framework/public/assets/js/attendance.js` | 24265 | `86b759f00e3a9aa02b2d2f96d0ab1acf815114becb7691c7ccfb2c9997730b17` |
| `framework/resources/js/chrono-local-web.js` | `framework/public/assets/js/chrono-local-web.js` | 2984 | `9940b5723b21a2fa21f39db9471523ff5818bc6946a480607c2e5cac20516b3b` |

Logo, CSS und die drei vorhandenen Clients blieben im ursprünglichen Paket unverändert. Keine neue
Buchungs-/Retry-/Timerlogik, kein JavaScript-Framework, CSS-Compiler oder Design.
Das Startgate lädt weiterhin ausschließlich den bestehenden Attendanceclient
aus `assets/js/attendance.js`; serverseitige CSRF-/Identitäts-/Pending-/Record-
Bindung und autoritative Antworten werden nicht ersetzt. Die vorhandenen
lokalen Kalender-/Chartpakete im öffentlichen Baum bleiben unberührt.

**Begrenzter #57-Nachtrag auf Main `619ef50a`:** Der gemeinsame
Anwesenheitsclient und seine bytegleiche öffentliche Kopie erhalten die
initiale Fail-closed-Pendingprüfung und Vor-POST-Speicherbestätigung aus
[lokaler Webintegration](local-web-ui-integration.md#57-kein-post-ohne-bestätigte-ablage-derselben-ereignis-id).
Der vorhandene Laravel-Startupadapter und seine öffentliche Kopie markieren
Initialfehler ebenfalls dauerhaft für dieses Dokument als gesperrt und zeigen
den gleichen Klärungs-/Reloadhinweis. Die Tabelle oben und ausschließlich die
beiden entsprechenden bestehenden `FreshWebConfiguration::assets()`-Hashes sind
auf diese neuen Bytes aktualisiert.
Das ist keine alternative Assetzulassung oder Runtimefreigabe; alle übrigen
Assetpins, Quell-/Public-Paare und Kompositionsprüfungen bleiben unverändert.

## Herkunft und Lizenz

Die sechs Dateien stammen ausschließlich aus demselben Repository, nicht aus
neuen Downloads oder einem externen Paket. Vorhandene Dateikommentare werden
weder entfernt noch verändert. Die unverändert mitgelieferte Projektdatei
[`LICENSE`](../../LICENSE) enthält die MIT-Lizenz und Copyright (c) 2023 Patrick
Meyhöfer; SHA-256 `d26765af12de0908bacaa04725bf73ab3efb31f97007763536b1df4b37e9ead6`.
Dieser Lizenz-/Herkunftsnachweis beschreibt die vorhandenen Repositorydateien;
er fügt keine fremden Logo-, Font- oder Bibliotheksrechte hinzu. Beim Verteilen
eines gesonderten Asset-only-Artefakts sind die vorhandenen Lizenz-/Copyright-
Hinweise ebenfalls beizulegen. Das hier gelieferte Quellpaket behält LICENSE.

## Aktualisierung und Grenzen

Bei späteren Änderungen an einer Quell-Datei muss die öffentliche Kopie im
gleichen geprüften Release bytegleich aktualisiert werden; kein Laufzeit-Copy,
Symlink, Source-Fallback oder dynamischer Dateipfad. Eine spätere echte
Webkomposition kann diese festen Kopien vor ihrer Routenfreigabe prüfen.

Geprüft sind Größen/Hashes/Quellblobs und JavaScript-Syntax ohne Ausführung.
Keine neue Testsuite, App-/Router-/Auth-/HTTP-/Browser-/Mobil- oder visuelle
Abnahme, keine Schlüssel, Konten, Datenbank oder Laufzeiteinstellung verändert.
Die echte Webkomposition und V4-Schreibgates bleiben getrennte Voraussetzungen.
Andere CSS-/Font-CDNs sind nicht lokalisiert; keine vollständig CDN-freie oder
bereits produktiv aktive Laraveloberfläche behauptet.

Die bytegleich kopierte `main.css` enthält vier bereits vorhandene Stellen mit
nachgestellten Leerzeichen (Quellzeilen94,444,578,582). Der normale
`git diff --check` meldet diese geerbten Stellen; sie werden nicht durch eine
heimliche Normalisierung der Kopie verändert. Die übrigen neuen Dateien und
die Dokumentation bestehen die unveränderte Whitespaceprüfung.


## #51-Nachtrag: tatsächlichen Laravel-Public-Client synchronisieren

Enger Quellnachtrag auf Main `667b8f834c4e7f596cab6c4fd11d629a8f224b38` (nach PR #245).
PR #243 hatte den gemeinsamen Repository-Client für optionale Startmetadaten
aktualisiert, die vorhandene feste Public-Kopie und ihren exakten Assetpin jedoch
noch auf den alten Bytes belassen. Mit dem bisherigen Public-Client blieb das
neue Laravel-Metadaten-Fieldset deaktiviert; der ältere Standardstart funktionierte
weiter. Dies war ein konkret bestätigter Source-/Public-Drift, keine behauptete
Produktionsbeobachtung.

Die Public-Kopie ist jetzt **bytegleich** mit dem bereits geprüften gemeinsamen
`assets/js/attendance.js`. Nur dessen bestehender SHA-256-Eintrag in
`FreshWebConfiguration::assets()` sowie die Tabellenzeile oben werden angepasst.
Kein neuer Client, Paket-/Bibliotheksversionswechsel, Startupadapter, dynamischer
Copy-/Fallbackweg oder abgeschwächter Validator. Alle fünf übrigen Assetpins,
Source-/Public-Paare, Kompositions-, Authentifizierungs-, Terminal- und Domain-
regeln bleiben unverändert. Die Aktualisierung aktiviert kein Betriebsprofil.

Ein grober tatsächlicher Public-Client-/unveränderter Startupadapter-VM-Nachweis
mit erfundenem DOM, Speicher und Transport bestätigt den Vorher-Nachher-Unterschied,
optionalen Metadatenstart, Antwortverlust nach angenommener Speicherung, Reload
und identischen Pending-Replay sowie Speicher-/fremde-Pending-Verweigerung. Die
vorhandene statische Assetvalidierung akzeptiert jeweils passende alte/neue
Kopie+Pins und verweigert gemischte bzw. veraltete Bytes. Syntax/Diffcheck und
vollständige Quellerhaltung sind geprüft. Keine echte Login-/HTTP-/Browser-/
Download-/Produktionsabnahme, realen Daten, Keys, Datenbank oder neuen CI-Läufe.

## #66-Nachtrag: Antwortkennung in Anwesenheitsfehlermeldungen

Quellschritt auf Main `92a38f3dd4891d0f7037b6559e13222691da37dd`, Baum
`1d9c5cf41b33bab3eed83b3bc5e986bc85caabbe`: Der unverändert gemeinsame
Anwesenheitsclient ergänzt nur bei5xx mit formatgültigem32-Hex-`X-Request-ID`
den vorhandenen Fehlertext um eine ausdrücklich nicht als Buchungsnachweis
ausgewiesene Anfragekennung. Fehlender/ungültiger Header und alle bisherigen
Kontroll-/Pending-/Replay-/Erfolgszweige bleiben unverändert. Details und Grenzen:
[native-controlled-error-correlation.md](../native-controlled-error-correlation.md#66-nachtrag-anfragekennung-im-vorhandenen-anwesenheitshinweis).

Source/Public bleiben bytegleich; nur dieser bestehende exakte Assethash und
die Tabellenzeile wurden aktualisiert. Alle fünf anderen Pins, der eigentliche
Validator, Startupadapter, Auth-/Domain-/V5-/Runtimekonfiguration und APIs bleiben
erhalten. Kein neuer öffentlicher Pfad, dynamischer Copy-/Fallbackweg, anderer
Client, Log- oder Host-/Securityschalter und keine Laufzeitaktivierung.

## #14-Nachtrag: exakte Korrekturlinks der jüngsten Zeilen

Quellschritt auf Main `830b30f92066ba152e1d6f1aedd8da7a7fe671d5`, Baum
`c332690c347fe133175753a2544a7897a754b47e`: Die vorhandenen nativen/Laravel-
Webstatusreads ergänzen nur einen exakten `id_string` derselben eigenen Zeile.
Der gemeinsame Client verwendet ihn statt einer gerundeten JS-Nummer für
Korrekturlinks; unklare/fehlerhafte IDs werden ohne Link angezeigt, niemals als
Record0. Alte sichere Nummern und kanonische Strings bleiben verwendbar.
Details und Grenzen im [#14-Nachtrag](../CH30-EXPECTED-RECORD.md#14-nachtrag-jüngste-korrekturlinks-behalten-die-exakte-zeilen-id).

Die feste Public-Kopie ist bytegleich; nur ihr bisheriger exakter Attendancehash
und die Tabelle oben ändern sich. Alle anderen Pins und Validatoren sowie
Startup-/Pending-/Event-/Retry-/#66-/V5-/Runtimegrenzen bleiben erhalten.
Kein neuer öffentlicher Assetpfad, dynamischer Fallback oder Betriebszugang.


## #79/#82: tatsächliches NEW-Installationsboot mit aktuellen Assetpins

Der [NEW-V5-Kerneljourney](fresh-installation-rehearsal.md) reproduziert zwei
veraltete feste Pins auf Main `3a5cef6d`: `main.js` und der bestehende
Kalenderrenderer waren bereits separat geprüft/gemergt, ihre Bootgates jedoch
noch auf älteren Bytes. Nur diese beiden Digestwerte werden auf die belegten
aktuellen Main-Blobs ausgerichtet; die Assetdateien und sämtliche Validatoren
bleiben bytegleich. Die obige `main.js`-Zeile beschreibt die aktuelle feste
Native-/Public-Kopie. Manipulierte Bytes bleiben vor DB-/Routenfreigabe verweigert.
Kein Runtime-/Host-Cutover und keine zusätzliche Asset- oder Securityfreigabe.
