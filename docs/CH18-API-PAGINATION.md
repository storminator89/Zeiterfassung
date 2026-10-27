# CH-18 / #24 – strenge und begrenzte API-Paginierung

## Bewusster Vertrag für native API und inaktiven Kandidaten

`GET /api.php/timeentries` verwendet in beiden Adaptern denselben
**definitions-only** Parser `lib/api_pagination.php`, ohne Bootstrap, IO, Auth
oder DB-Verbindung im Helper. Der native Router bleibt aktiv, der
Framework-Kandidat bleibt unregistriert. Kein Framework-/Host-/Auth-Cutover.

| Parameter | Fehlend | Gültiger Bereich | Wire-Metadaten |
|---|---|---|---|
| `limit` | **100** | **0 bis 1000** einschließlich | Immer die wirksame Ganzzahl, auch **100** bei Auslassung. |
| `offset` | Effektiv **0** | **0 bis 2147483647** einschließlich | Bei Auslassung weiterhin `null`, sonst die validierte Ganzzahl. |

`limit=0` ist zulässig und liefert eine leere Seite, aber weiterhin `meta.total`.
Querywerte müssen aus ASCII-Dezimalziffern bestehen; führende Nullen sind
zulässig. Der gemeinsame Helper akzeptiert intern auch echte nichtnegative
PHP-Integer innerhalb der Grenzen. **Nicht** akzeptiert: Arrays/Objekte,
`null`, bool, Float, Leerstring, Whitespace, Vorzeichen (auch `+`), Dezimalpunkt,
Exponenten oder Werte oberhalb der jeweiligen Grenze. Es gibt kein
stillschweigendes Ignorieren, Kürzen, Clamping oder Casten vor der
String-Grenzprüfung. Überlange/extreme Zahlen werden vor Integerkonvertierung
verworfen. PHPs bestehendes Queryparsing bleibt unverändert: gleichnamige
skalare Schlüssel werden nach PHP-Semantik aufgelöst; dieser Patch führt keine
neue Duplicate-Key-/Raw-URL-Grammatik ein.

Ungültige Pagination endet **vor SQL** in HTTP **400** mit bestehender Form,
z. B. `{"success":false,"message":"Invalid limit. Use an integer from 0 to 1000."}`.
Der bestehende Bootstrap und die Bearerprüfung liegen davor und können weiterhin
ihre eigenen Fehler liefern; dies verspricht keine generelle JSON-Garantie
für Auth-/Infrastrukturfehler. Datumssyntax und deren bestehende Fehlergrenzen
werden nicht erweitert.

## Absichtliche Kompatibilitätsänderung und Clientumstellung

Bisher waren fehlendes `limit` und numerischer Offset allein **unbegrenzt**;
`meta.limit` war bei fehlendem Parameter `null`. Diese beiden Aufrufe liefern
jetzt höchstens **100** Zeilen und melden `meta.limit:100`. Offset allein ist
weiter gültig, aber **nicht** mehr unbeschränkt. Der bereits gemergte
SQLite-`LIMIT -1`-Fallback bleibt im Builder als defensive interne Altfallkante
erhalten; kein normal validierter HTTP-Aufruf braucht diese Kante noch.
Negative, dezimale und nichtnumerische Werte werden nicht mehr nach den alten
PHP-`is_numeric`-/Int-Cast-Regeln durchgereicht oder ignoriert.

Die aktuellen beiden Popup-Clients
`firefox_add_on/popup.js` und `time-tracker-extension_edge/popup.js` verwenden
explizit `timeentries?limit=1` nur zur neuesten Berichtszeilenanzeige. Dieser
unterstützte Aufruf bleibt unverändert. Im aktuellen Repository wurde kein
weiterer produktiver `/timeentries`-Verbraucher gefunden; unbekannte externe
Clients sind damit **nicht** überprüft. Die bisherige README-Aussage „all time
entries“ war für unparametrisierte Aufrufe gültig und ist nun bewusst ersetzt.

**100** ist eine gewählte Standardseitengröße (wie bestehende
Historien-/Korrekturleser), **1000** eine gewählte harte maximale Abrufgröße,
keine behauptete Benchmark-/Lastmessungsgrenze. **2147483647** ist eine portable
positive signed-32-bit-Obergrenze für Offset, keine Performancezusage für tiefe
Seiten. Count und Offset können bei großen Beständen weiterhin Arbeit kosten;
dies ist kein Cursor-/Rate-Limit-/Indexprojekt.

Clients, die alle Treffer benötigen, müssen explizit Seiten lesen, z. B.
`?limit=100&offset=0`, dann `offset=100`, `200`, …; bei Datumsfiltern dieselben
Grenzen mitsenden. Nach einer nichtleeren Seite um `meta.filtered` erhöhen und
bei `filtered < limit` (spätestens leerer Seite) stoppen; `limit=0` nicht als
Iterationsgröße verwenden. Neue Daten zwischen Requests können bei der
unveränderten Sortierung `startzeit DESC` und Offsetpagination weiterhin
Verschiebungen verursachen. Es wird keine stabile Cross-Request-Snapshot- oder
neue Tie-Breaker-Garantie eingeführt.

`meta.total` zählt weiterhin eigene datumsgefilterte Treffer **vor** Pagination,
`filtered` nur die Seitenzeilen. Eigener Nutzerscope, Berliner
Berichts-Starttagcallback, Filter, gespeicherte Reihenfolge, rohe Zeilen und
Count bleiben erhalten. `GET /attendance`, Token, Writer, Audit, Rollen-/Teamscopes,
Serverzeit, echte Pausen, Vier-Augen-Freigabe und Historie bleiben unangetastet.
Swagger und die betroffenen bestehenden Nachweise bilden den neuen Vertrag ab.

## Begrenzter Nachweis, keine Produktionsabnahme

```sh
php tests/api_pagination.php
php tests/api_booking_day.php
```

Nur zwei grobe Gruppen im neuen CLI-Nachweis: strenge Scalar-/Grenzablehnung
mit JSON-400 vor SQL, sowie bounded Default/Offset-only/`limit=1`/Maximum/Nullseite
auf einer anonymen SQLite-Memoryfixture mit eigenen/fremden Zeilen,
Datumsfilter, Reihenfolge, Count/Metadaten und unveränderter Fixture. Der
bestehende grobe Berliner Tagesnachweis bleibt separat erhalten. Die Programme
geben tatsächliche PHP-/SQLite-Version und Grenzen der Abnahme aus.

Die native Leserdefinition wird ohne `config.php` oder Routing geladen;
keine HTTP-/Login-/Bearer-/Session-/Controller-/HttpKernel-/Browser-/UI-
oder reale DB-Abnahme. Dormant-Parität ist gemeinsame Parserverwendung plus
Quellen-/Syntaxabgleich, kein neuer Kandidat-Integrationstest. Das aktualisierte
bestehende Kandidat-Rehearsal wird hier **nicht** ausgeführt. Keine Vollsuite,
neue GitHub-CI-Auslösung, Migration, Laufzeit-/Schlüsseländerung oder
Veröffentlichung. Exakter Commit, Laufzeit und tatsächlich ausgeführte Prüfungen
werden im eingefrorenen Übergabepaket aufgeführt, nicht aus diesen Befehlen als
bereits bestanden abgeleitet.
