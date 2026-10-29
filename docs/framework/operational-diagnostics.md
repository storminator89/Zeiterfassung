# #66 — begrenzte private Betriebsdiagnose

`php artisan chrono:diagnose` ergänzt die vorhandenen datensparsamen
[HTTP-Fehler-IDs](request-correlation.md), ohne HTTP-Endpunkt, zusätzlichen Dienst,
Scheduler, automatische Alarmzustellung oder veränderte Runtimezulassung.
Laravel entdeckt den neuen Consolebefehl im bestehenden Commands-Verzeichnis.

## Ein beobachteter Zustand, kein Freigabeattest

Der Befehl liefert genau einen JSONbericht (`schema_version=2`) mit neuer
zufälliger `diagnostic_id` (32 kleine Hexzeichen), sofern die Zufallsquelle
verfügbar ist, Zeitpunkt, festen Fehlercodes und Messwerten. Bei Ausfall bleibt
die ID ausdrücklich `null`; `diagnostic_id_unavailable` wird ergänzt. Keine
Eingabe wird als ID übernommen. Keine URLs, Namen, Benutzer- oder
Geräte-IDs, privaten Pfade, Exceptiontexte/Stacks, SQL, Bindings, PINs, Hashes,
Schlüssel, Tokens oder Dateiinhalte werden ausgegeben oder zusätzlich geloggt.
Warnungen innerhalb der synchronen Diagnose werden ebenfalls generisch behandelt;
der vorherige PHP-Errorhandler wird wiederhergestellt. Frühere Bootstrapfehler
können den Befehl verhindern und besitzen deshalb keinen erfundenen Diagnosebericht.

- Exit **0 / healthy**: ausschließlich die ausgewählten Beobachtungen sind innerhalb
  der angegebenen Grenzen und eine neue Diagnose-ID ist verfügbar. `scope=read_only_runtime_observations`,
  `readiness=not_assessed` bleiben ausdrücklich im Bericht.
- Exit **1 / degraded**: eine Messung ist ungünstig, unvollständig oder verweigert.
- Exit **2 / invalid**: ungültige Optionen bzw. kein echtes boolesches Read-Flag.
- Exit **3 / disabled**: Runtime-Lesen ist geschlossen. Kein Runtimeobjekt,
  Datenbankmanager, PDO, privater Pfad, Writer oder Provisioner wird aufgelöst.

`/up`, Container-running und auch Exit0 bescheinigen **nicht** Login, Schreibbarkeit,
API/JWT, LDAP, Hardware, TLS, Logzustellung, Wiederherstellbarkeit oder Cutover.
Diese Bereiche stehen als `unassessed` im Bericht. Ein Fehler bedeutet insbesondere
nicht, dass ein vorangegangener Commit ausgeblieben ist: Pending-/Event-ID-Vertrag
bewahren, keine neue Buchung/automatische Wiederholung oder Datenreparatur ableiten.

## Messungen im bestehenden zugelassenen Profil

Mit geschlossenen Defaults bleibt alles geschlossen. Für die tatsächliche Diagnose
wird ausschließlich die schon zugelassene benannte `chrono`-Readkonfiguration über
einen **frischen** `RuntimeDatabase::readConnection` verwendet. Originale Datei-,
Schema-, Ledger-, Policy- und V5-Cohortprüfungen bleiben unverändert. Eigener
query-only-Handle und ein einmaliger Lesesnapshot; kein Writer, Keyzugriff, DDL,
Migration, Authentifizierungsversuch, Reset, Retry oder Berechtigungswechsel.

```sh
php artisan chrono:diagnose --backup=/ABSOLUTER/PRIVATER/AKTUELLER/BACKUPDATEI
# Nur wenn aktive Terminals im Betrieb erwartet werden:
php artisan chrono:diagnose --backup=/ABSOLUTER/PRIVATER/AKTUELLER/BACKUPDATEI --terminal
```

- **Speicher:** freie Bytes auf dem bereits zugelassenen DB-Datenträger. Standard
  `--min-free-bytes=134217728` (128MiB), ein frei wählbarer Beobachtungs-/Alarmwert,
  kein Sicherheitsgate. Unbekannter Wert ist nicht null oder gesund.
- **Sicherungsalter:** ohne `--backup` degradiert. Nur eine explizite vorhandene
  private, reguläre Single-Link-Datei außerhalb der Webroots wird per Stat geprüft;
  Inhalt wird niemals gelesen/gehasht. Leere, wechselnde, zukünftige, unerreichbare
  oder nicht zugelassene Metadaten bleiben unbekannt. Die zugelassene Live-DB selbst
  wird nicht als Backup akzeptiert. Standard
  `--max-backup-age=86400` (24h), nur kanonische nichtnegative Ganzzahlen, Altergrenze
  mindestens1. **mtime ist nur eine Altersheuristik**, kein Beweis für Vollständigkeit,
  Konsistenz oder aktuellen Datenstand. `restorability=not_verified` bleibt immer.
  Aktuelle vollständige kalte Sicherung samt benötigten privaten Configs/Schlüsseln
  und Restore aus #65 sind separat erforderlich; keine Live-Dateikopie oder alte
  Empty-Sicherung nach späteren Buchungen verwenden.
- **Heartbeat:** nur mit `--terminal`, nur `last_seen` freigegebener Geräte; keine
  Credential-/Pairingdaten. Vorhandene reine `terminal_contact_status`-Regel mit90s
  wird wiederverwendet. Aggregierte recent/stale/missing/invalid-Zählung, maximal1000
  Geräte; darüber ausdrücklich incomplete/degradiert, keine vollständige Summe.
  Keine aktiven Geräte bei expliziter Auswahl ist ebenfalls degradiert. Kontakt ist
  weder Gerätefreigabe noch Funktions-/Hardwarebeweis. Ohne Auswahl keine Gerätequery.

## Kurzes Runbook und Alarmierung ohne neuen Dienst

1. Diagnose nur im bestehenden privaten Operator-/Containerkontext ausführen und
   den **redigierten Bericht samt Exitcode** privat aufbewahren. Eine frische ID
   bezeichnet einen neuen Prüflauf, nicht denselben HTTPrequest oder ein Konto.
2. `runtime_observation_refused`: keine anderen Flags/Pfade/Writer ausprobieren.
   Bestehende Admission, Mountidentität, Profil und private Betriebslogs prüfen;
   ein Schema-/Cohortfehler rechtfertigt keine Manifestkorrektur oder Neuanlage.
3. `storage_low/unknown`: freien Platz im tatsächlich gewählten Datenträger prüfen.
   Keine Nutzer-, Journal-, Pending-, Schlüssel- oder Sicherungsdateien blind löschen.
4. `backup_*`: geplante aktuelle konsistente Sicherung und verantwortliche Person
   prüfen; Zeitstempel allein ist kein Restorepass. `terminal_*`: vorhandenen
   Agent-/Netz-/Heartbeatpfad privat prüfen, niemals automatisch neu koppeln oder
   Grants/Schlüssel erneuern. Beim begrenzten Scan keine fehlenden Geräte erfinden.
5. Bestehendes Laravel-Logging bleibt stderr. Für einen bereits benutzten
   Docker-Container nur die drei benötigten Felder inspizieren, z.B.
   `docker inspect --format 'driver={{.HostConfig.LogConfig.Type}} max-size={{index .HostConfig.LogConfig.Config "max-size"}} max-file={{index .HostConfig.LogConfig.Config "max-file"}}' BESTEHENDER_CONTAINER`.
   **Keinen vollständigen LogConfig-/Containerdump** verwenden: andere Logdriver-
   Optionen können Zugangsdaten oder private Ziele enthalten.
   Driver, `max-size`/`max-file` bzw. die tatsächlich zuständige journald-/Hostrotation
   und verfügbare Bytes dokumentieren. Fehlende Angaben sind **unbestätigt**, keine
   automatische Rotation. Kein Logdump, Secret-/Personendatenexport oder `phpinfo()`.
   Bei Nicht-Dockerbetrieb den vorhandenen stderr-Empfänger und dessen reale
   Größen-/Aufbewahrungsgrenzen prüfen. Das Tool attestiert den externen Sink nicht.
6. Für einen vorhandenen lokalen Monitor Exit1/2/3 und wiederholte eindeutige
   HTTP-5xx-IDs aus einem vereinbarten Zeitfenster melden; identische HTTP-ID nicht
   als Exception und Antwort doppelt zählen. Verantwortliche, Zeitfenster,
   Schwellen, Aufbewahrung und Empfänger vor Betriebsaktivierung festlegen.
   Kein neuer Maildienst, Cronjob, Agent, Queue/Redis oder automatischer Versand
   wird installiert. Vor Host-/Rotations-/Alarmkonfigänderungen gesonderte Freigabe.

## Begrenzung der Quellenabnahme

Quellbasis: Main `54c409af37c11c12cfd6ee3f9f4bd45ea081d608`, 29.10.2026.
Die vorhandene HTTPkorrelation wird nicht ersetzt. PHP8.5.12 / gelocktes
Laravel13.38.0: Syntax und acht begrenzte CommandTester-Komponentenfälle geprüft:
geschlossen/ungültig ohne Runtimeauflösung, tatsächliche zugelassene read-only
V4/V5-Runtime, niedriger Speicher/veralteter Zeitstempel/keine aktiven Geräte
sowie ausschließlich im Speicher falsche Inodezulassung generisch verweigert.
Originaldateien/Modi/Identitäten unverändert, eigene Fixtures entfernt. Der reine
Test-Zeitstempelmarker war ausdrücklich keine Sicherung: selbst im grünen Fall
bleiben `restore_integrity` und `restorability` unbestätigt.

Die Komponentenquelle wurde per Prepend mit aktuellen Main-Klassen und gelocktem
Vendor zusammengesetzt, nicht durch einen vollständigen Artisan-/Appbootstrap
oder Standard-Composer-Autoload abgenommen. Keine tatsächliche Host-/Docker-/TLS-/
Produktions-, Alarmzustellungs-, Hardware- oder Disaster-Recoveryabnahme. Keine
Konten, Passwörter/Hashes, Schlüssel oder Tokens erzeugt; keine Runtime-/Hostflags
geändert. Echte Betriebsgrenzen/Empfänger/Logrotation bleiben Operatorentscheidung.


## #66: Diagnosebericht bei nicht verfügbarer Zufalls-ID

Additive Quellbasis: Main `703556fffb01bae4b17424c6e6b6a744cade021a`, Baum
`996fa9e72e9409f81fc46b80f8591abe00ff6557`.
Bisher lag `random_bytes` vor der lokalen Fehlerbehandlung. Ein Fehler der
Zufallsquelle verhinderte dadurch auch für geschlossene Defaults oder ungültige
Optionen den gesamten redigierten Bericht. Jetzt erfolgt genau ein Versuch
innerhalb derselben lokalen Fehlergrenze; fehlende Entropie wird weder mit ihrem
Exceptiontext ausgegeben noch geloggt. Keine schwächere Zufallsquelle, Uhrzeit,
frühere ID, Benutzer-/Requesteingabe oder automatischer zweiter Versuch ersetzt
sie. Die JSONversion steigt auf2, weil `diagnostic_id` nun ausdrücklich nullable
ist; externe Leser müssen diesen fehlenden Korrelationswert beachten.

Die eigentliche Diagnose läuft weiterhin nur durch die bisherigen Options-/
Readflag-/Admissiongrenzen, höchstens einmal und ausschließlich lesend.
`diagnostic_id_unavailable` steht zusätzlich hinter den bisherigen Fehlercodes.
Geschlossen3/disabled, ungültig2/invalid und bestehende ungünstige oder verweigerte
Beobachtungen1/degraded behalten ihren Zustand/Exitcode. Eine sonst ausschließlich
günstige Messung wird bei fehlender ID **1/degraded**, nie unkorrelierbar gesund.
Messwerte, `readiness=not_assessed`, übrige Grenzen und der wiederhergestellte
vorherige PHP-Errorhandler bleiben erhalten. Frühe Appbootstrapfehler oder ein
Ausfall des Ausgabekanals werden dadurch nicht pauschal abgedeckt.

Bei `diagnostic_id_unavailable` den vorhandenen privaten Operator-/PHPkontext
auf Verfügbarkeit der Systemzufallsquelle prüfen. Kein erneutes Buchen,
Reprovisionieren, schwacher Seed, geändertes Admissionmanifest oder neuer
Logtransport wird daraus abgeleitet. Die fehlende Support-ID sagt insbesondere
nichts über den Ausgang einer früheren Schreiboperation aus. Das Paket ändert
keine System-/Host-/Alarm-/Sicherheitskonfiguration und behebt keine tatsächliche
Betriebsursache der Zufallsquelle.

**Grober Komponentennachweis:** PHP8.5.12 / Laravel13.38.0, tatsächlicher aktueller
Command/Symfony-CommandTester und originaler zugelassener query-only V5-Reader
mit vollständig leerer erfundener privater Fixture. Ein nur im Harness injizierter
Zufallsfehler reproduziert bei der alten Klasse in allen geprüften Zweigen den
Abbruch vor Bericht und Runtimeauflösung. Die neue Klasse liefert genau einen
JSONbericht mit null-ID/festem Zusatzcode: geschlossen/ungültig ohne Runtime,
günstige Messung degradiert, ungünstige Messung und verweigerte Runtime behalten
ihre ursprünglichen Ergebnisse. Kein Writer wird aufgelöst; normale Berichte
sind bis auf neue Schema-ID und volatile ID/Zeit gleich zur ausgeführten alten
Klasse. Originaldateibytes/Identitäten/Modi, vorheriger Errorhandler, Session und
Zeitzone bleiben erhalten; eigene leere Fixture danach entfernt. Keine
Entropie-Exception, privaten Pfade oder andere Canarywerte im Bericht. Syntax
und vollständige Quellinverse werden gesondert geprüft.

Keine komplette Artisan-/Appbootstrap-/Standardautoload-, Host-/Docker-/TLS-/
Browser-/Logsink-/Alarm-/Produktiv-/Recoveryabnahme oder reale Entropiestörung
behauptet. Keine Accounts, Credentials, Schlüssel oder Tokens erzeugt; keine
Betriebsdaten oder Konfiguration verändert. Keine neue Testsuite oder manuell
gestartete CI. Die frühere HTTP-/Terminalkorrelation bleibt unverändert.
**#66 bleibt teilweise und offen** für tatsächliche Logzustellung/Rotation,
Alarmierungsregeln und Betriebsabnahme.
