# Lesbare Historienbezüge in Laravel

Teilumfang #52/#79 auf tatsächlichem Main
`a38942cd46d7868791ac3f4e94ee9bfe9ad87202`, Baum
`37f156997ec6b20e0fb5341d9375642839de43b6`.

Die vorhandene Historie bietet zusätzlich **Bezüge anzeigen/ausblenden**. Nur das
explizite HTML-Opt-in `relations=1` verwendet den bestehenden
`ChronoReads::historyCorrelations()`-Snapshot. Ohne Opt-in bleibt der bisherige
Ankerquery ohne zusätzliche Referenzabfragen. Ungültiger Modus oder ungültige
Filter liefern400, ohne Auditdaten zu lesen; vorhandene Chrome-/Statusreads
bleiben davon unberührt. Bezügefehler liefern festen503-Text, keinen stillen
Fallback, leeren Erfolg oder Rohfehler. Auth-/Autorisierungsfehler propagieren.

Vor Blade wird dieselbe **öffentliche geprüfte Exportprojektion** verwendet wie
für den vorhandenen korrelierten JSONexport. Keine Rohsnapshots, internen Owner,
Payloads, Antwortdaten oder freien Antrags-/Reviewtexte werden zusätzlich
weitergereicht. Ereignisarten und Empfangszeiten erhalten feste Labels bzw.
bestehende Berlinformatierung. IDs bleiben validierte Dezimalstrings.

Die vorhandenen Karten/Details/Tabellen zeigen strukturelle Sitzungs-, Ereignis-
und Antragsbezüge, auch wenn eine stornierte Buchung nicht mehr live existiert.
Referenztabellen erscheinen einmal je Seite; Karten verlinken nur ihre bereits
selektierten IDs. Aktuelle Antragszuordnung ist ausdrücklich beschriftet.
Ungeklärte, nicht anwendbare und abgeschnittene Beziehungen bleiben sichtbar;
**keine Ursachenzuordnung oder vollständige Historie wird behauptet**. Bezüge
können außerhalb der Ankerfilter liegen, niemals außerhalb des unveränderten
Own-/Admin-/aktuellen direkten Teamscopes. Keine neue Suche oder freie IDauflösung.

Filter, Cursor, Zurücksetzen und Moduswechsel bewahren den ausdrücklichen
Anzeigezustand. Die bisherigen beiden JSONexporte und ihre URL-/Formatverträge
bleiben unverändert; der neue Modus ist nur HTML. Vier Pfade: Controller, DTO,
Blade und dieses Dokument. Reader/SQL/Scopes/Helper, Routen/Auth/CSRF/Writer,
API/LDAP/Setup/Fresh, native UI und #197 bleiben unverändert.

## Grober Nachweis und Grenze

Ein query-only Memory-/echter Reader-/Controller-/Blade-Ablauf prüft Own/Admin/
direktes Team einschließlich frischer Teamumhängung, stornierte/aktuelle/
ungeklärte Bezüge, Escaping und Ausschluss privater Felder, ungültigen Modus400,
Readfehler503 ohne Fallback,100er-Grenzen/Abschneidung/Cursor sowie bytegleiche
bestehende JSONexporte. SQLite-Authorizertrace bestätigt: ohne Opt-in keine
zusätzlichen Referenztabellen, bei ungültigem Modus kein Auditquery. Syntax und
Diffhygiene werden separat geprüft; keine neue Testsuite/CI.

Erste Fixturefehler (manuelle Audit-ID kollidierte mit triggergenerierten IDs,
fehlende PHP-Konstante für SQLITE_READ, global noch nicht gebundener Memory-
SessionStore für den CSRFmetahelper) bleiben dokumentiert. Nur Fixture-IDs,
verifizierter [C-Actioncode20](https://www.sqlite.org/c3ref/c_alter_table.html) und Memorybindung wurden berichtigt; Anwendung und
Assertions wurden dafür nicht abgeschwächt, kein Schlüssel erzeugt.

Keine reale HTTP-/Session-/Login-/CSRF-/Browser-/Produktivabnahme, keine echten
Nutzer, Credentials, Geschäftsdaten, Schlüssel, Remote-/Runtimeaktivierung oder
vollständige #52/#79-Abschlussbehauptung. Finale Integration benötigt einen
unabhängigen Quellenreview am exakten Paket/zusammengesetzten Baum.
