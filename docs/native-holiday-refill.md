# #41 — kontrollierte BW-Feiertagsergänzung in der Native-Administration

Quellbasis Main `4f6035a1c98eda82e5a402c4283252aaf19b73c0`, Tree
`06c855b4c61262248f58f3cbecf3e77bb5a50225`, nach PR #350. Dies ist die separat
umgesetzte Native-Parität zum bereits veröffentlichten [Laravel-Vertrag](framework/holiday-refill.md)
aus #349. Dessen `holiday_refill.php` bleibt **unverändert** die einzige fachliche
Validierungs-/Konflikt-/Ergänzungsregel. Kein Abruf, neuer Endpoint, Schema,
Frameworkbootstrap, Asset, Dienst, Regionmodell oder Runtime-/Host-/Securitywechsel.

## Benutzbarer Ablauf im bestehenden Adminlayout

Die standardmäßig geschlossene Karte **Feiertagsbestand ergänzen ·
Baden-Württemberg** steht vor der unveränderten Benutzerverwaltung. Nur die
aktuelle lokale Verwaltung mit bestehender nativer Sitzung sieht das Formular;
die Anzeige ist keine Schreibautorität. Der Operator prüft den globalen Bestand,
ruft die vollständige BW-Jahresantwort selbst ab, prüft Jahr/Standort/Inhalt,
fügt sie ein und bestätigt die Auswirkungen bewusst. Kein automatischer Abruf,
Autofill, Submit, Replay oder persistierter Rohdatenentwurf.

POST benutzt das unveränderte native `csrf_field()` und die bestehende
`security.php`-/`config.php`-Authentifizierung vor dem neuen Zweig. Die feste
Aktion wird **vor allen** bisherigen Adminmutationen abgefangen. Neben CSRF
sind ausschließlich `refill_holidays=1`, `holiday_year`, `confirm_bw_source=BW`
und `holiday_json` erlaubt. Gemischte Konto-/LDAP-/Pausen-/Abteilungs-/Audit-/
Quellenfelder verweigern die gesamte neue Aktion, ohne einen alten Zweig zu
betreten. Bestehende Adminformulare und ihre Mutationshandler bleiben bytegenau erhalten;
die frühe Feiertagsfehler-Darstellung wird im unten beschriebenen Teilumfang erweitert.

Der Wrapper validiert zuerst die unveränderte geschlossene 128-KiB-JSON-Struktur,
verlangt eine inaktive SQLite-Verbindung im Exceptionmodus und besitzt dann
ausschließlich deren eigenen `BEGIN IMMEDIATE`-/COMMIT-/ROLLBACK-Lebenszyklus.
Unter diesem Lock lädt er Rolle, Quelle und Passwortfingerprint des ausschließlich
serverseitig übergebenen Sessionactors neu. Stale Identität, fremdes Konto,
nichtlokale Quelle und Nichtadmin werden nicht durch Sessionanzeige oder Formular
überstimmt. Keine LDAP-Rechteausweitung oder Credentialänderung.

Der unveränderte Kern parst die Rohdaten unter demselben Lock erneut. Alle
vorhandenen Daten **und Namen** des gewählten Jahres müssen exakt zur gelieferten
landesweiten Antwort passen, bevor ein INSERT erfolgt. Konflikte stoppen das
gesamte Jahr; kein REPLACE/IGNORE, Löschen, Umbenennen oder Reparieren. Nur fehlende
Daten werden eingefügt. Original-IDs/-werte und andere Jahre bleiben erhalten.
Bedingte Einträge mit Hinweis werden wie im Bestand ausgelassen und gezählt;
Quelle und Vollständigkeit werden dadurch nicht attestiert.

Vorhandene INSERT-Audittrigger erfassen genau die neuen Zeilen mit festem
`web:ID`, BW/Jahr und SHA256 der **operatorgelieferten Bytes**. Hash bedeutet
Korrelation, keine Herkunftsprüfung. Der Wrapper speichert und restauriert den
vorherigen globalen Auditkontext auf Erfolg und Fehler. Ein identischer erneuter
bewusster Aufruf ist ein No-op ohne neue Zeilen oder Audit.

Nur bestätigter COMMIT erzeugt einen festen 303-Redirect zu derselben Karte und
einen minimalen einmaligen, actor-gebundenen Sessionbeleg: Jahr und drei begrenzte
Zähler, ohne JSON, Namen, Hinweise, Hash, Authdaten oder Draft.
Tatsächlich angezeigt werden Jahr, eingefügte/unveränderte Zeilen und ausgelassene
Hinweise sowie der bestehende Dashboard-Jahrgangslink. Fremde oder fehlerhafte
Belege werden verworfen. Jeder neue Ergänzungs-POST entfernt einen alten Beleg
vor der Verarbeitung; POST-Antworten zeigen keine alte Erfolgsmeldung. Rechte-/unbekannte Fehler und
gemischte Kommandos behalten den festen nativen Abbruchweg. Bekannte einzelne
Validierungs-/Konfliktverweigerungen können im unten beschriebenen Teilumfang
das bestehende Formular unter demselben 400-/409-Status erneut anzeigen. Unbekannter COMMIT-/ROLLBACK-/PDO-Ausgang
wird nur als unbestätigt 503 mit Hinweis auf Neuladen/Bestandsprüfung behandelt,
nie als sichere Rücknahme oder automatische Wiederholung. Eine geliehene
Transaktion wird weder benutzt, committed noch zurückgerollt.

Neu gelesene Soll-/Saldoanzeigen nutzen den ergänzten Kalender auch für ältere
Zeiträume. Bereits gespeicherte Abwesenheitsgutschriften werden nicht neu berechnet.
Dies steht vor der Bestätigung ausdrücklich im Formular.

## Grober Komponentenbeleg und offene Grenzen

PHP 8.5.12 / SQLite 3.46.1, ein zusammenhängender synthetischer Memory-Journey in
zehn Phasen: echte Bestandsfixture/Migratoren/Trigger, tatsächlicher Nativewrapper
und unveränderter Kern, Teilbestand im Folgejahr, Originale/anderes Jahr/Audit,
identischer No-op, Ganzjahreskonflikte, gemischte/ungültige Eingaben, aktuelle
lokale Adminrolle/Fingerprint, später INSERT-Fehler mit atomarem Zeilen-/Audit-
Rollback sowie geliehene Transaktion. Alle nichtkalenderbezogenen Fachzeilen und
der ursprüngliche Schema-SQL bleiben erhalten; der reine Harness fügt für den
Fehlerfall kurz einen erfundenen Trigger hinzu und entfernt ihn wieder.

Tatsächlicher neuer Anzeigevorspann und Native-Partial mit echtem `csrf_field()`,
minimaler einmaliger eigener Beleg, geschlossene/leere und unberechtigte Ansicht,
keine Rohdaten-/Fingerprintausgabe; tatsächlicher früher gemischter Dispatch
verweigert. Die unveränderte CSRF-Entscheidung wird isoliert mit einer Arraysitzung
für fehlenden/falschen/Array- und korrekten festen Token ausgeführt. Drei PHP-
Syntaxprüfungen, Diffcheck und vollständige Quellerhaltung sind getrennte Belege.
Der erste Harnesslauf referenzierte eine laut Schemamap optionale, von der Fixture
nicht angelegte `login_attempts`-Tabelle und brach vor dem Featureaufruf ab; nur der
Harness wurde korrigiert, kein Produktcode oder Trigger abgeschwächt.

**Kein** vollständiger App-/Native-Auth-/Login-/Cookie-/Sessionmiddleware-/HTTP-/
Browser-/Layout-/Konkurrenz-/COMMITverlust-/realer Quellen-/Betriebsnachweis.
Keine echten Konten, nutzbaren Credentials/Hashes, Secrets, Dateidatenbank,
Server oder externe Abfragen. Keine neue Testsuite/manuelle CI und kein Deployment.

#41 bleibt teilweise offen: kein dauerhaftes Jahr-/Region-/Herkunfts-/Vollständigkeits-
oder Fehlerstatusmodell, keine andere Region, keine automatische Quellenprüfung,
kein Editor für Konflikte, keine rechtliche Kalendergarantie und keine Neuberechnung
alter Gutschriften. Die bestehende Bestandsanzeige bezeichnet die Zeilen weiterhin
als ungeprüft. Downloaderkörper und normale Lazyimport-Aufrufe bleiben unverändert;
die unten beschriebene bekannte Fehlerantwort überspringt allein dessen Callsite.
Der historische Lazyimport wird damit nicht als vollständig gelöst erklärt.


## Bekannte Verweigerung im selben Native-Formular bearbeiten — Teilumfang #41

Basis tatsächlicher Main `0c78588dc5d7cd1e06def7fab0babd2ffd71fc9c`, Baum
`f221e82a1c36797a53f633a07bf603be6e292c54`. Laravel verwendet bereits den reinen
`holiday_refill_rejected_draft`-Helfer. Native brach bei 400/409 zuvor mit reinem
Fehlertext ab; Jahr und bis zu 128 KiB eingegebenes JSON fehlten im Formular.
Nun zeigt ausschließlich eine bekannte Validierungsverweigerung oder ein im
unveränderten Nativewriter sicher zurückgerollter Bestandskonflikt die bestehende
Adminseite mit ursprünglichem 400-/409-Status, festem Fehler und geöffneter Karte.

Der neue definitions-only `native_holiday_refill_refusal.php` ist **kein Writer**
und validiert keine Feiertagsquelle. Er akzeptiert nur den strikt einzelnen
nativen Fünf-Felder-Vertrag einschließlich CSRF, keine Laravel-Token-, Konto-,
LDAP-, Pausen-, Abteilungs- oder sonstigen Felder. Gemischte/unerkennbare Payloads
bleiben im ursprünglichen frühen Abbruch und erreichen keinen alten Handler.
Alle bisherigen Admin-Mutationsselektoren/-körper sowie Nativewriter und
`holiday_refill.php` bleiben unverändert. GET und andere Adminaktionen erhalten
weder einen neuen Datenread noch eine neue Eingabeauswertung.

Vor jeder neuen Fehler-Formularanzeige besitzt der Zusatz ausschließlich eine
frische eigene Lesetransaktion auf dem bisherigen idle SQLite-/Exception-PDO.
Aktueller eigener Actor, gleiche Rolle, lokale Adminquelle und vorhandener
Passwortfingerprint müssen erneut passen. Derselbe bestehende Displaypolicy-
Helfer wird verwendet; keine neue Rolle oder Quelle wird zugelassen. Verlust der
Identität/Quelle/Berechtigung bleibt 401/403; technischer Lese-/Cleanupfehler oder
geliehene Transaktion bleibt 503 ohne Draft. Keine fremde Transaktion wird
übernommen oder beendet. Diese separate Lesebestätigung macht spätere alte
Adminseitenreads nicht atomar und ersetzt weder Bootstrap/CSRF noch die alleinige
Writerentscheidung im ursprünglichen IMMEDIATE-Lock.

Die bestehende Adminseite lädt später `header.php` und damit `functions.php`.
Dessen historischer automatischer Kalenderimport darf ausgerechnet durch eine
bekannte explizite Feiertagsverweigerung nicht neu angestoßen werden. Genau der
bisherige automatische Callsite wird deshalb bei gesetztem serverlokalem
`$nativeHolidayRefillError` ausgelassen. Dieses Ergebnis stammt nur aus dem oben
geprüften bekannten Fehlerzweig, nicht aus Query/POST oder einem Konfigschalter.
Alle normalen Aufrufe (Marker fehlt/null), GET, andere Adminaktionen und der
gesamte Downloaderkörper bleiben unverändert; kein globaler Lazyimport-Umbau.
Der bekannte 400-/409-Response lädt somit nicht automatisch einen anderen
Jahrgang nach und führt darüber keinen Quellenfetch/INSERT aus. Spätere alte
Headerberechnungen lesen weiterhin den gespeicherten, nicht zertifizierten
Kalender; daraus entsteht kein neuer Soll-/Saldo-/Vollständigkeitsnachweis.

Nach bestätigtem Readcommit wird ausschließlich derselbe unveränderte Laravel-
Drafthelper verwendet: höchstens acht ASCII-Jahresziffern und höchstens 128 KiB
UTF-8-JSON ohne unerlaubte Steuerzeichen, serverseitig nicht getrimmt, gecastet
oder abgeschnitten. Unsichere Felder entfallen statt scheinbar repariert zu
werden. Die Ausgabe ist HTML-escaped; '</textarea>' oder Scripttext wird nicht
aktiv. Das sind noch keine gültigen Feiertagsdaten. Die BW-Bestätigung bleibt
bewusst **ungewählt**, CSRF wird wie bisher neu aus der aktuellen Sitzung gerendert.
Ein kurzer Hinweis verlangt erneutes Prüfen und bewusstes Bestätigen.

Draft und Fehlermeldung existieren nur in dieser Antwort: kein Session-/Flash-/
Datei-/Log-/URL-/Browserstorage, Redirect oder automatischer Submit/Retry. Der
alte Erfolgsbeleg wird weiterhin vor jedem Versuch entfernt; kein Fehler zeigt
alte Erfolgsmeldung. Ein späterer GET ist wieder leer. Bestätigter tatsächlicher
COMMIT behält unverändert seinen minimalen einmaligen 303-Beleg; 401/403/503 und
unbekannter Writer-/Commit-/Rollbackausgang werden nie zur Formularwiederholung.
Der bekannte Fehlerzweig sendet no-store. Browsernormalisierung, Reload-/Zurück-
Verhalten und vollständiger Seitenaufbau werden dadurch nicht als geprüft erklärt.

Grober PHP 8.5.12 / SQLite 3.46.1 Memory-Nachweis behält den alten zehnphasigen
Nativewrapper-/Core-/Migration-/Audit-/CSRF-/Receipt-Journey und ergänzt den
aktuellen tatsächlichen frühen Dispatch, reinen Readgate und Native-Partial.
Ein weiterer Callsite-Abschnitt mit reinem Downloader-Spy belegt die vorherige
Call-Erreichbarkeit und nun null Aufrufe bei bekanntem Fehler; fehlender/null
Marker und andere Adminfälle behalten den ursprünglichen einzelnen Aufruf.
Kein tatsächlicher Quellenrequest, Kalenderinsert oder vollständiger Headerlauf.
Bekannte 400/409, unveränderte begrenzte Eingaben, Escaping, leere/überlange/
unsichere Felder, ungeprüfte BW-Bestätigung, keine Sessionpersistenz/alte Quittung,
alle neun alten gemischten Kommandos, aktuelle User-/Admin-/LDAP-/stale Zustände,
geliehene Transaktion, generische Read-/Writerfehler und frischer GET werden
geprüft. Neue Refusal-Reads laufen query_only; der unveränderte IMMEDIATE-Writer
für den Konflikttest benötigt weiterhin seine normale Verbindung. Ein erster
Harness erwartete fälschlich 409 auf query_only; der unveränderte Writer verweigerte
stattdessen korrekt 503. Nur die Fixturephase wurde getrennt, kein Produktschutz
gelockert. Source/Schema/Audit/Zähler und echte Session-/Zeitzonenzustände bleiben
im neuen Read-/bekannten 400/409-Displayabschnitt unverändert. Keine neue Repository-Testsuite/manuelle CI.

Keine realen Konten/Credentials/Schlüssel/Dateidatenbank, vollständiger Native-
Bootstrap-/Login-/Session-/CSRF-Stack-/HTTP-/Browser-/Layout-/Konkurrenz-/Last-/
Produktiv- oder COMMIT-Verlustabnahme, keine Quellenprüfung/Importaktion, Aktivierung
oder Deployment. Die Header-/Statusentscheidung ist lokal quell-/komponentengeprüft,
kein tatsächlicher HTTP-Test. #41 bleibt offen/teilweise: kein Region-/Herkunfts-/
Vollständigkeitsmodell, keine Konfliktreparatur oder Gutschrift-Neuberechnung.
