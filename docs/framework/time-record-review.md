# #79 / #55 — vorhandene Prüfhinweise in der Laravel-Liste erhalten

## Separater Team-Leseflow im bestehenden Supervisorbereich

`supervisor.php?team_review=1` bietet zusätzlich eine ausdrücklich aufgerufene,
rein lesende Laravel-Karte mit vorhandenen Prüfhinweisen und ausgewählten
Originalzeit-/Pausenwerten. Die bisherige Berichtssperre bei ungültigen oder
konfliktbehafteten Daten bleibt vollständig bestehen; keine Teilsumme wird
dadurch abschließend und kein Datensatz repariert. Ohne den exakten rohen
Querystringwert `team_review=1` erfolgt keine zusätzliche Teamprüfung. Andere
vorhandene Werte einschließlich Arrays/Leerzeichen liefern 400, fehlende
optionale Fähigkeit oder technischer Lesefehler 503 statt einer sauberen Liste.
Authentifizierungs-/Autorisierungsverlust wird unverändert weitergegeben.

Die optionale Schnittstelle `TeamTimeRecordReviewReads` erweitert nicht den
allgemeinen `ChronoReads`-Vertrag. Ihr eigener bestehender DEFERRED-Snapshot
liest Identität und Rolle frisch: nur aktuelle Admins oder Supervisoren,
dieselbe nichtrekursive SQL-Sicht wie der bisherige Teambericht
(`u.supervisor_id=actor` oder aktueller Admin). Kein Request-Subject, keine
rekursiven Teams, keine fremden Administratorrechte aus dem Sessioncache.
Auch Tabellensuche, Datumswahl und Teamwochen-Pagination schränken diesen
vollständigen aktuellen Kontext nicht ein und können Gegenstücke nicht
verstecken. Öffnen/Schließen und bestehende Filter-/Paginglinks bewahren die
sonstigen Anzeigen; es wird nichts gepostet, reserviert oder freigegeben.

Der neue reine Anzeigehelfer verwendet **unverändert**
`report_absence_conflicts`, `time_record_work_overlap_lookup`,
`time_record_review_rows`, `report_record_calculation` und
`time_record_local_time_findings`. Er erfindet keine weitere Diagnosekategorie,
keine DST-Auflösung und insbesondere **keinen zusätzlichen
Abwesenheit/Abwesenheit-Hinweis** aus dem gehaltenen PR #352. Gehaltene
Korrektur-/API-Filter sowie PR #404/#405 sind kein Bestandteil dieses Pakets.

Ausgabe: höchstens 100 passende, zuletzt angelegte Buchungen mit exakter
Person-/Buchungs-ID als Text, bestehendem Namen, Originalstart/-ende/-pause
und vier bestehenden booleschen Hinweiskategorien. Die Gesamtzahl bezieht
sich nur auf diese Regeln im aktuell erlaubten Kontext. Keine Beschreibungen,
Abwesenheitsarten, Krankheitsdetails, Gegenstücklisten, Credentials, Audit-
Rohsnapshots, Export-/Reparatur-/Korrekturschreibaktion oder neue Routen.
Die Hilfsabfrage benötigt `beschreibung` ausschließlich für die vorhandene
Abwesenheitsklassifikation; das Feld verlässt den Reader nicht.

Originalwerte erscheinen escaped als JSON-Auswahl (damit NULL, leere Werte,
Typen und Steuerzeichen sichtbar sind), pro Wert höchstens 512 UTF-8-Bytes
plus Kürzungszeichen bei ausdrücklich markierter Kürzung. Nicht darstellbares UTF-8 wird nur in der
Anzeige ersetzt. Keine Anzeige wird zurückgeschrieben oder als bestätigtes
Mapping angeboten. Die Datenquelle bleibt unverändert. Ein leeres Ergebnis
ist kein Vollständigkeits-, Sauberkeits-, Migrations- oder Verfügbarkeitsattest.
Der Benutzer muss die bestehenden freigegebenen Vier-Augen-Prozesse zur
Klärung verwenden; aus der Karte folgt keine zusätzliche Schreibautorität.

Die neue Karte folgt den bestehenden Supervisor-Karten und Tabellen mit
DE/EN/ZH-Copy. Native Seiten, alle bisherigen Aggregationen/Periodenregeln,
Auth-/CSRF-/Writer-/Runtime-/Hostgrenzen und die HTML-Browserabnahme bleiben
unverändert beziehungsweise separat offen. Es gibt keine Aktivierung oder
behauptete vollständige #55/#79-Abnahme.

Begrenzter inaktiver UI-Migrationsschritt auf Main
`5d3afc45c5b14f6acffd2321e82cc86ad3818c51`, Baum
`7dfe095c0ce33fe0184bfab215a1a9c1e24c1673`. Kein Cutover oder neues
Qualitätsverfahren: die native Liste besitzt den Filter und DST-Hinweise bereits.
Der bisherige Laravel-Controller ignoriert dagegen `review=1`; seine View hat
keinen dazu passenden Auswahl-/Bestätigungsstand und keine DST-Feldhinweise.

## Gleicher vorhandener Daten- und Anzeigevertrag

`LocalWebHomeController::records()` verwendet weiter genau einen Aufruf von
`reportRows(chronological: true)`. Dessen unveränderter Reader lädt die Identität
und aktuelle bekannte Rolle im bestehenden Snapshot neu und liest ausschließlich
`WHERE user_id=?`; kein Request-Subject und keine Ausweitung für Admin/Team.
Keine SQL-, Auth-, Snapshot-, Routen-, Middleware-, Provider-, Writer- oder
Runtime-/FreshWeb-Freigabe wird geändert.

Nur fehlendes `review` bedeutet „Alle“. Ein vorhandenes Feld muss exakt der
String `'1'` sein, sonst derselbe feste native 400-Text; keine Normalisierung oder
Weiterdeutung. Der bestehende opt-in Routenstack nimmt diesen Pfad bereits von
globalem Trimmen/Leerstringumwandeln aus; dies wird nicht neu aktiviert.

Die unveränderten Definitionen in `lib/time_record_review.php` klassifizieren
das vollständige bereits eigene Ergebnisarray **vor** Zehn-Raw-Row-Paging.
Count, Seitenzahl und letzter zulässiger Reviewseitenstand stammen aus derselben
gefilterten Menge. Auch Alle-Modus übernimmt den inzwischen in PR #279
integrierten nativen Count→Clamp: alte/überlaufende Seiten werden auf die letzte
eigene Seite begrenzt, leere Bestände auf Seite 1. Es wird nicht zuerst mit einem
potenziell überlaufenden Offset geschnitten. Bestehende zulässige Seiten und
Gruppen bleiben unverändert. Tagesgruppen, Summen und Konfliktbelege
erhalten weiterhin den gesamten ungefilterten eigenen Kontext derselben Reads;
ein gesunder oder andersseitiger Kontextdatensatz wird nicht aus Summen entfernt.

DTO und bestehendes Blade-Fragment ergänzen nur den nativen Auswahlstand,
Trefferzahl, nichtattestierende Leermeldung und bestehende feste DST-Feldtexte.
Die Kategorien bleiben „doppelte Berliner Stunde ohne Offset“ beziehungsweise
„Berliner Zeitlücke ohne Offset“, nicht ein gewählter Zeitpunkt/Offset/Ersatzwert.
Vorhandene Dauer-/Pausen-/Tages-/Konfliktberechnungen und Rohdaten bleiben exakt.
Der ursprüngliche gemeinsame Client bedient die gleichen `data-*`-Hooks bereits;
JavaScript, öffentliche Assets/Pins und native PHP-Dateien bleiben unverändert.
Blade escaped wie bisher; keine neue HTML-Rohdatenausgabe oder Diagnosepersistenz.

## Enger Grobnachweis

PHP 8.5.12 / Laravel 13.38.0: tatsächlicher aktueller `LegacyChronoReads`,
Controller und stock Blade gegen erfundene query-only Memorydaten; feste
RequestGuardidentität, kein Login, keine vollständige Appinitialisierung.
Native und Laravel liefern dieselben 33 vorhandenen Prüftreffer über vier
Seiten, dieselben vollständigen Gruppen/Counts/letzten Seiten und Feldtexte.
Alle-Modus behält die ursprünglichen Gruppen auf zulässigen Seiten; auch stale/
PHP-Maximum-Seiten stimmen mit dem frischen nativen Clamp überein. Gefälschtes Subject
und Admin-/Supervisorrolle weiten die Ownsicht nicht aus. Leere Prüfung erklärt
ausdrücklich keine Fehlerfreiheit; ungültige Filter liefern 400. Datenhash,
Änderungszahl, query-only und geschlossener Snapshot bleiben erhalten.
Explizite Offsets, offene Enden und nicht klassifizierte Formen werden nicht
neu eingeordnet. Tatsächliche Klassenpfade werden gegen stale Composer-Classmaps
verifiziert; anfängliche reine Harness-Autoload-/Sparsepfadfehler sind korrigiert.

Syntax/kompiliertes Blade, volle Quellerhaltung und Inverse werden separat belegt.
Keine neue Repositorytestsuite, CI-Auslösung oder Installation. Kein echter
Account-, Auth-/CSRF-/Middleware-/HTTP-/Browser-/Form-/Produktivnachweis, keine
echten Bestandsdaten, Reparatur oder Bereitstellung. Das bestehende vollständige
Own-Laden bleibt unverändert; keine Skalierungs-/Produktionsleistungszusage.
#55 ist kein vollständiger Bestands-/Work-Work-Overlap-/Migrationsfreigabecheck;
#79 bleibt für UI-/Rollen-/Betriebsabnahme und Cutover offen.
