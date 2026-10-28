# CH-12: Reihenfolge in eigener Liste und CSV

Bezug: [#17](https://github.com/storminator89/Zeiterfassung/issues/17), Basis
`0d270e61e3f1a4c06e13a1eeaddaa2cc41ba726a`.

Die native eigene Zeittabelle und der bestehende eigene Voll-CSV sortieren nach
dem tatsächlichen Startzeitpunkt absteigend, bei Gleichstand nach der stabilen
Datensatz-ID absteigend. Die SQL-Sortierung erfolgt **vor** dem unveränderten
Zehn-Rohzeilen-Limit/Offset der Tabelle. Keine neue Ansicht, kein neuer Filter,
keine Änderung von Rollen, Datenbankschema, gespeicherten Zeittexten, Summen,
Pausen, Gutschriften oder dem bestehenden seitenlokalen Gruppieren. Unvollständige
Tagessummen an Seitengrenzen bleiben die separate Aufgabe #10.

Der definitions-only Helfer `lib/report_ordering.php` registriert eine
lokale SQLite-Skalarfunktion auf der vorhandenen PHP-8.5-PDO-Verbindung.
Er verwendet den kanonischen Parser `booking_time()`, keine SQL-Textsortierung
oder UTC-Annahme für offsetfreie Altzeiten. Das lokale Buchungsdatum bleibt
separat über `report_day()`/`booking_local_date()` bestimmt. Auch die bestehenden
CSV-Datums-/Uhrzeitspalten werden in Europe/Berlin formatiert; ihre Formate,
Spalten, BOM, Escaping und Download bleiben erhalten. Explizit anderszonige
ISO-Zeittexte zeigen somit dieselbe Berliner Ortszeit wie die lokale Tagesgruppe.

Es gibt im Bestand keine wiederverwendbare Epoch-Skalarfunktion und keinen
Startzeitindex für diese Abfrage. SQLite `unixepoch(startzeit)` allein würde
offsetfreie Altzeiten als UTC statt als Berlin interpretieren. Der Callback
verursacht einen zusätzlichen PHP-Datumsparse pro ausgewählter eigener
Kandidatenzeile vor dem Sortieren; das bestehende Text-ORDER-BY musste ebenfalls
sortieren. Keine Performanceverbesserung wird behauptet. Große Historien und
eine normalisierte/indexierte Epochprojektion bleiben #56 bzw. einer separat
geprüften Datenmigration vorbehalten. Kein Index-/Schemaupgrade in diesem Fix.

## Historische Grenzen

- Offsetfreie Altzeiten behalten die bestehende Europe/Berlin-Interpretation.
  In der doppelten Herbststunde verwendet der kanonische PHP-Parser weiterhin
  seine bisherige Winterzeit-Auflösung. Der wirkliche historische Instant lässt
  sich ohne Offset nicht rekonstruieren; es wird weder ein Offset erfunden noch
  ein Originalwert umgeschrieben. Nicht existierende Frühlingszeiten behalten
  ebenfalls die bestehende Parsernormalisierung. Korrekturen laufen weiterhin
  ausschließlich über den vorhandenen autorisierten Korrekturprozess.
- Unlesbare Sortierschlüssel ergeben NULL und stehen nach lesbaren Instants,
  wiederum nach ID sortiert. Das ist keine Datenreparatur und keine vollständige
  ungültige-Zeilen-Anzeige: #16 bleibt offen; nachgelagerte Datums-/Summenpfade
  können solche Bestandszeilen weiterhin ablehnen.
- Der geteilte Gruppenhelfer ändert nur seinen Kommentar, nicht sein Verhalten.
  Er bewahrt weiterhin die angelieferte Reihenfolge. Die inaktiven Framework-
  Reader, die API, Teamansichten und WIP-UI-PRs #110/#111 werden nicht umgeschaltet
  und behalten vorerst ihre bisherigen Queryverträge. Vor deren Aktivierung
  muss diese Sortierung ausdrücklich übernommen und gemeinsam abgenommen werden;
  #17 darf nicht als flächendeckend abgeschlossen behauptet werden.

## Beweisumfang

Notwendige PHP-8.5.12-Syntaxprüfung und ein grober synthetischer reiner
`sqlite::memory:`-Nachweis für Herbst-DST, gemischte ISO-/Legacyformen, gleiche
Instants mit verschiedenen IDs, Berlin-Tageswechsel und Seitengrenzen.
Die unveränderten nativen SELECTs werden dabei ohne ihre Einstiegsskripte
ausgeführt. Keine App-Konfiguration, HTTP-/Browser-/Login-/Middleware-Abnahme,
keine reale Datenbank, Zugangsdaten, Server oder Deployment. Keine neue CI-Suite.

## Ergänzung: native und vorbereitete Laravel-API

Basis dieses getrennten Dreipfadpakets: Main
`7ececc05556f6fb26532e078ee3988c9651f583e`, Tree
`2a3eb9ddaefe77157d8ef119e311b28b3899e4c5`. Ausschließlich native
`getTimeEntries()` in `api.php`, `LegacyChronoReads::apiTimeEntries()` und dieses
Dokument ändern sich. Die beiden Lesewege registrieren denselben unveränderten
`report_ordering.php`-Helfer auf der **bereits zugelassenen vorhandenen PDO** und
sortieren vor LIMIT/OFFSET nach `chrono_report_start_epoch(startzeit) DESC,
id DESC`. Keine neue Verbindung, Datenkopie, Migration oder Zeitprojektion.

Person-/Datumsfilter, eigenes Berliner Buchungsdatum, COUNT, Bindings,
Paginierungsvalidierung/-Metadaten und rohe Zeilenfelder bleiben unverändert.
Auch der neue erwartete Sitzungsbindungsvertrag aus #36 wird nicht berührt:
keine Änderung an Status, Aktionen, Authentifizierung, JWT, Rollen, Routen,
Fehlertransport oder Schreibpfaden. Ungültige Filter werden wie zuvor vor dem
eigentlichen Lesen verworfen; ohne Datenreparatur bleibt der Raw-Wire-Vertrag.

Die bestehende UDFregistrierung ersetzt auf derselben Verbindung denselben
Callback und kann vor mehreren nacheinander vollständig abgeschlossenen
Abfragen erneut erfolgen. Wenn die Registrierung nicht verfügbar ist, wirft
der unveränderte Helfer einen Fehler; es gibt weder stillen Textsortierungs-
Fallback noch eine Ersatzverbindung. Vorhandene Fehleradapter bleiben zuständig.
Ein fehlender fester Helferpfad ist ebenso wie bei bestehenden Nutzern ein
fehlgeschlagenes Require, keine heruntergeladene oder dynamisch gewählte Quelle.

Die oben dokumentierte Berlin-/DST-/Legacy-Auflösung des vorhandenen Helfers
bleibt bewusst gleich. Insbesondere wird in diesem Paket kein neuer Parser,
Mapping oder strengeres Datenannahmemodell eingeführt. Relative/unvollständige
Altwerte und die Qualitätsgrenzen anderer Berichtspfade bleiben gesonderter
Prüfbedarf; keine Rekonstruktion eines historisch unbestimmten Instants.

Der grobe erfundene Query-only-Nachweis vergleicht die beiden reinen Lesebodies
für gemischte Offset-/Legacyformen und die doppelte Herbststunde, IDgleichstand,
Scope/Filter sowie Paging und Metadaten. Wiederholte Registrierung und der
feste Registrierungsfehler werden ohne neue Verbindung im Anwendungscode
betrachtet, dazu PHP-Syntax und vollständige Quellen-Erhaltung. Der grobe
synthetische Nachweis und PHP-Syntax bestanden am 28.10.2026 unter PHP 8.5.12;
ein einziges Query-only-In-Memory-PDO enthält ausschließlich erfundene Zeilen,
der Fehlerfall verwendet ein verbindungsloses PDO-Testdouble. Kein Bootstrap,
Token/Login, Session, echte Daten/DB, HTTP, Browser, Deployment oder neue CI.
Dies ist keine geschützte/API-HTTP-/Exportabnahme und keine Performanceaussage.
Teamreihenfolge, weitere Berichtspfade, große Historien und vollständiges #17
bleiben offen; der bis hier eingefrorene Fortschritt ist nur API-Quellparität.

## Ergänzung: native und vorbereitete Laravel-Teamreihenfolge

Basis dieses getrennten Dreipfadpakets: Main
`8aa48d23f843b33eb69efa625f177c651388fca2`, Tree
`a1f3c3a14f59a9d8ae7f74e4cdc1a4e8d5eea224`. Ausschließlich der bestehende
SELECT in `supervisor.php`, `LegacyChronoReads::teamReport()` und dieses
Dokument ändern sich. Beide Rollen-prüfenden Leser registrieren den festen
unveränderten `lib/report_ordering.php`-Helfer auf ihrer **bereits vorhandenen
PDO** und sortieren nach `chrono_report_start_epoch(z.startzeit) DESC,
z.id DESC`. Das geschieht vor dem unveränderten Gruppieren, Namensfilter und
Zehn-Gruppen-Paging. Keine neue Verbindung, Abfrage, Datenkopie oder Migration.

Der genaue bisherige Umfang bleibt `u.supervisor_id = ? OR ?='admin'`:
frisch autorisierter Supervisor sieht seine direkt zugeordneten Personen,
Administrator den bisherigen Gesamtrahmen. Kein impliziter Eigen-, rekursiver
Team- oder Fremdpersonenzugriff. Bindings, Rohwerte, Gruppen-/Summenberechnung,
Zeitzone, Sollzeit, Übertrag, Konflikt-/Unvollständigkeitsregeln, Suche und
Seitenbegrenzung bleiben unverändert. Die getrennte aktuelle Teamstatus-
Projektion aus #54 und alle anderen Reader-Methoden sind bytegleich erhalten.
Die Darstellung kann sich dadurch in der beabsichtigten chronologischen
Reihenfolge von Gruppen und deren Einträgen ändern. Auch die bestehende
Benutzerreihenfolge der Überstunden-/Detailkarten folgt dem ersten Auftreten
in diesen sortierten Zeilen und kann sich dadurch ändern; die Kartenwerte
bleiben unverändert.

Es gelten unverändert die oben beschriebenen Parser-/Berlin-/DST-Grenzen,
NULL-Sortierung für unlesbare Werte, wiederholbare UDFregistrierung auf derselben
Verbindung und der feste Fehler ohne Text-Fallback oder Ersatzverbindung.
Dies behebt keine ungültigen Bestandszeilen: der native Vorab-Qualitätsschutz
bleibt eine getrennte #16-Aufgabe. Keine neue Reparatur- oder Rechenregel und
keine Performanceverbesserung behauptet.

Ein grober Query-only-Nachweis verwendet ausschließlich erfundene Daten in
einer `sqlite::memory:`-PDO und die zwei tatsächlichen SELECTs. Er betrachtet
Offset-/Legacy-/Herbst-DST-Reihenfolge, Gleichstand nach ID, bisherigen
Administrator-/Direktteamumfang und die unveränderte Suche/Gruppenseitengrenze;
dazu PHP-Syntax und vollständige Quellen-Erhaltung. Der grobe Nachweis und
PHP-Syntax bestanden am 28.10.2026 unter PHP 8.5.12. Kein Bootstrap, Reader-
Dispatch, Berichtsvollrechnung, Login, Session, echte Daten/DB, HTTP oder Browser,
kein neuer CI-Lauf, Remote-Schreibzugriff oder Deployment. Der Fortschritt ist
Team-Quellparität, keine geschützte Rollen-/Browserabnahme, kein #16-Nachweis und
kein flächendeckender Abschluss von #17 oder Lastnachweis großer Historien.
