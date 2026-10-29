# Mehrere eigene Abwesenheitseinträge desselben Tages prüfen (#55)

Quellbasis Main `64948f8e87c72d26338595aef7afed0382198101`, Tree
`87fbcca1334b3ae9cb163ce4e8a4a7189aa4d3aa`, nach Native-Feiertagsrefill #351.
Die vorhandene Eigenliste und ihr Filter **Vorhandene Prüfhinweise** zeigen nun
zusätzlich mehrere eindeutig prüfbare abgeschlossene Abwesenheitseinträge mit
positiver **berechneter Dauer** am selben Berliner Tag. Der Offline-Scanner
konnte Abwesenheitspaare bereits prüfen; sein Code/Format bleibt unverändert.

## Enger Hinweis in der vorhandenen Liste

`time_record_absence_overlap_lookup()` erhält ausschließlich den vollständigen
bereits berechtigten Snapshot vor Datums-/Zehner-Seitenfilterung. Derselbe
reine Helfer wird Native und Laravel verwendet. Er benutzt unverändert die
bestehende Abwesenheitserkennung, enge Zeitdiagnose und Berichtsberechnung:
moderne `absence_kind`-Zeilen und erkannte deutsche Legacy-Marker, keine neue
Urlaubs-, Krankheits-, Anspruchs-, Genehmigungs- oder Gutschriftregel.

Beide Zeitwerte müssen unterstützt und eindeutig sein. Der vorhandene
`report_record_calculation` muss einen abgeschlossenen Eintrag mit mehr als
null berechneten Minuten liefern. Offene/ungültige/mehrdeutige/nicht existente
Werte, nicht unterstützte Formen und Null-/Netto0-Dauern liefern keine neue
Markierung. Das ist bewusst enger als die Offline-Abwesenheitspaarprüfung:
Ein fehlender Hinweis ist weder Vollständigkeits- noch Fehlerfreiheitsnachweis.

Nach dem bestehenden `booking_span`-Vertrag belegt eine Abwesenheit ihren ganzen
Berliner Starttag, nicht nur ihr Kompatibilitäts-Uhrzeitintervall. Deshalb werden
auch verschiedene Arten und zeitlich getrennte Uhrzeitintervalle desselben
Tages gemeinsam markiert. UTC-Datumstext oder bloße Zeichenpräfixe entscheiden
nicht über den Tag; benachbarte Berliner Tage und verschiedene Konten werden
nie kombiniert. Vorhandene eindeutige offsetfreie Werte behalten die historische
Berlin-Annahme, ohne deren fachliche Bestätigung zu behaupten.

Pro Konto/Tag wird nur ein bisheriger Teilnehmer gemerkt; ab einer zweiten
anderen Zeile werden beide und alle weiteren Teilnehmer markiert. Es entstehen
keine Paarlisten oder neuen SQL-Abfragen. Das Laravel-DTO enthält nur sichtbare
eigene Zeilen-IDs als boolesche Flags, keine Gegenzeile, Abwesenheitsart oder
fremden Kontodaten. Alle alten Kategorien, Reihenfolgen und Warnungen bleiben.

Die bestehende Zeile erhält einen erklärenden Text und behält ihren bisherigen
Link **Korrektur / Storno**. Das ist kein Nachweis von Doppelvergütung, Anspruch
oder genehmigter Abwesenheit; keine Zeile wird ausgewählt, gekürzt oder entfernt.
Tatsächliche Änderungen benötigen unverändert den begründeten Antrag und die
Entscheidung einer anderen berechtigten Person. Die Werte und sämtliche Dauer-,
Tages-, Wochen-, Saldo-/Exportberechnungen bleiben unverändert; der Hinweis ist
keine neue Berechnungs-/Exportblockade oder verlässliche Gesamtperiodenfreigabe.

## Grober synthetischer Quellnachweis

PHP **8.5.12**, Laravel **13.38.0**: 37 erfundene Zeilen ergeben elf neue
Teilnehmer; sieben bisherige Prüfzeilen bleiben gleich, zusammen 18. Tatsächlicher
Helfer, tatsächlicher Controller mit Fake-Port für bereits berechtigte eigene
Zeilen, DTO, kompilierte vorhandene Blade-Ansicht DE/EN/ZH (sechs Listenseiten)
und exakt extrahierte native Zeilenfunktion wurden geprüft.

Ganze lokale Tage mit verschiedenen/modernen/Legacy-Arten, identische und
getrennte Uhrzeitintervalle, 23-/25-Stunden-Tage, unterschiedliches UTC-/lokales
Datum, Seiten-/Kontogrenzen, Null-/Netto0-/Offen-/Ungültig-/DST-/Formatausschlüsse,
exakte große IDs, alte Kategorien/Reihenfolge, vollständige Tagessummen,
Escaping, übergroße Seite und ungültiger Filter bestanden. Arrays, native Session
und Zeitzone bleiben gleich. Notwendige PHP-/Blade-Syntax und Diffcheck separat;
keine neue Testsuite oder manuell gestartete CI.

**Grenzen:** kein App-/Bootstrap-/DB-Zulassungs-/Auth-/CSRF-Stack-/HTTP-/Browser-/
Sicht-/Runtime-/Produktions-/Konkurrenz-/Lastnachweis, keine echten Konten,
Credentials, Schlüssel, Datei-DB, Konfiguration oder Server. Keine Quellen- oder
Urlaubskontoattestation, kein Mapping oder Reparatur. #55 bleibt teilweise offen.
Bestehende #44/#57-/Held-/Denialpakete werden weder berührt noch transportiert.
