# FW-08 / #79 – bestehendes Dashboard und Kalender, weiterhin inaktiv

Die folgenden Prerequisite-Abschnitte dokumentieren den damaligen Quellport.
Die danach separat übernommenen lokalen FullCalendar-Assets und die geschlossene
optionale Route stehen in `calendar-assets.md`. Der aktuelle ergänzende #45/A-
Schritt auf Main `993d7e659219040cf8d0f9dbb64b54da4c80b286` ist unten getrennt
beschrieben und präzisiert ausschließlich die vorhandene Kennzahldarstellung.

Quellbasis ist der vom alleinigen Publisher bestätigte Main
`d86b60cc39d456befd624d03a45eaa2bcb893c1e`, Baum
`fced8def4bbc33276faea308dd17c9b2f57e4319` (Home und Arbeitszeitmodelle).
`dashboard.php`, die Reportingvorbereitung in `functions.php`, die vorhandenen
`lib/calendar_range.php`, `lib/calendar_events.php`, `lib/reporting.php` und
`lib/home_projection.php` sind die Vertrags-/Darstellungsquellen. Keine neue UI.
Native Dateien, Assets, vorhandene Reader-Methoden, Provider und API-Aufrufer
bleiben unverändert.

## Enger additiver Quellumfang

Vier neue Dateien: `LocalWebDashboardController`, `ExistingUi/DashboardPage`,
`chrono.dashboard` und dieses Dokument. Dazu ausschließlich die neue Methode
`ChronoReads::calendarWeek(mixed $calendarDate = null): array` samt Implementierung
und fehlende exakte native Dashboard-/Kalendertexte in den vorhandenen
Framework-Copyarrays für **de/en**. Das ist weiterhin die bestehende
Zwei-Sprachen-Grenze, keine Migration weiterer nativer Sprachen.

Keine Route/Registrierung, Active-Page-Whitelist, Layout-, Fixture-, Provider-,
API-, Config-, Auth-, Session-, CSRF-, Host- oder Sicherheitsänderung und kein
Deployment. Standardbootstrap bleibt unverändert. Additive Readerhunks müssen
bei der Integration auf den dann aktuellen Main angewendet werden; niemals eine
ältere Gesamtdatei über Home-, History- oder Modelmethoden kopieren.

## Ein eigener Snapshot, keine zweite Fachlogik

Der Reader benutzt ausschließlich seinen bestehenden DEFERRED-Lesesnapshot mit
aus dem Guard stammender, darin frisch nachgeladener Identität/Rolle. Keine
Subjekt-/Rollenparameter; auch Admins und Supervisor sehen hier nur eigene
Buchungen. Sämtliche eigenen effektiven Zeilen werden vor Zeitraum-/Eventfilterung
an `report_days` und die bestehenden Konflikthelfer gegeben.

Eine einzige Berliner `now`-Instanz bestimmt aktuelle Woche/Monat/Jahr und den
Heute-Link. Die **Statistikkarten bleiben aktuelle Zeiträume**, auch wenn der
Kalender eine frühere oder spätere Woche zeigt. Abgeschlossene Schichten gehören
weiter vollständig zum kanonischen Starttag, offene Zeilen zählen nicht als
abgeschlossene Arbeitszeit. ISO-Jahr/Woche, tatsächliche Pausen, Gutschriften,
individuelle Sollmodelle, Feiertags-Soll und Überstundenübertrag bleiben bei den
vorhandenen Reporting-/Planungs-/Homehelfern. Die wenigen Kartenformeln sind
unverändert aus dem nativen Dashboard übernommen; kein Carbon-, SQL-DATE-,
neuer Zeitparser oder anderes Zeitkontomodell.

`calendar_visible_week` besitzt allein die native Datumsvalidierung, Montag–
Sonntag und exklusive Berliner Folgemontagsgrenze einschließlich DST. Der
Controller gewinnt `calendar_date` mit PHP `parse_str` aus der unveränderten
Querystring, entsprechend nativem `$_GET`; keine globale Laravel-Trimmung darf
eine nativ ungültige Eingabe gültig machen. Arrays, leere/falsche Datumsformen
und ungültige Kalendertage behalten den nativen Rückfall auf die aktuelle Woche.

Feiertage werden nur aus `Feiertage` für genau diese sichtbare Woche gelesen.
Kein API-Aufruf, Download, Refresh, INSERT oder Kalenderprovider kommt hinzu.
`calendar_range_events` filtert weiterhin überlappende Buchungsintervalle,
behandelt Abwesenheiten als ganze Berliner Tage und vermeidet den zusätzlichen
Cachefeiertag, wenn bereits eine Feiertagsgutschrift existiert. Die native
All-day-Endgrenze bleibt inklusive. Leere Wochen liefern `[]`, dieselben drei
Serverlinks und denselben leeren Kalender, keinen neuen Leerzustand.

## Datenqualität und offene Klärung bleiben getrennt

Ungültige Zeilen werden mit `report_record_calculation` erkannt und bleiben in
den vorhandenen ID-/Fehlerdiagnosen sichtbar; es wird kein Kalenderdatum erfunden
und kein fehlerhafter Datensatz an TOAST gegeben. Die gemeinsame Chromewarnung
mit Korrektureinstieg bekommt den vollständigen Invalid-Zähler und alle Konflikte.

Konflikte behalten getrennte Flags für `all`, `week`, `month`, `year`, abgeleitet
aus dem vollständigen Kontext mit `report_conflicts_in_period`, nicht aus den
sichtbaren Events. Ein Gegenstück kann außerhalb der Kalenderwoche beginnen.
Betroffene Karten zeigen weiterhin **„Klärung offen“**; Originalarbeitszeiten und
Gutschriften bleiben sichtbar, nichts wird gekürzt, gelöscht oder genullt.

Bewusste sicherere Paritätsabweichung entsprechend bestehendem Laravel Home:
**ungültig beeinflusste Teilaggregate werden nicht als finale Zahlen ausgegeben**.
Hat eine ungültige Zeile einen kanonisch lesbaren Starttag, sind dessen
Berichtszeiträume unvollständig; ist ihr Start unlesbar, markiert
`report_incomplete.unknown` die unbekannte Zuordnung und alle Zeiträume bleiben
vorsichtig unvollständig. Historisch außerhalb der aktuellen Perioden liegende
ungültige Tage sperren nicht unbetroffene aktuelle Karten, aber immer die
Gesamtsummen/Wochenzählung. Betroffene Reader-Metriken sind `null`, die Darstellung
zeigt **„Unvollständig“** und `data-report-presentation="incomplete"`, niemals
Ersatznullen. Bei gleichzeitigen Konflikten hat „Klärung offen“ Vorrang; die
Invalidwarnung bleibt zusätzlich sichtbar. Bekannte Sollwerte, insbesondere
die geplante tägliche Arbeitszeit, bleiben angezeigt.

## Bestehendes Markup, Text- statt HTMLtitel

Die neun nativen Statistikboxen mit Icons, Farben, Gruppen, Beschriftungen und
Tooltips sowie Kalenderhöhe, Navigation, Zeitfenster, Nur-Lesen-Einstellung und
Terminmodal sind übernommen. Im neuen Blade stehen nur die fehlenden lokalen,
auf `.chrono-dashboard` begrenzten Tailwindäquivalente; keine Assetänderung und
kein entfernter Tailwind-JavaScriptcompiler. Gemeinsame Sidebar und Chrome
werden nicht kopiert oder umgestaltet.

Blade escaped Texte und URLs. Nur der feste Laravel-Encoder
`Illuminate\Support\Js::from` wird für Eventdaten, Tagesnamen und das kanonische
Mittagsanker-Datum roh ausgegeben; keine rohe Notiz, Feiertagsbezeichnung oder
übersetzte JavaScriptzeichenkette. Sowohl All-day- als auch Zeittermin-Template
bauen Titel mit `textContent` und festen DOMknoten. Das Modal verwendet ebenfalls
`textContent`. Zeit-/Modalformate bleiben gleich, werden aber ausdrücklich in
`Europe/Berlin` statt versehentlich in einer abweichenden Browserzone formatiert.

## Präzise weiterhin offene Integrationsgatter

1. **Gemeinsames `AttendancePage`/geschützter GET:** `dashboard.php` steht
   absichtlich noch nicht in der Active-Page-Whitelist, keine Dashboardroute ist
   registriert. Der Controller behauptet keine andere aktive Seite. Daher ist
   diese Quelle vorbereitet, **nicht dispatch-/funktionsabgenommen**. Eine spätere
   Integration muss Sidebarzustand, Sessionblocking, `RefreshLocalAccount` →
   `auth:web` → `auth.session`, Authfehlernavigation und explizite Fixtureroute
   gesondert übernehmen. Kein Guard-/Credential-/Cookie-/Sessioninjizieren.
2. **TOAST-Ausführungsasset:** die unveränderte native TOAST-CSS-URL bleibt als
   Darstellungsvoraussetzung erhalten; das neue Blade lädt **kein externes
   JavaScript**. Es erwartet `tui.Calendar` aus einer später ausdrücklich geprüften
   Assetintegration. Keine neue Abhängigkeit wird hier heruntergeladen oder
   freigegeben. Das verhindert, dass der bloße Quellport die bestehende strengere
   Laravel-Chromegrenze gegen externe ausführbare Skripte still aufweicht.
3. **Geschützter realer Browserweg:** Login, echte Datenanbindung, Kalenderasset,
   Navigation/Back, DST-Ansicht und Modal sind nicht mit geschütztem Browser
   abgenommen. Bestehende Tool-/Authholds werden nicht umgangen. Kein Produktiv-,
   Host-, Auth- oder Sicherheitscutover und keine Aussage „Migration fertig“.

## Eng begrenzter grober Nachweis

Bestanden am 27.10.2026 unter PHP 8.5.12, separat eingefroren: Syntax der sechs
betroffenen PHP-Definitionen, Blade-Kompilierung (55 escaped Ausgaben und drei
feste Js-Encoder) samt PHP-Syntax des Ergebnisses, ein zusammenhängender
synthetischer Pure-array-Kalender-/Diagnose-/DTO-Nachweis und drei reine
Inhaltsrenderings (de, en, leere Woche) gegen ein leeres Layout ohne
Laravelboot/Chrome/Session. Je neun unveränderte Karten; kanonische
167-/169-Stunden-DST-Wochen, Überlappungsfilter, Feiertagsdedup und getrennte
Diagnoseanzeigen geprüft. JavaScript nur statisch auf Syntax geprüft.
Scope-/Preservationnachweis hält den additiven Umfang fest.

Kein Controllerdispatch, DBzugriff, Frameworkboot, Session-/Authinjektion,
Funktionstest, HTTP, Server, Browser, neuer CI-Lauf, Veröffentlichung oder
Deployment. Inhaltsrendern ist keine visuelle oder geschützte Funktionsabnahme.

## #45/A – Arbeitszeit und Abwesenheitsgutschrift in denselben Karten

Basis: Main `993d7e659219040cf8d0f9dbb64b54da4c80b286`, Tree
`7c7791f53025feed5fe5b76a94959abb0739826b`. Genau sieben Pfade ändern sich:
neuer definitionsreiner `lib/dashboard_presentation.php`, ausschließlich
`calendarWeek()`-Hunks in `LegacyChronoReads`, die bestehende Dashboard-Blade-
Vorlage, ihre drei Copyarrays de/en/zh und dieses Dokument. Native `dashboard.php`
und Sprachdateien bleiben unverändert; diese bewusste Fallbackgrenze bedeutet,
dass #45 nicht für beide Laufzeitwege als vollständig erledigt gilt.

Die bisherigen Wochen-/Monatswerte enthalten sowohl abgeschlossene Arbeitszeit
als auch abgeschlossene Abwesenheitsbuchungen. Der reine Helfer partitioniert
genau diese **bereits autorisierten kanonischen Netto-Minuten** mit
`report_record_calculation()` und `booking_is_absence()`. Er benutzt weder einen
eigenen Zeitparser noch neue Soll-/Urlaubs-/Krankheitsregeln. Auch historische
Abwesenheiten mit den bisherigen deutschen Kennzeichnungen behalten dieselbe
Zuordnung wie die bestehenden Kalender-/Konflikthelfer. Offene und ungültige
Zeilen werden nicht als abgeschlossene Zeit ausgegeben.

Die Gutschrift ist die bisher in den Report eingeflossene abgeschlossene
Abwesenheitsdauer. Insbesondere wird **nicht** aus `planned_minutes`, dem heutigen
Sollmodell oder einem Urlaubsanspruch eine andere Gutschrift berechnet.
Komponenten ändern keine Quellzeile und kein fachliches Ergebnis. Abgeschlossene
Nachtschichten bleiben vollständig ihrem Berliner Starttag zugeordnet, auch an
Wochen-/Monats-/Jahresgrenzen; Pausen und tatsächliche Zeitabstände einschließlich
DST bleiben beim kanonischen Kalkulator. Das ist keine Umsetzung von #46.

Die neun Karten, Reihenfolge, Icons, Farben, Formeln, Zahlenformate und sämtliche
Kalender-/Modal-/Navigations-/Assetteile bleiben bestehen. In der vorhandenen
Wochen- und Monatsstundenkarte kommen jeweils zwei umbrechende kleine Zeilen
für abgeschlossene Arbeitszeit und Abwesenheitsgutschrift hinzu. Die Hauptzahl
bleibt die bisherige kombinierte Summe. Neue Komponenten werden im Reader bei
demselben vollständigen Konflikt-/Invalidkontext auf `null` gesetzt und in der
Vorlage ausschließlich über dasselbe `DashboardPage::value()`-Masking angezeigt.
Es gibt keine finale Teilsumme, wenn die kombinierte Periode „Klärung offen“ oder
„Unvollständig“ ist; Konflikte behalten den bisherigen Vorrang.

Nur Texte werden fachlich präzisiert: die Prozentwerte heißen Sollabdeckung,
nicht Produktivität, weil ihr unveränderter Zähler Arbeitszeit plus Gutschriften
enthält. Die bestehende Begrenzung auf 0–100 % und der Null-Soll-Fall bleiben
explizit gleich. Der Tagesdurchschnitt bleibt auf abgeschlossene **Buchungstage**
bezogen, nicht ausschließlich tatsächlich gearbeitete Tage; auch die bestehende
Wochenzählung wird entsprechend beschriftet. Übertrag, Gesamtüberstunden,
Jahresdifferenz, Rest und sämtliche weiteren Formeln werden nicht verändert.

Die Seite nennt die Grenzen der **gesamten aktuellen Woche/des gesamten aktuellen
Monats** und bezeichnet das vollständige Periodensoll. Ein Kalenderwochenwechsel
ändert diese Kennzahlen weiterhin nicht. Es wird kein Live-/Stichtagssaldo,
Abschluss, lückenloses Vertragszeitkonto oder Wertstellungsmodell behauptet und
kein entsprechender Filter eingeführt. Der Überstundentooltip erläutert den
vorhandenen Übertrag-plus-Buchungstage-Vertrag, ohne Carry-in oder Salden zu ändern.
Alle neuen Texte/Werte werden durch die bestehende Bladeausgabe escaped.

Keine zusätzlichen SQLabfragen, Rechte, Subjektauswahl, Route, Auth-/Session-/
CSRF-/Konfigurationsänderung, Datenbank, Migration, Export oder externe Verbindung.
Insbesondere bleiben Teamstatus und sämtliche anderen Readmethoden bytegleich.
Laravel bleibt wie zuvor nur ausdrücklich opt-in und standardmäßig geschlossen;
kein Produktions-/Host-/Sicherheitscutover, keine Runtimeaktivierung.

Ein grober zusammenhängender synthetischer Nachweis prüft Partition, unveränderte
Reportminuten, Konflikt-/Unvollständigkeitsmasken und Inhalte der vorhandenen
neun Karten in de/en/zh, dazu PHP-/Blade-Syntax und Quellenintegrität. Der grobe
Nachweis und die Syntax-/Kompilierungsprüfungen bestanden am 27.10.2026 unter
PHP 8.5.12; keine neue Installation war nötig (identischer Composer-Lockstand).
Er verwendet
nur erfundene Arrays und eine leere Layoutgrenze, keinen Appbootstrap, keine DB,
Accounts, Sessions, HTTP/Server, Browser, GitHub-CI oder Remote-Schreibzugriffe.
Die getrennten eingefrorenen Nachweise nennen die tatsächlichen Ergebnisse und
unabhängige Quellenprüfung. Das ist keine geschützte oder visuelle Abnahme.
Der native Fallback wurde inzwischen separat ergänzt. Die bestehende Saldo-Karte
zeigt nun zusätzlich den Übertrag plus Differenzen nur der erfassten abgeschlossenen
Buchungsstarttage bis einschließlich des aktuellen Berliner Datums. Der gemeinsame
Einpasshelfer bewahrt Gesamtwert, Sollformel und SQLzahl; der vorhandene Snapshot-
Planresolver bleibt nur operationseigen. Beide Werte werden bei unklaren Daten
maskiert. Fehlende Solltage, Ein-/Austritt und Wertstellung werden nicht erfunden;
dies ist kein vollständiges Vertragskonto (#40/#61) und keine neue Runtimefreigabe.
