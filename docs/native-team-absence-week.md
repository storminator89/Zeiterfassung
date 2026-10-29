# #54: bestehende Team-Abwesenheitswoche auch nativ anzeigen

Finaler isolierter Quellschritt auf Main `60fdf57ebeeb849e0aeb199682737a62e9d03f64`,
Baum `35e3a6510a445bd5dafff099c312f0832b69e902` (nach #248). Die vorhandene native
`supervisor.php` erhält die bereits in der optionalen Laravel-Ansicht vorbereitete
[datensparsame Abwesenheitswoche](framework/team-absence-week.md), nicht einen
zweiten Kalender-/Abwesenheitsalgorithmus. Keine neue Route, Bibliothek,
JavaScriptlogik, Schreibaktion oder Runtime-/V5-Aktivierung.

## Vorhandene Projektion gemeinsam verwenden

Der bisherige `LegacyChronoReads::teamAbsenceWeek()`-Projektionsbody liegt nun als
definitions-only `chrono_team_absence_week_snapshot()` vor. Laravel verwendet
weiterhin denselben benannten autorisierten Snapshot, denselben aktuellen Actor
und seine bisherige Rollenverweigerung. Der feste vorhandene PDO-Zeilenreader
wird wiederverwendet. Scope, SQLparameter, Reihenfolge, 50er-Personenseite,
1001er-Kandidatenlesegrenze, siebentägiger Berlin-Zeitraum und Rückgabefelder
bleiben gleich; keine parallele Implementierung oder Quellreparatur.

Die neue native Grenze besitzt genau eine Lesetransaktion. Darin werden
Kennwortversionsbindung und aktuelle lokale Admin-/Supervisorrolle geprüft,
danach Personenanzahl, Roster und Abwesenheitszeilen mit derselben aktuellen
direkten Team-/Adminbedingung gelesen. Keine Own-Ausnahme, Auswahl beliebiger
Personen oder Unterteams. Eine laufende fremde Transaktion wird nicht übernommen;
Auth-/Rollen-/SQLfehler rollen die eigene zurück und geben keine Teilprojektion
zurück. Der bestehende native PHP8.5-`PDO::connect()`-/`Pdo\Sqlite`-Vertrag für
`createFunction()` bleibt Voraussetzung; kein generischer PDO-Fallback.

Stempelstatus und historischer Bericht bleiben getrennte bestehende Reads, keine
atomare Gesamtseitenzusage. Der vorhandene Stempelstatus prüft nach dem neuen
Kalenderread erneut die aktuelle Rolle für den bisherigen historischen Bericht.
Seine Fehlergrenze und sämtliche bisherigen Buchungs-/Berichtssummen bleiben
unverändert. Ein Kalenderfehler wird als „Nicht verfügbar“ dargestellt, nie als
leeres Team oder freie Kapazität; der letzte erfolgreiche Kalenderread wird
nicht als Fallback gespeichert. Die Karte bleibt innerhalb der bestehenden
Admin-/Supervisoransicht und des vorhandenen `no-store`-Transports.

## Dieselbe enge Darstellung und Navigation

Die reine PHP-Karte übernimmt bestehende Card-/Tabellen-/Formstile und die
bereits vorhandenen DE-/EN-/zh-Texte. Sie zeigt nur Namen, sieben Datumswerte und
generische **Abwesend**-/**—**-Marker, keine Art, Notiz, Diagnosen, IDs oder
Credentialwerte. Der Marker heißt gespeicherter positiver abgeschlossener
Abwesenheitstag, nicht Genehmigung, Vertretung, Echtzeitanwesenheit oder
Verfügbarkeitsversprechen. Urlaub/Krankheit werden intern wie bisher ausgewählt,
aber nicht unterschieden ausgegeben. Nullgutschriften, Feiertage und normale
Arbeitszeiten erzeugen keinen Marker. Ungültige/offene und abgeschnittene
Kandidaten behalten den sichtbaren Unvollständigkeitshinweis. Der alte Bericht
wird dadurch nicht nachträglich von allen Gesundheitsangaben bereinigt.

`team_date` und `team_page` wählen nur Woche und Personenblatt. Vorige/aktuelle/
nächste Woche und Personenblätter bewahren die unabhängige Berichtssuche/-seite;
deren Suchformular und Seitenlinks bewahren die kanonische Kalenderauswahl.
Ein Wochenwechsel beginnt wieder beim ersten Personenblatt. Datenfehler und
fehlende Marker sind keine Aussage, dass jemand verfügbar ist. Kein Feed,
öffentliches Token, Export, Urlaubsantrag, Urlaubsanspruch oder neue Fachregel.

## Begrenzter Nachweis

Ein grober anonymer Memory-Journey mit erfundenen Konten und nicht verwendbaren
Kennwortplatzhaltern prüft die gemeinsame Projektion und native Grenze: 57
Teammitglieder auf 50+7 Seiten, direkte Teams/Admin ohne eigene/Unterteamleckage,
Berliner Herbst-/Frühlingswoche und Offsetdatum, positive/alte/Null-/Arbeits-/
Feiertags-/ungültige/offene/außerhalb liegende Zeilen, Auswahllimits, Entzug/
Umzuordnung, Auth-/Transaktions-/SQLverweigerung und 1001er-Lesebegrenzung. Die
tatsächlich neue reine PHP-Karte wird mit künstlichem DTO auf Escaping,
Datensparsamkeit, Fehler-/Leerfall und unabhängige Links geprüft. Nur das erste
Prüfskript nutzte irrtümlich generisches `new PDO`; es wurde auf die bestehende
PHP8.5-Factory korrigiert, nicht der Produktvertrag gelockert.

PHP8.5.12-Syntax, Localeparität, definitions-only Laravel-Klassenkomposition,
mechanische Projektionsübertragung und vollständige Quellerhaltung sind getrennt
geprüft. Kein App-/Migrations-/Login-/Session-/HTTP-/Browser-/Layout-/Konkurrenz-
oder tatsächlicher Daten-/Betriebsnachweis. Keine echten Personen, Kennwörter,
Keys oder Daten gelesen, keine neue kleinteilige Repo-Testsuite/CI und keine
Remoteaktion, Host-/Securityänderung oder Veröffentlichung. #54 und die reale
geschützte UI-/Betriebsabnahme bleiben offen; keine vollständige Issueerledigung.
