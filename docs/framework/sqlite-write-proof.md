# FW-04: ein PDO, atomare Buchung, Audit und Ereignisantwort

Lieferstand zu [#75](https://github.com/storminator89/Zeiterfassung/issues/75):
**begrenztes Go für den synthetischen bekannten V2-Datei-PoC, kein produktiver
Schreibpfad/Cutover.** PHP **8.5.12**, Laravel **13.38.0** aus `composer.lock`,
SQLite **3.46.1**, `Pdo\Sqlite`. Die bestehende UI und sämtliche HTTP-Routen
bleiben unverändert. Keine neue Queue, kein Dual Write, Datenbank-/Hostwechsel
oder Dienst. Die noch offenen New-/vor-v2-/WAL-Gates aus #74 bleiben offen.

## Wiederverwendbarer Adapter, ausdrücklich nicht automatisch aktiviert

[`AttendanceWriter`](../../framework/app/LegacyDatabase/AttendanceWriter.php)
öffnet selbst **keinen Pfad**. Sein Aufrufer muss zuerst die explizite private
bekannte V2-Datei mit [dem Offlineverfahren](offline-v2-adoption.md) vorprüfen,
Backup und Adoption bestätigen und einen **neuen dedizierten** PDO mit dem
Standard-`SQLiteConnector` öffnen. Nicht den one-shot Adopter weiterverwenden,
nicht den Scratch-Default auf die Bestandsdatenbank umstellen und nicht den
Legacy-Bootstrap (`config.php`/`security.php`) einbinden. Der Adapter ist weder
im Container/DatabaseManager gebunden noch über eine Route/Artisan-Schreibaktion
erreichbar. Er ersetzt keine Autorisierung, CSRF-, Terminal- oder Replayprüfung
der späteren HTTP-/API-Pakete.

Der Adapter besitzt eine **private unveröffentlichte Standard-
`SQLiteConnection`** über genau dem zugelassenen PDO, ohne gemeinsam genutzte
Connection-Hooks, Eventdispatcher, Replikate oder TransactionManager. Dadurch
gibt es keinen zweiten fachlichen Writer/Commit und keinen externen Callback
zwischen Identitätsprüfung und Commit. Aufrufer dürfen denselben PDO während
einer Operation nicht anderweitig verwenden; keine Legacy-Writefunktion parallel
im selben Request aufrufen.

- `AttendanceWriter::transition()` ruft unverändert
  `attendance_transition_locked()` auf, **nicht** den Raw-`BEGIN`-Wrapper
  `attendance_transition()`. Payloadhash, gespeicherte JSON-Antwort,
  `(source,event_id)`, `expectedRecord`, Zeit-/Überlappungs- und Konfliktregeln
  bleiben ausschließlich bei der bestehenden Domäne. Keine neue ORM-Kopie.
- Der Standard-Laravel-`beginTransaction()` erzeugt mit PHP 8.5 und
  `transaction_mode=IMMEDIATE` tatsächlich `BEGIN IMMEDIATE TRANSACTION`;
  Laravel-Level und SQLite-Transaktion müssen dabei beide aktiv sein. Genau
  diese Connection übernimmt auch `commit()`/`rollBack(0)`.
- `busy_timeout=5000` muss konfiguriert **und tatsächlich wirksam** sein.
  **Ein Versuch**, kein automatisches `DB::transaction(..., attempts: n)`,
  Domain-/Deadlock-/Queryretry oder zweites Commit. Die synchrone Domäne arbeitet
  direkt auf demselben PDO; es werden keine Jobs/externen Seiteneffekte gestartet.
- **Verschachtelung/Savepoints sind für diese Operation nicht freigegeben.**
  Ein aktiver äußerer Laravel- oder Raw-PDO-Block wird vor Mutation abgelehnt.
  Damit kann ein vermeintlicher Buchungserfolg nicht erst später mit einer
  unbekannten äußeren Transaktion verschwinden. Kein alternativer Lockmodus.
- Auf genau jedem übergebenen neuen `Pdo\Sqlite` registriert `AuditFunctions`
  die PHP-8.5-UDFs vor dem ersten Write. Audittrigger, Projektion, Original und
  Ereignisantwort verwenden denselben Handle und dieselbe Transaktion. Kein
  separates Audit-Insert nach Commit. Fehlender Kontext schlägt geschlossen fehl.
- `AuditContext::run()` stellt Akteur/Grund operationlokal bereit und räumt sie
  auch bei Fehlern auf. Die Globals, die die unveränderte Legacy-Domäne setzt,
  werden in `finally` exakt wiederhergestellt, einschließlich vorhandener
  `null`-Werte. Für jeden Request/Job neuen scoped Kontext/Adapter verwenden;
  keinen Adapter über Scopewechsel hinweg aufbewahren.
- Reconnect ist verboten, ebenso unerwarteter PDO-Wechsel oder unterbrochene
  Transaktion. Bei erkanntem Wechsel wird ausschließlich der **ursprüngliche**
  noch aktive PDO zurückgerollt, niemals der Ersatz committed; der Adapter ist
  danach unbrauchbar. Eine neue Operation benötigt erneute Admission und UDFs.
- Ein fehlgeschlagenes SQLite-`COMMIT` kann die Transaktion noch halten. Mit
  explizitem Laravel-`beginTransaction()`/`commit()` bleibt der Level bei diesem
  Fehler erhalten; der Catch rollt ihn explizit über Laravel zurück. Es gibt
  keinen konkurrierenden `transaction()`-Exceptionhandler/Doppelrollback.
  Schlägt auch Cleanup fehl, bleibt die ursprüngliche Exception erhalten und
  der Adapter wird gesperrt: PDO verwerfen, nicht weiterarbeiten oder Erfolg
  erfinden. Bei ungewisser Antwort nur dieselbe Event-ID/Nutzlast erneut abfragen.

Die begrenzte Admission erlaubt hier ausschließlich bestehende private
Rollback-Journal-Dateien, keine Attachments, `query_only`, leeren/Memorypfade
oder WAL. Journal-, Foreign-Key- und Synchronous-Einstellungen werden **nicht**
automatisch umgestellt. Im geprüften synthetischen Bestand: `delete`, FK `0`
wie im bisherigen Connector, Synchronous `2`; keine nachträglichen Constraints
oder Lockerungen, um einen Check grün zu machen. Für echte Daten muss die
Betriebsfreigabe die beobachteten Einstellungen gesondert bestätigen.

## Ein grober, wiederholbarer Integrationsbeweis

```sh
# Aus dem Repository, PHP8.5 mit Pflichtmodulen und installiertem Composer-Lock:
php framework/tools/prove-sqlite-writes.php
```

Dieses **CLI-only** Werkzeug liegt im verweigerten `framework/`-Quellbaum und
akzeptiert keinen Datenbankpfad. Es erzeugt aus dem vorhandenen Memory-
Fixturebuilder neue synthetische V2-Daten, persistiert sie per `VACUUM INTO`
und erstellt kalte getrennte Original-/Backup-/Zielkopien unter einem frischen
privaten `/tmp/chrono-sqlite-proof-*`-Verzeichnis (`0700`, Dateien `0600`).
Keine produktiven Daten, Zugangsdaten, Schlüssel oder bestehende Fixture-Dateien
werden gelesen. Das Verzeichnis wird im JSON nur zur lokalen Nachprüfbarkeit
genannt; Datenbankdateien gehören nicht in Git/GitHub.

**Grob bestanden am 27.10.2026**, einschließlich tatsächlicher File-Adoption:

1. Zwei getrennte PHP-Prozesse mit eigenen Standard-Connector-PDOs warten auf
   denselben echten SQLite-Writerlock und starten dieselbe Person: genau ein
   Erfolg, ein vorhandener `409`-Fachkonflikt, eine offene Session, ein Event
   und beide zugehörigen Auditzeilen. Keine verlorene Projektion/Auditzeile.
2. Verlorene Erfolgsantwort: gleiche ID/Payload liefert identische Antwort und
   unveränderte gespeicherte JSON-Bytes, andere Payload ergibt `409`, ohne
   Mutation/zusätzliche Journale. Ausgeschöpfter echter BEGIN-Busytimeout führt
   vor Domainarbeit zum Fehler; Wiederholung mit derselben ID persistiert einmal.
3. Eingespritzter Auditfehler und Fehler nach Event-Insert rollen sämtliche
   Mutation/Event/Auditzeilen **und Sequenzen** vollständig zurück. Ein echter
   paralleler Reader blockiert den Commit bis zum Busytimeout: vollständiger
   Rollback/Lockfreigabe, danach dieselbe Event-ID erfolgreich wiederholbar.
4. Pause/Resume/Ende persistieren. Geschlossene Originalintervalle, append-only
   Events/Audit und eine finale synthetische Entscheidung bleiben unveränderlich.
5. Neue tatsächliche PDO-/Job-Scope-Verbindung hat funktionierende UDFs, keinen
   alten Auditkontext oder globale Reste. Fehlender Kontext und Verschachtelung
   werden abgewiesen. Ein ausschließlich per Reflection im Beweis injizierter
   gefährlicher PDO-Wechsel während der Mutation lässt weder auf Original noch
   Ersatz Teilzustand zurück und sperrt den Adapter; dafür gibt es keinen
   produktiven Testhook.
6. Nach Schließen/Neuöffnen: `quick_check=ok`, keine offene Session, ein
   Adoptionseintrag; Original und Backup byteidentisch unverändert, keine
   SQLite-Sidecars übrig. Ergebnis ausschließlich aggregiert, keine Zeilenwerte.

Notwendige PHP-Syntaxprüfung und `git diff --check` bestanden. Keine Mikrotests,
Browser-/UI-Testausweitung oder manuell gestartete GitHub-CI. Dies ist kein
vollständiger Last-/Betriebs-/Apache- oder produktiver Wiederherstellungsbeweis.

## Go/No-Go und verbleibende Grenzen

**Go:** dieser enge opt-in Adaptervertrag und der synthetische bekannte
V2-Rollback-Journal-PoC, als Gate für spätere reviewte Schreibpfadpakete.

**No-Go für produktive Aktivierung:** noch keine verbindliche Request-/Job-
Writerzuordnung, Route/Auth-/Terminalintegration, echte private Datenadmission,
vollständige Restore-/Betriebsabnahme oder explizite Cutoverfreigabe. #74 bleibt
für Neuinstallation, vor-v2 und WAL unvollständig. Kein automatischer
Datenbankwechsel als Ausweg; SQLite-Busy bleibt ein möglicher Betriebsfehler
mit sicherer Same-ID-Wiederholung, keine garantierte Verfügbarkeit. Bestehende
Fachlücken aus #35/#36/#57 und Adminaudit-Abdeckung werden nicht stillschweigend
behoben. Die bestehende UI muss in späteren Paketen weiterverwendet werden.

Primärquellen: gelockter Standard-
[`SQLiteConnection`](https://github.com/laravel/framework/blob/v13.38.0/src/Illuminate/Database/SQLiteConnection.php),
[`ManagesTransactions`](https://github.com/laravel/framework/blob/v13.38.0/src/Illuminate/Database/Concerns/ManagesTransactions.php),
[Laravel-Transaktionen](https://laravel.com/docs/13.x/database#database-transactions),
[SQLite-Transaktionsverhalten](https://www.sqlite.org/lang_transaction.html).
