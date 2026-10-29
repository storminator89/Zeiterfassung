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
Autofill, Submit, Replay oder Rohdatenentwurf.

POST benutzt das unveränderte native `csrf_field()` und die bestehende
`security.php`-/`config.php`-Authentifizierung vor dem neuen Zweig. Die feste
Aktion wird **vor allen** bisherigen Adminmutationen abgefangen. Neben CSRF
sind ausschließlich `refill_holidays=1`, `holiday_year`, `confirm_bw_source=BW`
und `holiday_json` erlaubt. Gemischte Konto-/LDAP-/Pausen-/Abteilungs-/Audit-/
Quellenfelder verweigern die gesamte neue Aktion, ohne einen alten Zweig zu
betreten. Bestehende Adminformulare und deren Handler bleiben bytegenau erhalten.

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
vor der Verarbeitung; POST-Antworten zeigen keine alte Erfolgsmeldung. Validierungs-/Konflikt-/Rechtefehler verwenden den
bestehenden festen nativen Fehlerweg. Unbekannter COMMIT-/ROLLBACK-/PDO-Ausgang
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
als ungeprüft. Der bekannte Lazyimport in `functions.php` bleibt unverändert;
diese neue Aktion führt ihn nicht aus und erklärt ihn nicht für vollständig gelöst.
