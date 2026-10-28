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
| `assets/js/main.js` | `framework/public/assets/js/main.js` | 6130 | `d5786e6ebe049297ef5c38499a598104e2edf0e2b97960401daf21c604879365` |
| `assets/js/attendance.js` | `framework/public/assets/js/attendance.js` | 17346 | `659c1b1f44268726cb4efe9958dfbdd1f60fc5b3578913fc460df52bd2c7d374` |
| `framework/resources/js/chrono-local-web.js` | `framework/public/assets/js/chrono-local-web.js` | 2675 | `b55caaaa3396f51361426a039bfc929e15f0618f91027bc57ed1617252ad3924` |

Logo, CSS und die drei vorhandenen Clients bleiben unverändert. Keine neue
Buchungs-/Retry-/Timerlogik, kein JavaScript-Framework, CSS-Compiler oder Design.
Das Startgate lädt weiterhin ausschließlich den bestehenden Attendanceclient
aus `assets/js/attendance.js`; serverseitige CSRF-/Identitäts-/Pending-/Record-
Bindung und autoritative Antworten werden nicht ersetzt. Die vorhandenen
lokalen Kalender-/Chartpakete im öffentlichen Baum bleiben unberührt.

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
