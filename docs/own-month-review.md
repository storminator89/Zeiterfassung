# #44 — eigene offene Vorgänge vor einer Monatsprüfung

Quellbasis Main `64948f8e87c72d26338595aef7afed0382198101`, Tree
`87fbcca1334b3ae9cb163ce4e8a4a7189aa4d3aa`, nach PR #351.
Das Originalkriterium „Offene Vorgänge vor Abschluss anzeigen“ trägt diese enge,
ausschließlich lesende Vorprüfung. Sie ist **kein Monatsabschluss**: keine Sperre,
Freigabe, Wiederöffnung, Revision, Payrollregel, Saldo-/Exportberechnung oder
neue betriebliche Zuständigkeit. #44 bleibt teilweise offen.

## Knapp und ausdrücklich in der vorhandenen Oberfläche

Eine standardmäßig geschlossene Karte auf der bestehenden Anwesenheits-/Home-
Seite bietet einen einzelnen GET-Monatswähler (`month_review=YYYY-MM`). Erst
bewusstes Absenden liest den neuen eigenen Stand. Normale Aufrufe, ungültiger
Monat und der bestehende `format=status`-JSONzweig führen keinen neuen Monatsread
aus. Kein stiller Fallback auf einen anderen angefragten Monat. Keine neuen
Assets, JavaScriptkontrollen, POST-Aktionen, Routen oder Runtimeflags.

Native zeigt die Karte vor den vorhandenen Statistiken im rechten Bereich;
Laravel nach den bestehenden eigenen Hinweisen und vor der Arbeitszeitliste.
Der native Vorspann verwendet dieselbe validierte explizite GET-Sprachauswahl wie
der unveränderte Header, ohne dessen Sessionablauf zu ändern. In Laravel gilt
dasselbe bereits vorhandene Home-Interaktionsflag wie für die Liste:
bei geschlossenem Homeflag oder fehlender optionaler Readerfähigkeit weder neue
Ansicht noch versteckter Read. DE/EN/ZH-Texte und vorhandene Details-/Card-/Link-
Klassen; kein Redesign. Bestehende Buchungs-/Abwesenheitsformulare, Pending-/
Status-/Retry-Semantik, History-/Export-/Korrekturwege bleiben unverändert.

Die kompakte Ausgabe enthält die bisherigen zwei und optional eine dritte Zählzeile, höchstens fünf eigene Beispiele,
Lesestempel und gewöhnliche Links auf vorhandene Listen/Korrektur. Beispiele
sind offene Buchungen in absteigender ID-Reihenfolge, dann Pending-Anträge und anschließend geschlossene Prüfhinweise,
insgesamt höchstens fünf. Bei mehr Treffern wird die Begrenzung ausdrücklich
benannt. Jedes Antragsbeispiel verlinkt seine exakte ID über den bereits
bestehenden Queue-Lookup (`request_status=pending&request_id=...`), ohne JS-Zahl-
oder Floatkonvertierung. Das Ziel lädt einen frischen Stand und behält die
bisherigen Sichtrechte sowie den Pending-Statusfilter. Ein inzwischen
entschiedener, verborgener oder fehlender Antrag kann deshalb keine Treffer
liefern; der Link öffnet nichts wieder und bestätigt keine Antragsentscheidung.
Der allgemeine Übersichtslink bleibt erhalten. Admins sehen in dieser
Vorprüfung trotzdem ausschließlich eigene Daten. Nichts wird automatisch
korrigiert, beendet, beantragt oder wiederholt.

## Nur Startdatumsfakten, keine erfundenen Monatsauswirkungen

Ein einziger autorisierter Lesesnapshot zählt:

- eigene offene Buchungen (`endzeit IS NULL` oder leer): auswertbarer Berliner
  Start im Monat, früherer offener Start und nicht verlässlich bestimmbarer
  Start getrennt. Spätere bekannte Starts bleiben außerhalb. Ein früherer offener
  Start beweist **keinen** Bezug zu diesem Monat; kein Ende wird erfunden.
- eigene noch `pending` Anträge: auswertbarer Original- oder Vorschlagsstart im
  Monat; nicht zuverlässig klassifizierbare Anträge separat als zeitlich unklar.
  Bei bestehenden Buchungen müssen die erforderlichen Originalstartdaten und bei
  Nichtstorno auch der Vorschlagsstart auswertbar sein. Ein Nachtrag erfordert
  wirklich NULL-Original/NULL-Referenz. Eine NULL-Referenz mit vorhandenem Original oder eine nicht darstellbare
  Referenzform wird nicht als Nachtrag rekonstruiert. Storno verwendet nur den Originalstart.
  Zeit-, Nachtrags-, Storno- und Ort-/Notizanträge bleiben derselbe bestehende
  Pending-Bestand. Entschiedene/zurückgenommene Anträge werden nicht wieder geöffnet.

Verwendet werden unveränderte `legacy_timestamp_assessment`, `booking_time`,
`booking_local_date`, die bestehende Vorschlags-Integerprojektion und exakte
positive ID-Prüfung. Keine zweite Zeitzonen-/Storno-/Freigaberegel. Offsetfreie
eindeutige Bestandszeiten behalten die vorhandene Berlin-Annahme; unterstützte
explizite Offsets bleiben Instants. Lücken, doppelte Herbststunde ohne Offset,
unlesbare/unerwartete Formen, nichtkanonische/duplizierte Original-JSON oder
überlange Werte werden **unklar**, nie einem Nachbarmonat zugeordnet.

Die Originalprüfung bestätigt nur die kanonische JSON-Darstellung, Referenzform
und das benötigte Startzeitfeld, **nicht** vollständiges Zeilenschema, aktuelle
Referenzexistenz/ID-Owner-Konsistenz oder die reservierte Metadatenversion.
Daraus entsteht keinerlei Freigabe- oder Snapshotintegritätszusage.

Die unveränderte Starttagkonvention ist ausdrücklich **keine** Ermittlung aller
betroffenen Monate: Nachtintervalle werden nicht aufgeteilt, Enddaten oder
Pausenlagen nicht ergänzt. Frühere offene und zeitlich unklare Fälle gehören zu
keinem bestätigt ausgewählten Monat und bleiben getrennt. Null Treffer ergibt
keinen grünen „abschlussbereit“- oder Qualitätsstatus. Fehlende Solltage, andere Konten/Teams, vollständige Bestands-/Ereignis-
integrität und Abschlussanforderungen sind nicht Bestandteil dieser Vorprüfung.

## Eigene Autorität und Datenminimierung

Die neue optionale `OwnMonthReviewReads`-Fähigkeit nimmt nur den validierten
Monat, keinen Owner oder Rollenparameter. Der kanonische `LegacyChronoReads`
verwendet unverändert seinen frisch geladenen Actor und Snapshot mit bisherigen
Verbindungs-/Idle-/Source-/Fingerprint-/Generations-/Rollen-Gates. Alle bisherigen
Methoden einschließlich des gesamten Snapshotkörpers bleiben exakt erhalten.

Native prüft den bereits authentifizierten eigenen Sessionactor/Fingerprint
und aktuelle Rolle nochmals im eigenen Lesesnapshot, **vor Headerausgabe**;
verlorene Autorität bleibt ein geschützter Fehler. Beide Leser verweigern eine
geliehene Transaktion, statt sie zu übernehmen. Technische Fehler werden als
nicht verfügbar angezeigt, nie als leere/saubere Monatsprüfung. Die Karte ist
ein eigener Snapshot, keine Behauptung atomarer Gesamtseite mit den bisherigen
Statistik-/Status-/Open-Review-Reads.

Alle drei SQL-Reads binden ausschließlich den vertrauenswürdigen Owner. Zeilen
werden gestreamt; Zähler und höchstens fünf IDs bleiben übrig. Rohwerte werden
vor PHP-Materialisierung begrenzt (Zeitform, Integerfelder, maximal 64-KiB-Original);
zu große Werte bleiben unklar, nicht abgeschnittene gültige Daten. Keine Gründe,
Notizen, Orte, Namen, Healtharten, Owner-/Reviewer-/Credential-/Generationsfelder
werden zurückgegeben. Die minimale DTO übernimmt nur Monat, Lesestempel, fünf
nichtnegative bisherige Zähler und optional zwei getrennte geschlossene Zähler, begrenzte exakte ID-Strings/Art/Bezug und Limitflag; unbekannte
Felder fallen weg. Native escaped und Blade-escaped Ausgabe, keine JS-Zahlrundung.

## Grober Nachweis und Grenzen

PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0: ein achtteiliger synthetischer
Memory-Journey mit echten Fixture-/Migrations-/Auditdefinitionen, tatsächlichem
Helper, kanonischem Reader, aktuellem Attendancecontroller/DTO und kompiliertem
bestehendem Inhalt/Partial in drei Sprachen. Default/ungültig/Status/fehlende
Fähigkeit/geschlossenes Homeflag, eigene User-/Admin-/LDAP-Supervisor-Sichten,
Original-/Vorschlagsmonatswechsel, Nachtrag/Storno/Metadaten, DST-unklar,
überlange Legacywerte, exakte große ID, frischer Terminalstatus, fehlgeschlagener
Read, geliehene Transaktion und aktuelle Autoritätsverluste wurden geprüft.
Query-only-Verbindung, Daten-/Schema-/Audit-/Change-Digests sowie Session und
Zeitzone bleiben während Reads unverändert. Native tatsächlicher neuer
Preoutput-Zweig/Partial wird mit reinem Array-Sessionkontext ausgeführt.

Im ersten Harness fehlten Defaultparameter eines absichtlich niemals aufgerufenen
Writerdoubles; ausschließlich Harness korrigiert. Die Komponentenprüfung zeigte
außerdem, dass der neue Read an dasselbe Homeflag wie sein bestehender Zielbereich
gebunden werden muss; das wurde im Produkt ergänzt und positiv/negativ geprüft.
Eine synthetische überlange rein numerische Referenz wurde von SQLite als Infinity
statt TEXT gespeichert und vom vorhandenen Digest verweigert; die beabsichtigte
überlange TEXT-Fixture erhielt einen nichtnumerischen Präfix. Kein Produktcode,
Trigger oder Datenintegritätsschutz wurde dafür abgeschwächt.

**Kein** vollständiger App-/Runtime-/V5-/Login-/Auth-/Sessionmiddleware-/HTTP-/
Browser-/Layout-/Konkurrenz-/Performance-/Betriebsnachweis. Guard ist RequestGuard-
Double, Layout inert, Session Arraystore mit festem Darstellungstoken. Keine
neue Abschluss-/Payroll-/Exportabnahme, echten Konten, nutzbaren Credentials,
Schlüssel, Dateidatenbank, Server oder externen Aufrufe. Keine neue Testsuite,
manuelle CI oder Aktivierung/Deployment. #44 bleibt für die eigentlichen
Periodenfreigabe-/Sperr-/Revisions-/reproduzierbaren Exportkriterien offen.

## Gezielte Navigation zum bereits vorhandenen Antrag

Die Antragsbeispiele verwenden nur den vorhandenen exakten Queue-Lookup;
keine neue Antragsdetailroute und kein neuer Read werden eingeführt. Der Link
enthält ausschließlich den Pending-Status und die vom unveränderten eigenen
Snapshot/DTO bereits validierte dezimale Antrags-ID. Er enthält weder eine
Buchungs-ID, einen Owner noch eine Aktion, Vorschau- oder Erfolgsmeldung. Die
bestehenden offenen Buchungslinks, Zähler, Reihenfolge, Fünfergrenze, Hinweise
und allgemeine Listenlinks bleiben unverändert. Die Zielqueue beurteilt ihre
aktuellen Sichtrechte erneut; es entsteht keine Freigabe oder Reservierung.

Grober Zusatznachweis unter PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0:
der bestehende achtteilige eigene Memory-Read-/Controller-Journey läuft weiter.
42 tatsächliche Native-/kompilierte Blade-Darstellungen in DE/EN/ZH decken
Default, ungültig, nicht verfügbar, leer, offene Buchungen, Anträge und gemischte
Beispiele ab. 30 Antragslinks behalten die exakte ID einschließlich >2^53 und
PHP_INT_MAX; der unveränderte Queue-Selector liest sie unverändert ein. Nach
Entfernen ausschließlich der neuen Anker sind sämtliche bisherigen Renderbytes
identisch. Wiederholte Darstellung und unveränderte query-only Quelldaten,
Schema, Change-Zähler, Transaktionen, Session und Zeitzone sind geprüft.

Dies ist ein Navigations-/Komponentennachweis, keine tatsächliche Queue-
Entscheidung oder vollständige Browser-/Auth-/CSRF-/HTTP-/Betriebsabnahme.
Keine neuen fachlichen Zustände, Writer, Testsuite, manuelle CI oder Bereitstellung.


## Geschlossene Datums-/Pausenprüfhinweise — weiterer Teilumfang #55/#44

Quellbasis tatsächlicher Main `bb980b1f2af4d34aab8fa5d40f34c70103f18f90`,
Baum `9104f0851fc3e6b1838e170bf890317bbfaa3c8a`. Bisher waren geschlossene
Bestandszeilen ausdrücklich nicht Bestandteil der eigenen Monatsvorprüfung:
Eine Person konnte dort keine Treffer sehen, obwohl die eigene Liste bekannte
Datums-/Pausenprobleme enthielt. Der zusätzliche reine Read ergänzt jetzt zwei
getrennte `closed_counts` und bis zur bisherigen Gesamtgrenze fünf Beispiele:
Prüfhinweis mit eindeutigem Berliner Start im angefragten Monat und geschlossener
Eintrag mit nicht verlässlich bestimmbarer Startzuordnung. Bekannte Starts
außerhalb des Monats bleiben außerhalb; kein Enddatum verteilt eine Nachtschicht
auf andere Monate. Ein unklarer Start hat weiterhin keinen bestätigten Monatsbezug.

Die ursprünglichen offenen/Pending-Reads, ihre fünf Zähler und ihre priorisierten
Beispiele bleiben unverändert. Anschließend liest ein fester ownergebundener SELECT
nur geschlossene Zeilen (`endzeit` weder NULL noch leer), mit auf 25 Bytes
begrenzten Textzeitfeldern, höchstens 20 Bytes Pause und einem internen
Pause-Prüfbarkeitsflag. Zeilen werden gestreamt. Nur Zähler, exakte eigene ID,
feste Art `closed` und Startbezug bleiben im DTO; keine Rohzeit/Pause/Fehlertexte,
Notiz, Art der Abwesenheit, Namen oder Personendaten kommen hinzu.

Kein zweiter Zeitparser: `legacy_timestamp_assessment`, der vorhandene strikte
Starttagshelfer und `report_record_calculation` prüfen nicht eindeutige/ungültige
Zeitformen, Endreihenfolge sowie ungültige/negative/überlange Pausen. Ein nicht
begrenzt prüfbarer Pausenwert heißt **Prüfhinweis**, nicht bestätigter Fehler oder
Nullpause. Gültige normale/nullpausige und Null-Minuten-Zeilen erzeugen keinen
neuen Hinweis. Offene Zeilen bleiben ausschließlich im vorhandenen Open-Read.
Keine neue Überlappungs-, Soll-/Saldo-/Integritäts-, Urlaubs- oder Abschlussregel.

Die vorhandene Native-/Laravel-Karte zeigt dieselbe dritte Zählzeile in DE/EN/ZH
und führt geschlossene Beispiele über denselben bisherigen `record_id`-Link zu
Korrektur/Storno. Weder Antrag noch Storno wird automatisch ausgelöst. Fehlende
neue DTO-Felder eines älteren optionalen Readers bleiben unbekannt: keine neue
Zählzeile und kein erfundener Null-/Sauberkeitsnachweis. Leermeldung berücksichtigt
alle tatsächlich gelieferten Kategorien, bestätigt aber weiterhin keine Qualität
oder Abschlussreife. Leser-/Controller-/Home-/Auth-/Status-/Versions-/Generations-
Gates, Routen, Snapshotgrenzen und alle schreibenden Pfade bleiben unverändert.

Grober PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0 Memory-Nachweis führt den
bestehenden achtphasigen Helper/Reader/Controller/DTO/Native-/Blade-Journey
unverändert fort. Zusätzlicher ursprünglicher-Schema-Abschnitt reproduziert die
bisher ausgelassenen geschlossenen Fälle, bekannte/unklare Monatsstarts,
DST/Offset/Nacht/außerhalb/offene/valide Nullfälle, begrenzte Rohwerte und große
exakte ID; eigene User-/Admin-/Supervisorgrenzen, alter DTO ohne neue Nullbehauptung,
Native-/kompilierte Blade-Ausgabe in drei Sprachen sowie Source/Schema/Audit/
Änderungszähler/query_only/Transaktionen/Session/Zeitzone werden geprüft.
Notwendige PHP-/kompilierte Blade-Syntax und Vollbaum-Erhaltung separat geprüft.
Kein App-/Bootstrap-/echter Auth-/CSRF-/HTTP-/Browser-/Layout-/Runtime-/V5-/
Großbestands-/Konkurrenz-/Produktivnachweis, keine realen Daten/Konten/Credentials/
Schlüssel/Dateidatenbank, neue Testsuite/manuelle CI, Aktivierung oder Deployment.
#55 und #44 bleiben teilweise/offen; keine Reparatur, Mapping, Abschluss-/Payroll-
oder vollständige Qualitätsabnahme. Browserabnahme bleibt beim Nutzer.

Ein anfänglicher neuer Harness versuchte BLOB-Zeitfelder in der ursprünglichen
Audit-Fixture anzulegen; deren unveränderter JSON-Audittrigger verweigerte das.
Nur die nicht repräsentierbare Fixture wurde entfernt, weder Trigger noch
Produktzulassung abgeschwächt. Ein zunächst numerisch wirkender überlanger
Pausentext wurde außerdem von SQLite zu INTEGER 0 normalisiert; der beabsichtigte
überlange TEXT-Fall erhielt deshalb einen nichtnumerischen Fixturepräfix. Dies
ist kein Nachweis einer realen Datenmigration oder Zulassung beschädigter Quellen.
