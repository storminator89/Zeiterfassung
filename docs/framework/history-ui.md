# FW-08 / #79 – bestehende Änderungshistorie, weiterhin inaktiv

**Fortsetzung des vierteiligen Prototyps `00f99b507fb2086634b56fa1b3efbc0c84579c9f`,
keine neue Oberfläche und keine Webaktivierung.** Ausgangspunkt ist der vom
Publisher bestätigte Main `165ce38008c3a4a54bc2b4fca4fa2c63160d8a78`.
`history.php`, `lib/app_ui.php` und die mit PR126 übernommenen nativen
`lib/history_filters.php`-Definitionen sind die Markup-/Vertragsquelle.
Die Originalseiten und ihre Assets bleiben unverändert.

## Zwei getrennte, additive Teile

1. **Reiner Lesevertrag:** neues unveränderliches `HistoryQuery` und ausschließlich
   die zusätzliche Methode `ChronoReads::historyQuery(HistoryQuery $query): array`
   samt Implementierung. Das vorhandene `history(int $before = PHP_INT_MAX)`
   bleibt unverändert; keine Änderung an Snapshot, Guard, Rollen oder anderen
   Readmethoden. Gemeinsame Dateien dürfen bei Integration nur diesen Methoden-
   zusatz erhalten, niemals durch ältere Dateiversionen ersetzt werden.
2. **Vorhandene Darstellung:** die ursprünglichen vier Dateien
   `LocalWebHistoryController`, `HistoryPage`, `chrono.history` und dieses Dokument
   werden fortgeführt. Die bestehenden PR126-Filter, Fehler-/Leerzustände und
   Paginglinks ergänzen die bereits übernommenen Auditkarten, kein neues Design.

Das Paket registriert weder Controller noch Route, Fixture, Provider, Layout
oder Featureflag. Es ist **statisch vorbereitet, nicht funktionsabgenommen**.

## Kanonische Filter, unveränderte Sichtrechte

`HistoryQuery` hat einen privaten Konstruktor und entsteht ausschließlich aus
`fromQueryString(string)`. PHP `parse_str` bildet wie natives `$_GET` die
unveränderte `QUERY_STRING` ab; danach übernimmt ausschließlich
`chrono_history_filters` die bestehende Validierung. Dadurch werden leere
optionale Formfelder nicht durch Laravels globale Leerstring-zu-null-Konversion
ungültig, und ein NUL wird nicht vor dem nativen Parser weggetrimmt. Keine
Middleware wird deaktiviert oder umkonfiguriert. Es gibt keinen Konstruktor für
beliebiges SQL, ungeprüfte Filter oder eine vom Request behauptete Identität.

Person-, Bereichs-, Datensatz-, Antrags-, Datums- und Cursorfilter behalten
sämtliche nativen Bedeutungen: alle gemeinsam mit `AND`, positive IDs,
Änderungszeitpunkt in `Europe/Berlin`, einschließlich beider Kalendertage mit
exklusiver Folgetagsgrenze. `request_id` bezeichnet nur die eigene Auditzeile
von `time_adjustments`, keine automatische Korrelation zu Buchung oder Sitzung.
Es gibt keinen Lookup in aktuellen Buchungs-/Antragstabellen und keine neue
Feldänderungs-, Such-, Export- oder Korrelationsfunktion.

Der Reader ruft `HistoryQuery::statementFor` ausschließlich innerhalb seines
vorhandenen DEFERRED-Snapshots mit der aus dem Guard stammenden, dort frisch
nachgeladenen Identität/Rolle auf. Die Methode delegiert an
`chrono_history_query`; SQL und Parameter werden nicht nochmals aufgebaut.
Scope bleibt: eigene Einträge, direkt zugeordnete Supervisor-Teamkonten oder
alle Einträge für aktuelle Admins. Admins sehen weiterhin Benutzer-ID `0` und
protokollierte gelöschte Konten. Keine rekursive Teamsicht, neue Berechtigung,
Journalmutation oder zusätzliche Kontoabfrage.

Ungültige Filter liefern ein Fehlerobjekt, niemals eine unbeschränkte Liste:
der Controller überspringt die Auditabfrage und antwortet mit HTTP 400;
der Reader verweigert ebenfalls die Auditabfrage. Ein direkter Aufruf von
`statementFor` mit ungültigen Filtern wird vom kanonischen Helfer abgelehnt.
Die sonstigen bestehenden Chrome-Leseanforderungen sind damit nicht abgeschaltet.

Absteigende `id < before`-Reihenfolge und `LIMIT 100` bleiben gleich. Nur bei
**genau 100** Zeilen erscheint „Ältere Einträge“ mit der letzten ID; es gibt keine
zusätzliche Existenzabfrage. `chrono_history_url` erhält dabei alle Filter.
„Neueste Treffer“ entfernt ausschließlich den Cursor; ein neues Filterformular
sendet keinen alten Cursor. „Filter zurücksetzen“ führt weiter zu `history.php`.

## Originalkarten und unveränderte Payloadtexte

Titel, Baselinehinweis, Klassen, Entitätslabels, Akteur/Begründung und aufklappbare
Vorher-/Nachherbereiche mit umbrechenden `pre`-Blöcken bleiben erhalten.
Unbekannte Entitäten bleiben als Originaltext sichtbar. Der reine native
`attendance_local`-Formatter liefert weiterhin Berliner Zeit mit Offset.
Es werden nur Definitionen geladen, niemals native Bootstrap-/Headerdateien.

`HistoryPage` enthält nur bereits autorisierte Anzeigewerte, native Filtertexte,
Fehler/Labels und relative Paginglinks. Gespeichertes JSON wird **nicht** geparst,
neu codiert, formatiert oder in Skripte eingebettet. Blade escaped alle dynamischen
Werte, einschließlich Filter, Links und JSON. Nur `null`/fehlende Snapshotfelder
bekommen „Kein vorheriger Eintrag“ oder „Storniert“; leere Strings bleiben leer.
Kein HTML-Rohoutput, Downloadendpoint, Logger oder Writer kommt hinzu.
Das sensible Journal wird nicht in Prüfprotokolle oder Beispieldaten kopiert.

## Weiterhin gesondert blockierte Webintegration

`AttendancePage` erlaubt `history.php` noch nicht als aktive Seite; der Controller
ist damit absichtlich **nicht lauffähig**. Gemeinsame Integration müsste separat
Active-Page-Whitelist, bestehenden Sidebar-Aktivzustand, geschützten GET-Registrar,
vollständige Seitennavigation bei Authfehlern und den expliziten Fixturerouter
reviewen. Dieses Paket verändert nichts davon, täuscht keine andere aktive
Seite vor und erstellt keine zweite Chromeansicht. `routes/web.php` bleibt leer,
die ausgelieferte Frameworkroute bleibt ausschließlich `/up`.

Ein späterer GET braucht weiterhin persistentes Laravel-Sessionblocking und
`RefreshLocalAccount` → `auth:web` → `auth.session`. Keine neue Session-/CSRF-/
Loginimplementierung, kein Guard-/Credential-/Cookie-/Token-Injizieren, kein
Produktions-, Host-, Sicherheits- oder Authcutover. Die bestehenden WIP-/
Browser-/Authentifizierungsholds werden weder aufgehoben noch umgangen.

## Begrenzter grober Nachweis

Am 27.10.2026 unter PHP 8.5.12 bestanden: Syntaxprüfung der neuen/geänderten
PHP-Definitionen, rein statische Blade-Kompilierung (19 escaped Ausgaben) und
Syntaxprüfung des Ergebnisses, ein zusammenhängender reiner Filter-/SQL-/URL-
Vertragsnachweis und `git diff --check`. Kein Auswerten des kompilierten Templates, Controllerdispatch,
Laravelboot, DBzugriff, HTTP/Server/Browser/Login, Session-/Credentialkontext,
Funktionstest oder neuer CI-Lauf. Die Ergebnisse stehen im separaten eingefrorenen
Übergabenachweis; Kompilierung ist keine visuelle oder funktionale Abnahme.

Dieses Paket ist ein inaktiver #79-Baustein, **keine** fertige Migration, Merge-
oder Produktionsfreigabe. Eine spätere autorisierte grobe Ansicht muss die
bestehende UI und den realen Authweg berücksichtigen; keine Mikrotestmatrix.
