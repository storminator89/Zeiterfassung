# Aufnahmewege: isolierter V4-Policykern für eine frische Installation

**Nur Voraussetzung, noch keine fertige Admin-Auswahl.** Basis ist Main
`b5558c36b64c9491ddec26800a0588952d32316a`. Dieses Paket ergänzt ausschließlich
neue Definitionsdateien und eine anonyme Memory-Quellfixture. Kein vorhandener
Controller, Writer, Guard, Provider, Runtime-/Schema-Admission, Installationsweg,
UI-, Konfigurations- oder LDAPpfad wird geändert oder aktiviert.

Der aktuelle Bestand hat keine gemeinsame persistente Aufnahmerichtlinie:
`chrono_registration.enabled` ist nur ein vertrauenswürdiges Opt-in, manuelle
Anlage prüft den lokalen Admin, bestehender LDAPlogin ist separat. Der neue Kern
wird von diesen Pfaden **noch nicht aufgerufen und beschränkt sie nicht**.
V2/V3-Zulassung lehnt die zusätzliche Tabelle/Version weiter ausdrücklich ab.
Eine spätere Integration muss alle Aufnahmegates gemeinsam anschließen, bevor
wirksame Admin-Schalter angezeigt oder die neue Installation zugelassen werden.

## Dauerhafter Datenvertrag, nicht Session-/Config-Checkboxen

`user_intake_policy` enthält genau die feste Zeile `id=1`: drei unabhängige
Integer-Booleans `manual_enabled`, `ldap_sync_enabled`, `registration_enabled`,
positive `revision`, serverseitiges `updated_at` und optionale historische
Admin-ID `updated_by`. Der frische Zustand ist manuell an, LDAP-Sync und
Registrierung aus, Revision1. Jede Kombination einschließlich alle aus ist
zulässig. Bestehende Konten/Anmeldungen werden dadurch weder gesperrt noch
konvertiert; Erst-Admin-Einrichtung gehört ausdrücklich zu keinem dieser drei
Aufnahmewege. Auch bei allen Wegen aus muss der vorhandene lokale Admin die
Policy wieder ändern und die getrennte Erst-Einrichtung funktionieren können.

Die Tabelle hat feste Werte-/Singleton-Checks. Jede wirkliche Änderung verlangt
Revision+1 und einen lokalen Admin; ein CAS-Update im bereits gehaltenen
Writerlock verhindert das Überschreiben eines veralteten Formularstands.
Unverändertes Speichern erzeugt nach erfolgreicher Revisionsprüfung keine
zusätzliche Revision. Löschen der Policyzeile ist gesperrt.

Ein fester Trigger schreibt Vorher/Nachher der drei Flags, Revision und
Änderungsmetadaten in das vorhandene append-only `audit_log`; keine Namen,
Passwörter, PINs, Tokens, Verzeichnis-/CA-/Keywerte oder freie Requestfelder.
`user_id=0` macht diese globale Richtlinie im bestehenden Verlauf nur für Admins
sichtbar. Vertrauenswürdiger Auditakteur/-grund kommen wie bisher aus dem
vorhandenen Writer-/AuditContext, nicht aus dem Formular. Der Initialzustand ist
Teil der expliziten Schemaerstellung; er wird nicht als Benutzeränderung oder
rekonstruierte Vorgeschichte ausgegeben.

## Feste interne Schnittstelle

Namespace `App\Accounts`:

- `IntakeMethod::Manual`, `::LdapSync`, `::Registration` haben die Werte
  `manual`, `ldap_sync`, `registration`. Keine beliebigen SQL-/Policy-Schlüssel.
- `IntakePolicyChange::fromForm()` nimmt genau `revision` und die drei
  `*_enabled`-Felder an: kanonischer positiver Revisionsstring innerhalb
  `PHP_INT_MAX`, Flags ausschließlich rohe Strings `'0'` oder `'1'`.
  CSRF-/Aktionsfelder entfernt später nur der konkrete Transport; keine Akteur-,
  Rollen-, Ziel-, Quellen-, Audit- oder Geheimnisfelder gehören zum Befehl.
- `IntakePolicyStore::read(PDO): IntakePolicy` braucht einen bereits aktiven
  zugelassenen Lesesnapshot. Fehlende/abweichende Versionen, fehlende/mehrere
  Zeilen oder ungültige Werte sind Fehler; kein erlaubender Default/Fallback.
  Der interne DTO ist keine öffentliche JSONantwort oder Authentscheidung;
  insbesondere Änderungsakteur/-zeit nicht an anonyme Formulare serialisieren.
- `requireAllowedLocked(PDO, IntakeMethod, ?int $expectedRevision)` prüft die
  aktuelle Policy erneut. Bei expliziter abweichender Revision wird auch ein
  weiterhin eingeschalteter Weg verweigert. Deaktiviert heißt verweigert.
- `applyLocked(PDO, int $trustedAdminId, IntakePolicyChange)` lädt unter der
  vorhandenen Schreibsperre aktuelle lokale Rolle/Quelle neu und prüft die
  erwartete Revision. Die Akteur-ID darf ausschließlich der bestehende geprüfte
  Service liefern; es gibt keine frei vergebbare Adminrolle oder SQL-Callback.

Der Kern öffnet keinen PDO/Pfad, kennt keinen Guard, beginnt/beendet/wiederholt
keine Transaktion, sendet nichts und provisioniert niemanden. Alle Locked-
Aufrufe gehören in den einzigen bestehenden zugelassenen IMMEDIATE-Writer samt
festem AuditContext und gegebenenfalls dessen Session-Hash-/Quellenprüfung.
`PDO::inTransaction()` wird geprüft, ist aber allein **kein Nachweis** einer
IMMEDIATE-Sperre oder Autorisierung. Der vertrauenswürdige Aufrufer bleibt dafür
verantwortlich. Fehler nicht samt PDO-/Kontextdetails an Benutzer weiterreichen.

## Frische Quelle statt umfangreicher Altdatenmigration

`IntakePolicySchema::initializeFreshLocked()` ergänzt die feste Tabelle und drei
Trigger sowie `app_migrations`-Version4 nur in einer aktiven caller-owned
Transaktion auf einer kanonischen V3-Quelle ohne Benutzer, Buchungen, Historie,
Abteilungen, Feiertage, LDAP- oder Gerätezustand. Vorhandene Geschäftsdaten
werden abgewiesen, nicht kopiert, repariert, übernommen oder neu interpretiert.
Die nichtleeren Seedtabellen werden dabei vollständig wertgenau geprüft:
`terminal_settings` genau `(id=1,enabled=0)`, `terminal_sites` genau
`(id=1,name='Praxis')`, `pause_settings` genau `(id=1,hours_threshold=6,minimum_pause=30)`
und `(id=2,hours_threshold=9,minimum_pause=45)`. Zusätzliche, fehlende oder
geänderte Zeilen sind kein frischer Ausgangszustand. Die optionale Tabelle
`login_attempts` darf fehlen oder leer sein, niemals vorhandene Versuche
mitbringen. Diese Prüfung erfolgt vor jeder V4-DDL und verändert bei Ablehnung
keine vorhandenen Einstellungen; sie setzt ein aktives Modul nicht etwa zurück.
Der Aufrufer muss zuvor die kanonische Quellschemaidentität zugelassen haben;
die Leerheits-/Versionsprüfung allein ersetzt keine spätere Schema-Admission.

`framework/database/fixtures/intake_policy_v4.php` baut ausschließlich eine
neue leere anonyme Memoryquelle aus den unveränderten V2-/V3-Definitionen auf.
Es erzeugt kein Konto, nutzbares Credential, Datei-/Runtimeprofil, Login oder
Verzeichnis. Kein Upgradebefehl, Backup-/Restore-/Adoptionsablauf oder generischer
Migrationstreiber für alte Daten wird mitgeliefert. Die vorhandenen V2/V3-
Schema-/Ledger-/Runtimeprüfungen werden gerade **nicht** aufgeweicht; V4 ist noch
nicht über sie zulassbar. Für die folgende neue Installation braucht es ein
getrennt geprüftes V4-Referenz-/Ledger-/Runtimeprofil und die bestehende getrennte
Erst-Admin-Einrichtung, ohne tatsächliche Laufzeit- oder Credentialvorbereitung.

## Gemeinsam zu integrierende Gates, noch nicht implementiert

1. Geschütztes Adminformular in der bestehenden Admin-Karte: vorhandene CSRF-,
   Refresh-/auth-/auth.session-/Sessionblocking-Grenzen; nur aktueller lokaler
   Admin, vollständige drei Flags plus erwartete Revision. Keine neue Anmeldung.
2. Manuelle Adminanlage prüft `Manual` im selben Writerlock vor dem ersten
   Benutzer-/Modell-/Auditinsert. Bestehende Rollen-/Abteilungsregeln bleiben.
3. Gewöhnliche Registrierung benötigt weiterhin das getrennte trusted Runtime-
   Opt-in **und** aktuelle DB-Policy: auch alte Routen, Tabs oder Formularseiten
   dürfen einen im Writer gesperrten Weg nicht benutzen. Keine Admin-Selbstwahl,
   kein Autologin, unveränderte generische Rückmeldung und Drosselung.
4. LDAP-Sync prüft aktuellen lokalen Admin und Policy vor Transport-, Config-,
   Key- oder Netzzugriff. Die dabei erfasste Policyrevision reist als internes
   Materialisierungsattribut mit. Unter dem einzigen Importlock vor jeder
   Konfigurations-/Identitätsänderung nochmals Admin/Sessionkontext und
   `requireAllowedLocked(..., IntakeMethod::LdapSync, capturedRevision)` prüfen.
   Änderung/Deaktivierung: keine Teilstufe, keine automatische Wiederholung.
   Bestehende LDAP-Anmeldungen nicht an den Aufnahmeweg koppeln.
5. Neue Default-/Runtime-/Routen-/Installationszulassung erst nach gemeinsamem
   Source-/Integrationsreview. Keine scheinbar wirksamen Checkboxen vorher.

Nur ein grober erfundener Memory-Kernfluss und notwendige PHP-Syntax-/Diff- und
Quellgrenzenprüfungen sind vorgesehen. Das ist keine Auth-/CSRF-/Controller-/
Writer-/HTTP-/Browser-/Dateipersistenz-/Neustart-/Directory-/Installationsabnahme
und keine fertige Administration, vollständige Laravelmigration oder Aktivierung.
