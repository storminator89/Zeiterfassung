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

Die kompakte Ausgabe enthält zwei Zählzeilen, höchstens fünf eigene Beispiele,
Lesestempel und gewöhnliche Links auf vorhandene Listen/Korrektur. Beispiele
sind offene Buchungen in absteigender ID-Reihenfolge, dann Pending-Anträge,
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
keinen grünen „abschlussbereit“- oder Qualitätsstatus. Geschlossene fehlerhafte
Zeilen, fehlende Solltage, andere Konten/Teams, vollständige Bestands-/Ereignis-
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

Beide SQL-Reads binden ausschließlich den vertrauenswürdigen Owner. Aktive Zeilen
werden gestreamt; Zähler und höchstens fünf IDs bleiben übrig. Rohwerte werden
vor PHP-Materialisierung begrenzt (Zeitform, Integerfelder, maximal 64-KiB-Original);
zu große Werte bleiben unklar, nicht abgeschnittene gültige Daten. Keine Gründe,
Notizen, Orte, Namen, Healtharten, Owner-/Reviewer-/Credential-/Generationsfelder
werden zurückgegeben. Die minimale DTO übernimmt nur Monat, Lesestempel, fünf
nichtnegative Zähler, begrenzte exakte ID-Strings/Art/Bezug und Limitflag; unbekannte
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
