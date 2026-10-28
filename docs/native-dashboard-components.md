# #45 — Arbeit und Gutschrift im bestehenden Native-Dashboard

Begrenzte Native-Fortsetzung auf Main
`caba427faa7f7e61308487c64697e5cef4af86b0`, Tree
`e28ce944fc61807d261e8982ba1cae6b76374451`. Die frühere
[Laravel-Dashboard-Ergänzung](framework/dashboard-ui.md#45a--arbeitszeit-und-abwesenheitsgutschrift-in-denselben-karten)
hat den Native-Fallback ausdrücklich unverändert gelassen.

## Darstellung, keine zweite Berechnung

Die zwei vorhandenen Wochen-/Monatsstundenkarten zeigen zusätzlich Arbeitszeit
und Abwesenheitsgutschrift. Der unveränderte definitionsreine Helfer
`chrono_dashboard_components` partitioniert die bereits eigenen vollständigen
Quellzeilen mit `report_record_calculation` und `booking_is_absence`. Die beiden
Komponenten ergeben dieselbe bisherige kombinierte Summe. Reiner Urlaub ist
keine tatsächlich gearbeitete Zeit.

Gutschrift bedeutet hier die bisher gespeicherte abgeschlossene Abwesenheitsdauer,
nicht eine Neuberechnung aus `planned_minutes`, heutigem Sollmodell oder
Urlaubsanspruch. Legacy-Kennzeichnungen bleiben berücksichtigt. Offene und
ungültige Zeiten sind keine abgeschlossene Arbeit; Nachtschichten bleiben beim
Berliner Starttag, einschließlich DST und Periodengrenzen. Kein neuer Parser,
keine Regel-, Soll-, Saldo- oder Bestandsänderung.

Die unveränderten Prozentformeln heißen nun Sollabdeckung statt Produktivität:
Arbeitszeit plus Gutschrift geteilt durch volles Periodensoll, weiterhin auf
0–100 % begrenzt und ohne positives Soll weiterhin 0 %. Der Durchschnitt bleibt
pro abgeschlossenem Buchungstag, die Wochenzählung zählt weiterhin abgeschlossene
Buchungen. Eine knappe Notiz nennt die volle aktuelle Woche und den vollen
aktuellen Monat. Kalendernavigation verschiebt diese Kennzahlen nicht. Es ist
kein Stichtagssaldo oder lückenloses Vertragszeitkonto.

Die neun Karten mit Reihenfolge, Icons, Farben und Zahlenformaten bleiben.
Titel und neue Unterzeilen dürfen umbrechen; Kalender, Dialoge, Assets und
JavaScript bleiben bytegleich. Texte sind in de/en/zh vorhanden; neue
zusammengesetzte Texte werden escaped.

## Nichtabschließbare Werte

Vorhandene periodenspezifische Konfliktflags haben Vorrang. Bei ungültigen
Quellzeilen erklärt der bestehende Native-Header bereits alle Berichtssummen
als unvollständig. Die Karten übernehmen jetzt diese konservative globale
Grenze: Hauptwerte und Komponenten zeigen denselben Hinweis statt finaler
Teilzahlen. Auch ein ungültiger alter Datensatz außerhalb der aktuellen Periode
führt deshalb zu „Unvollständig“; ein unlesbarer Start erhält kein erfundenes
Datum. Das ist bewusst keine neue periodengenaue Fehlerzuordnung und keine
vollständige #16-Abnahme. Planzielwerte bleiben Planzielwerte.

Es gibt keine zusätzliche SQLabfrage, Verbindung, Transaktion, Schreiboperation,
Route, Rechte-/Auth-/Session-/CSRFänderung, Kalenderquelle oder Runtime-/V5öffnung.
Die vorhandenen eigenen Daten- und Teamgrenzen werden nicht erweitert.

## Begrenzter Nachweis

Ein grober synthetischer PHP-8.5.12-Inhaltsrender prüft sieben Phasen in de/en/zh:
normal, nur Urlaub, Konflikt, ungültige bekannte Zeit, unlesbarer Start, beides
zusammen und DST/Nacht-/Monatsgrenze. Hauptzahlen bleiben bei gültigen Daten
identisch; neun Karten und vier Komponenten bleiben vorhanden. Die reine
Urlaubswoche ergibt 00:00 Arbeit und 40:00 Gutschrift. Ein absichtlich abweichendes
`planned_minutes` verändert die bestehende Gutschrift nicht. Konflikte und
Unvollständigkeit maskieren Haupt- und Teilwerte zusammen. Quellzeilen und
Zeitzone bleiben unverändert, der Kalender-/Modal-/Asset-/Scriptteil ist exakt
zur Basis. PHP-Syntax und Whitespace-Prüfung bestehen.

Nur erfundene Arrays, Sprachdefinitionen und tatsächliches PHP-Inhaltsrendern,
kein Appbootstrap, DBzugriff, Account, Session, HTTP/Server, Browser oder
JavaScriptausführung. Keine visuelle, geschützte oder produktive Abnahme,
Runtimeaktivierung, GitHub-CI-Auslösung, Remoteänderung oder Veröffentlichung.
#45 bleibt für echte Stichtags-/Fach-/Browserabnahme offen.
