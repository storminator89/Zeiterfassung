# #62 — eigenes lokales Passwort im Native-Fallback

Begrenzte Ergänzung auf Main
`8053587c77e568fa24c32a81ab3b140ed17eedc6`, Tree
`0bc18204626496f8d51dd55319b18fb7ce8d6a61`. Die bereits vorhandene
Laravel-Passwortfunktion und sämtliche Laravel-Dateien bleiben unverändert.
Keine Recovery-, Einladungs-, Fremdkonto-, LDAP- oder Geräte-PIN-Funktion.

## Identität, Quote und Änderung

Das getrennte Formular in `settings.php` akzeptiert nur CSRF-Token, feste Aktion,
aktuelles Passwort, neues Passwort und Bestätigung. ID, Rolle und Fingerprint
kommen ausschließlich aus der bestehenden serverseitigen Sitzung. Das vorhandene
Security-Bootstrap prüft CSRF und die aktuelle Session vor dem neuen Handler.
Gemischte Präferenz-/Passwortnutzlasten werden zurückgewiesen; eine fehlgeschlagene
Passwortoperation speichert keine Sprache, kein Design und keinen Übertrag.

Die neue Funktion verlangt ein eigenes lokales Konto mit bestehender Rolle
`user`, `supervisor` oder `admin`. Vor teurer Passwortprüfung/-erzeugung besitzt
sie eine kurze `BEGIN IMMEDIATE`-Transaktion: frische ID, Rolle, Auth-Quelle und
Passwortfingerprint prüfen, beide Versuchsbudgets gemeinsam lesen/buchen,
Persistenz zurücklesen und Autorität erneut prüfen. Die bestehende
`login_attempts`-Tabelle erhält getrennte, gehashte Namensräume: höchstens fünf
Versuche pro Konto und zwanzig pro tatsächlicher Peer-IP in 600 Sekunden.
Loginzähler und fremde Buckets werden nicht gelöscht oder verändert. Ein
blockiertes Teilbudget erzeugt keine halbe Buchung im anderen; Fehlversuche
bleiben nach COMMIT bestehen. Fehlende/fehlerhafte Persistenz oder zukünftige
Zeitstempel öffnen keinen Hashingpfad. Es gibt keine neue Datei-/Sessionquote.

Passwörter werden nicht getrimmt, normalisiert oder gekürzt. Das neue Passwort
muss 12–72 Bytes umfassen, ohne Steuerzeichen, mit identischer Bestätigung und
anders als das bisherige wirksame Passwort; `defaultpassword` bleibt verboten.
Prüfung und der bestehende native PHP8.5-Bcrypt-Standard laufen außerhalb der
Writerlocks. Eine zweite kurze `BEGIN IMMEDIATE`-Transaktion prüft dieselbe
aktuelle Autorität erneut und ändert per Compare-and-swap ausschließlich das
Passwort derselben ID/Quelle/Rolle und desselben bisherigen Hashes. Kontoquelle,
Rolle, Stammdaten, Arbeitszeiten, Geräte und Schlüssel werden nicht geändert.

Bestehende V2/V3-Usertrigger schreiben atomar ihren bisherigen redigierten
Auditstand mit festem `web:<ID>`-Akteur und festem Passwortänderungsgrund.
Passwörter, Bestätigungen, Hashes und Fingerprints gelangen nicht in Audit,
Quota, Flashdaten, Meldungen oder HTML. Vorherige Auditglobals werden auch nach
Fehlern wiederhergestellt. Eine fremde aktive Caller-Transaktion wird weder
übernommen noch zurückgerollt.

## Abmeldung und nicht bestätigter Ausgang

Nur nach bestätigtem COMMIT folgt die bestehende vollständige native
Abmeldesemantik (Sessioninhalt, Cookie und Session), dann 303 zu `login.php`.
Der neue Fingerprint wird nicht in die alte Session übernommen. Andere alte
Web-Sitzungen/API-Tokens scheitern bei ihrer nächsten bestehenden Prüfung;
laufende bereits autorisierte Requests werden dadurch nicht rückwirkend
abgebrochen. Geräte-PINs und deren getrennte Grants bleiben unverändert.

Bei stale Identität oder unklarem Speicher-/COMMITausgang wird die alte Session
verworfen und eine feste Meldung zurückgegeben, ohne automatischen Retry oder
Erfolgsmodal. Ein bestätigter Passwortwechsel mit nicht vollständig bestätigter
Abmeldung bekommt eine eigene feste 503-Meldung. Rohfehler werden weder
angezeigt noch neu geloggt. Bei sicheren Formular-/Passwort-/Quotenfehlern bleiben
die Passwortfelder im ausgelieferten HTML leer. Anzeige in de/en/zh.

## Explizite Operationsgrenze, keine Native-V5-Abnahme

Unter jedem Authority-Writerlock wird nur der bestehende Native-Ledger `[1,2]`
oder `[1,2,3]` akzeptiert. Andere Ledger, insbesondere V4/V5, verweigern diese
Passwortoperation mit festem503 und Abmeldung; eine Quotenphase rollt bei
zwischenzeitlichem Ledger-/Autoritätswechsel vollständig zurück.

Dieser begrenzte Ledgercheck ist **kein vollständiger Datenbankprofil-, Schema-,
Eigentums- oder Cutovernachweis**. Das bestehende Native-Bootstrap läuft vorher.
Der separate breite Native-/SQLite-/immutable-MAIN-Guard bleibt
**HOLD_COMPATIBILITY**; kein URI-/Plainpath-Fallback oder Runtimepfad wird hier
geöffnet. Keine V5-Admission, Aktivierung, Migration, Host-/Sicherheitsänderung
oder Deployment. #30/#62 und die vollständige Native-V5-Grenze bleiben offen.

## Grober synthetischer Nachweis

PHP8.5.12: 29 Fälle auf anonymen kanonischen V2/V3-`:memory:`-Fixtures mit nur
erfundenen Passwörtern. Eigene lokale Rollen, CAS/Fingerprintwechsel, fremde
Konten/Stammdaten unverändert, Audit ohne Secrets, getrennte persistente Budgets,
atomare Verweigerung, Ablauf der Quote, Mixed-Payload, LDAP/stale Identität,
fremde Transaktion sowie ignorierte/manipulierte Counter und Autoritäts-/
Ledgerwechsel während Quotenbuchung wurden geprüft. Schreibfehler bewahren
Geschäftsdaten und bereits verbuchte Quote. V1/V4/V5 verweigert.

Fünf reine Formularzustände in de/en/zh prüfen Darstellung, lokale Verfügbarkeit,
fehlenden Nutzer, Escaping, getrenntes CSRF-/Aktionsfeld, Autocomplete und fehlende
Passwort-`value`-Attribute. Sprachdefinitionen emittieren exakt null Bytes;
ein bisher überschüssiges Schluss-Newline in zh wird entfernt, damit Cookie/303
nicht von Outputbuffering abhängen. Ein inverser Quellenvergleich bewahrt die vorhandene
Präferenz-/Admin-/Backup-/JavaScriptlogik; lediglich der anfängliche geladene
SELECT-Cursor wird vor Writerphasen ausdrücklich geschlossen. PHP-Lint und
Whitespaceprüfung bestehen. Unabhängiges Source-Review gehört zum Paket.

Keine echten Credentials, Datei-/Betriebsdatenbank, Appbootstrap, tatsächliche
Session, HTTP/Server, Browser oder JavaScriptausführung. Echte Logout-/Cookie-/
CSRF-/Login-/JWT-End-to-end-Abnahme, Prozesskonkurrenz, verlorene COMMITantworten,
LDAP/Hardware und produktiver Betrieb sind **nicht** abgenommen. Kein neuer
CI-Lauf oder Remote-Schreibzugriff für diese lokalen Checks.
