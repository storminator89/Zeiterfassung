# Eigene abgeschlossene Arbeitszeitüberschneidungen sichtbar prüfen (#55)

Auf Mainbasis `e7d198315ab15994fd69419d96c9cb8e94dd83de` ergänzen die vorhandene
native und Laravel-Arbeitszeitliste denselben ausschließlich lesenden Hinweis.
Der bestehende Filter **Vorhandene Prüfhinweise** fand bisher ungültige Zeilen,
Arbeitszeit-/Abwesenheitskonflikte und erkannte Berliner DST-Lücken/Doppelstunden,
aber keine ansonsten gültigen abgeschlossenen Arbeit/Arbeit-Überschneidungen.
Die Offline-Diagnose konnte solche Paare bereits nennen; sie wird nicht geändert.

## Enge Grenze, vorhandene Oberfläche

`time_record_work_overlap_lookup()` erhält den vollständigen bereits
berechtigten eigenen Snapshot vor Datums-/Seitenfilterung. Er markiert nur
Teilnehmer zweier gültiger abgeschlossener **positiver Bruttointervalle**
desselben Kontos. Arbeits-/Abwesenheitsklassifikation, Zeitdiagnose und
Berechnung verwenden die vorhandenen gemeinsamen Helfer unverändert.

- Beide Zeitwerte müssen im unterstützten engen Diagnoseformat liegen und
  eindeutig sein. Offene/ungültige/mehrdeutige/nicht existente Zeitwerte sowie
  Null-Dauer und Abwesenheiten liefern keine neue Arbeit/Arbeit-Markierung.
- Halboffene Grenzen: reine Ende/Start-Berührung zählt nicht. Nachtarbeit und
  explizite numerische Offsets werden nach ihren tatsächlichen Instants geprüft.
- Netto0 bei positiver Bruttodauer bleibt prüfbar: Pausenminuten verraten keinen
  Pausenzeitpunkt. Keine Pausenlage, Kürzung, Doppelvergütung oder Reparatur wird
  aus dem Hinweis abgeleitet. Eindeutige offsetfreie Zeiten behalten die bisherige
  Berlin-Annahme; diese wird nicht fachlich bestätigt oder verändert.
- Sortierung mit jeweils weitestem Ende findet Teilnehmer statt aller Paare;
  keine quadratische Paarliste, keine Gegenkonto-/Gegenbuchungsdaten im DTO.
  Der Aufrufer besitzt weiterhin bereits den vollständigen eigenen Bestand.
- Beide bisherigen Listenmodi zeigen die Hinweise an der bestehenden Zeile;
  der vorhandene Prüffilter berücksichtigt sie vor der unveränderten Zehner-
  Pagination. Reihenfolge, Seitenbegrenzung, bestehende Warnungen und Links auf
  **Korrektur / Storno** bleiben erhalten. Die andere berechtigte Person muss
  weiterhin über eine tatsächliche Korrektur entscheiden.

Dauer-/Tages-/Wochen-/Saldo-/Exportberechnungen und bisherige Qualitätsblocker
werden nicht geändert. Die Hinweise liegen getrennt von der Berichtsprojektion;
keine weitere Summe wird still gesperrt oder als verlässlich erklärt. Keine neue
Route, Abfrage, Rolle, Schreibaktion, Fachregel, UI-Struktur, Migration, Dependency
oder Konfiguration. Native Bootstrap und Laravel-Reader/Guard bleiben unverändert.

## Grober synthetischer Quellnachweis

PHP **8.5.12**, Laravel **13.38.0**, 39 erfundene Zeilen: 13 neue markierte
Teilnehmer, sechs bisherige Prüffilterzeilen bleiben gleich, zusammen 19.
Tatsächlicher gemeinsamer Helfer, tatsächlicher Laravel-Controller mit einem
Fake-Port für bereits berechtigte eigene Zeilen, DTO und kompilierte vorhandene
Blade-Ansicht für DE/EN/ZH (sechs Listenseiten) sowie die exakt extrahierte native
Zeilenfunktion wurden geprüft. Keine neue Testsuite wird ins Projekt aufgenommen.

Verschachtelung, Ketten und identische Intervalle, Nacht-/Seitengrenze, Netto0,
Randberührung, getrennte Konten, Null-/Offen-/Ungültig-/DST-/Format-Ausschlüsse,
exakte IDs über JavaScripts Zahlenbereich, alte Hinweisreihenfolge, vollständige
Tagessummen, Escaping, ungültiger Filter und übergroße Seite bestanden. Originale
Arrays, native Session und Zeitzone unverändert; notwendige PHP-/Blade-Syntax
und `git diff --check` separat geprüft. Unabhängiger Quellreview ohne Blocker.

**Grenzen:** kein App-/Bootstrap-/DB-Zulassungs-/Auth-/CSRF-Stack-/HTTP-/Browser-/
Sicht-/Runtime-/Produktions- oder Lastnachweis; keine echten Konten, Credentials,
Datei-DB, Server, Schlüssel oder Betriebsänderung. Kein Mapping oder Reparatur,
kein vollständiges Bestandsattest. #55 bleibt teilweise umgesetzt und offen;
insbesondere keine Abnahme sämtlicher Original-/Event-/Projektionsbeziehungen.
