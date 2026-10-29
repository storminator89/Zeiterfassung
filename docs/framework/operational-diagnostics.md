# #66 — begrenzte private Betriebsdiagnose

`php artisan chrono:diagnose` ergänzt die vorhandenen datensparsamen
[HTTP-Fehler-IDs](request-correlation.md), ohne HTTP-Endpunkt, zusätzlichen Dienst,
Scheduler, automatische Alarmzustellung oder veränderte Runtimezulassung.
Laravel entdeckt den neuen Consolebefehl im bestehenden Commands-Verzeichnis.

## Ein beobachteter Zustand, kein Freigabeattest

Der Befehl liefert genau einen JSONbericht mit neuer zufälliger
`diagnostic_id` (32 kleine Hexzeichen), Zeitpunkt, festen Fehlercodes und
Messwerten. Keine Eingabe wird als ID übernommen. Keine URLs, Namen, Benutzer- oder
Geräte-IDs, privaten Pfade, Exceptiontexte/Stacks, SQL, Bindings, PINs, Hashes,
Schlüssel, Tokens oder Dateiinhalte werden ausgegeben oder zusätzlich geloggt.
Warnungen innerhalb der synchronen Diagnose werden ebenfalls generisch behandelt;
der vorherige PHP-Errorhandler wird wiederhergestellt. Frühere Bootstrapfehler
können den Befehl verhindern und besitzen deshalb keinen erfundenen Diagnosebericht.

- Exit **0 / healthy**: ausschließlich die ausgewählten Beobachtungen sind innerhalb
  der angegebenen Grenzen. `scope=read_only_runtime_observations`,
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

Quellbasis: Main `1e8e50a4c95f1eb4c4397943bb371404a0cdce28`, 29.10.2026.
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
