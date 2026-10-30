# #50 / #79 – eigener Home-Stand mit einem offenen Antrag

Begrenzter Leseslice auf Main `3cdcf90f5cf6331f2d57940e0ee3eb602d5907f7`.
Die vorhandene Laravel-Antragskarte bietet für einen **eigenen offenen** Antrag
„Home-Stand mit Antrag prüfen“. Ein normaler GET lädt genau eine ausdrücklich
gewählte Vorschau; kein Antrag, keine Entscheidung oder Rücknahme wird gesendet.
Vorhandene Formulare, Navigation, Klassen, gespeicherte Dauer-Vorschau und
Vier-Augen-Regel bleiben erhalten. Die native Korrekturseite bleibt unverändert.

## Keine neue Zeitkontopolitik

„Jetzt“ und „Mit diesem Antrag“ verwenden exakt die bestehende Home-Projektion:
Startguthaben plus Minuten abgeschlossener **Bericht-Starttage** minus deren
jeweiliges historisches Soll. `report_days`, `home_total_overtime`,
`planned_minutes` und `report_format_hours` bleiben unverändert. Dies ist kein
vollständiges Vertragszeitkonto; fehlende Arbeitstage werden nicht erfunden.
Andere offene Anträge werden nicht kumuliert, eine spätere Freigabe nicht zugesagt.
Die Anzeige ist der aktuell gelesene hypothetische Stand, nicht ein historisch
zum Antragszeitpunkt gespeicherter Kontosaldo.

Der neue Helfer komponiert nur die bestehende Leserechnung und eine Kopie der
Projektion. Er ersetzt/ergänzt/entfernt genau die Zeile dieses Antrags so, wie der
vorhandene Kern ihre Berichtsfelder verändern würde. Beide vollständigen
Starttagmengen werden neu berechnet: Eine neue, entfernte oder verschobene letzte
Tagesbuchung verändert auch das abgezogene Tages-Soll. Ein bloßes Addieren der
bereits vorhandenen Netto-Dauerdifferenz wäre deshalb falsch.
Offsetzeitpunkte werden mit den kanonischen Helfern nach Berlin dargestellt;
Nachtschichten bleiben vollständig ihrem Starttag zugeordnet. Feiertage und
historische Modellversionen kommen unverändert aus `planned_minutes`.
Gespeicherte Urlaub-/Krankheitsgutschriften werden nicht zum neuen Modell-Soll
umgerechnet. Die geplanten Minuten einer vorgeschlagenen Gutschriftenkorrektur
entsprechen nur dem unveränderten Korrekturkern, keiner neuen Anspruchsprüfung.

## Ein vorhandener eigener Lesesnapshot, fail closed

`correctionsPage` erhält einen optionalen dritten Parameter. Ohne explizite
positive skalare Ganzzahl `balance_preview` im GET bleibt die Vorschau aus;
POSTs und ungültige/Array-/Überlaufwerte lösen sie nicht aus. Nur eine auf der
aktuell sichtbaren Statusseite bereits enthaltene, eigene und weiterhin offene
Antrags-ID kann zusätzlichen Kontext laden. Keine freie Benutzer-ID, keine
Team-/Admin-Kontosalden und kein Durchsuchen anderer Antragsseiten.

Antragsliste, aktueller eigener Datenbestand, Startguthaben, Sollmodelle und
hinterlegte Feiertage teilen dieselbe bestehende frische `DEFERRED`-Lesetransaktion
mit unverändertem Guard-/Kontoabgleich. Der Controller führt keine SQL aus.
Zusätzliche ganze Historie und Sollberechnung werden höchstens für **eine**
explizite Vorschau geladen, nicht fünfzigmal beim normalen Listenaufruf.

Der aktuelle Originaldatensatz muss wie im vorhandenen Freigabekern exakt zum
gespeicherten Original-JSON passen. Laufende Sitzungen, ungültige Vorschlagswerte,
überlappende vorgeschlagene Zeiträume oder ganztägige Gutschriften werden über
die unveränderten kanonischen Validierungs-/Overlap-Lesehelfer abgewiesen.
Ungültige Daten, bestehende oder hypothetische Arbeit-/Abwesenheitskonflikte,
fehlender/veränderter Originaldatensatz, unbekannte Startguthaben und technische
Auswertungsfehler liefern ausschließlich den festen Nichtverfügbarhinweis;
keinen partiellen Saldo, keine erfundene Null und keine Fehler-/Rohdaten.
Die Vorschau bewahrt die bestehende Home-Rechnung und deren Invalid-/Konfliktgrenzen;
zusätzlich verhindert der unten beschriebene strenge Zeitdiagnose-Gate eine
numerische Vorschau aus nicht verlässlich darstellbaren beitragenden Zeiten.
Sie ist keine neue allgemeine Prüfung historischer Arbeit-/Arbeitsüberschneidungen,
der Qualität hinterlegter Modelle oder der Vollständigkeit des Feiertagskalenders.
Auch eine mögliche Reparatur eines Konflikts erhält keinen numerischen
„Jetzt“-Stand aus unvollständigen Daten. Vorschauwerte sind escaped Text;
Originalsnapshot, Kontodetails und Exceptions gelangen nicht in die Vorlage.

Keine Writer-/Schema-/Audit-/Auth-/Session-/Routen-/Provider-/Flagänderung,
keine neue Verbindung, Migration oder Feiertagsbeschaffung, kein Browserstorage,
keine automatische Wiederholung, keine Vertretungs-/Eskalationspolicy und keine
produktive Aktivierung. Die tatsächliche Entscheidung prüft den aktuellen
Datensatz erneut unter dem unveränderten Writerlock.

## Begrenzter grober Nachweis

Zwei einzelne synthetische Abläufe unter PHP **8.5.12**, SQLite **3.46.1** und
Laravel **13.38.0**, ausschließlich mit erfundenen Daten:

- Ein anonymer Memory-PDO prüft die tatsächlichen kanonischen Projektionshelfer:
  normale Nettoänderung, verschobener/neuer/entfernter Starttag mit anderem Soll,
  Feiertag, doppelte Herbststunde mit beiden expliziten Offsets, gespeicherte
  Urlaubsgutschrift sowie stale/foreign/final/active/invalid/Konfliktfälle.
  Der letzte Query-only-Durchlauf bestätigt unveränderte erfundene Berichtsdaten.
- Ein echter Controller-/DTO-/kompilierter-Blade-GET mit erfundenen Read-/Authports
  prüft den Selektor, eigene statt fremde Links, Rückgabewerte/Beschriftung,
  ungültige Werte, Queue-/Buchungsauswahl und fehlenden Befehlsaufruf.

Notwendige PHP-/kompilierte-Blade-Syntax und `git diff --check` werden gesondert
festgehalten. Dies ist **keine** Abnahme des tatsächlichen Reader-Auth-/PDOstacks,
HTTP-/CSRF-/Browserinteraktion, Freigabe-/Writer-/Commitfehlern, Konkurrenz oder
Produktion. Keine reale DB/Datei, Konten, Betriebsdaten, Zugangsdaten, Schlüssel,
Appserver, neue GitHub-CI oder Remote-Schreibzugriffe. Vollständige #50/#79,
Vertretung/Eskalation und produktive Freigabe bleiben offen.


## Ergänzung: keine numerische Home-Vorschau aus unklaren beitragenden Zeiten (#55/#50)

Vorbereitet gegen actual Main `ea9bd198a764c5db39de8109b377f6b51e7f4d46`, Baum
`3ca860a9062464146785119ea300296c1f82b558`. Die gespeicherte Dauer-Vorschau
verweigert inzwischen strenge Altzeitbefunde, während diese separate Home-Vorschau
noch numerische Vorher-/Nachherwerte aus einer geschlossenen offsetfreien doppelten
Herbststunde liefern konnte. Der alte grobe Ablauf reproduziert diesen Widerspruch.

Der reine bestehende Helfer nutzt nun zwei bereits vorhandene Diagnosegrenzen:

- Geschlossene Bestandszeilen im vollständigen autorisierten eigenen Snapshot
  müssen mit beiden gespeicherten Endpunkten die unveränderte
  `legacy_timestamp_assessment()` bestehen. Doppelte Stunde, Frühlingslücke,
  nicht unterstützte lesbare Form und strenger Ungültigbefund ergeben nur das
  vorhandene Unavailable-Tupel (`false/null/null/null`), keine gewählte Altzeit.
- Ein Nichtstorno-Vorschlag muss die unveränderte strenge Dauer-Vorschau als
  `proposed_usable` bestehen. Damit kann die Home-Vorschau insbesondere keine
  historischen Sekundenoffsets durch das bestehende Minutenoffsetformat runden
  und trotzdem numerische Werte behaupten. Storno prüft weiterhin keine ignorierten
  Vorschlagszeitfelder.

Offene Zeilen werden nicht neu pauschal wegen eines strengen Altzeitbefundes
abgewiesen: sie tragen nach dem unveränderten Home-Vertrag keine abgeschlossene
Zeit bei. Auch die ausdrückliche Korrektur eines offenen Altbestands behält ihre
bisherige hypothetische Projektion; laufende Sitzungen und Laufzeit-Invalidität
bleiben separat durch die alten Guards verweigert. Fehlende Pause behält den
vorhandenen kanonischen Default, statt nebenbei eine neue Feldformregel einzuführen.

Alle numerischen Formeln, Home-Istwerte, Soll-/Feiertags-/Gutschriftenregeln,
Owner-/Original-/Status-/Session-/Overlap-Gates, Reader-/Scope-/Transaktionsgrenzen,
Writer/Freigaberegeln, Quelle, Routen, DTOs und UI bleiben unverändert. Die bereits
vorhandene Native-/Blade-Anzeige übernimmt lediglich den schon vorhandenen festen
Nichtverfügbarzweig. Kein vollständiges Vertragskonto, Reparatur/Mapping,
Wertstellung, Vertretung/Eskalation oder neue Runtime-/Host-/Security-Freigabe.
Abgelehnte separate Queue-/API-/Writerpakete werden nicht transportiert.

**Grobe Evidenz:** PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0, unveränderter
vorhandener Composerlock. Der vorhandene erfundene Memory-Journey wurde mit
altem/neuem tatsächlichem Helfer erneut durchlaufen: 15 bisherige Normal-/Kalender-/
Neuantrag-/Storno-/Gutschrift-/Verweigerungsfälle exakt gleich. Ein zusammenhängender
Zusatzlauf unter UTC und Los Angeles reproduziert 50 vormals numerische neue
Zeitdiagnose-Verweigerungen an Ziel- oder anderer beitragender Zeile sowie
historischem Vorschlag; zwölf besondere bisherige Fälle bleiben exakt gleich
(offene/leer beendete ignorierte Zeilen, offener Korrekturfall, Defaultpause,
Storno mit ignoriertem Sekundenoffsetvorschlag). Zwei alte/neue Ergebnisse in den
unveränderten tatsächlichen Native-/kompilierten-Blade-Vorschaublöcken ergeben vier
geprüfte Darstellungen: neuer fester Nichtverfügbartext ohne alte Zahlen. Quelle,
Schema, Changecounter, Query-only und caller-eigene Lesetransaktion sowie native
Globals/Zeitzone bleiben erhalten. PHP- und kompilierte Blade-Syntax und Diffcheck PASS.

Das sind minimale anonyme Memory-Daten und extrahierte Ansichten, keine ganze
Reader-/Auth-/App-/Nativebootstrap-/HTTP-/CSRF-/Browser-/Layout-/Writer-/Freigabe-/
Konkurrenz-/Betriebs-/Produktionsabnahme; keine tatsächlichen Konten/Daten/Keys,
Zugangsdaten, DateiDB, Server oder Bereitstellung. Kein neuer Repo-Testkatalog oder
manueller CI-Start; natürliche exakte Head-/Main-CI separat. #55/#50 bleiben offen.
Evidenz: `/tmp/chrono-balance-preview-timestamps-mainea9bd198-support/` mit
existing-coarse.json, coarse.json, proof.json, candidate.patch und unabhängiger
Prüfung. Temporärer Speicher bleibt schlank mit vorhandenen Objekt-Alternates;
keine neue vollständige Mirror-/Cold-Bundle-Kopie beansprucht.
