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
