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
| `assets/js/attendance.js` | `framework/public/assets/js/attendance.js` | 19203 | `4e5f763c32b9fe903cb63d28c05ca916ec833ca6de76ad4ee8c8e157ef53480b` |
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
