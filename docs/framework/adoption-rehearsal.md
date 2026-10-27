# FW-03 erster ausführbarer Schritt: strikt speicherinterne Übernahmeprobe

Dieses Paket ergänzt #74 nach der Dokumentationsbasis `9aa478aa2622050c23b387ea99ad6309dfe618ab` und Foundation `b9e0822174d580c4c83c15c6b2f789a7be29be3f`. Tatsächliche gemeinsame Basis ist Main **`a4deb81c650391f30e8f8b28546f1de92a7af9c7`**, Baum `9040015e6825cecc51903670f1ed36ee99f2ba77`. Der zunächst lokal benannte Integrationscommit `cf3f141…` hatte exakt denselben Baum; er wird nicht als Ersatz-Mainhistorie veröffentlicht.

**Nachfolgend separat geliefert:** [expliziter bekannter V2-Offline-Dateipfad](offline-v2-adoption.md). Der hier beschriebene Memorybefehl bleibt strikt speicherintern; sein Snapshothelfer heißt inzwischen `SourceSnapshot`, die gemeinsame Adoptionsfunktion verwendet nun Laravel-Transaktionsverwaltung mit ausdrücklich Immediate-Modus. Die damalige Memorygrenze ist keine Aussage, dass auch der separate Offlinebefehl auf den Speicher beschränkt wäre.

**Ergebnisgrenze:** Ein kontrollierter, wiederverwendbarer V2-Vorprüfungs-/Trackingkern und der echte Laravel-PDO/UDF-Lebenszyklus sind implementiert. Sie sind aktuell ausschließlich für vom Quellcode erzeugte anonyme **`:memory:`-Fixtures** zugänglich. Das ist **keine vollständige #74-Abnahme**, kein produktiver Writer, keine private Dateiübernahme und kein bestandener #75-Konkurrenz-/Rollback-/Cutovernachweis.

## Benutzung und feste Sicherheitsgrenze

Mit installierten gelockten Foundationabhängigkeiten und PHP 8.5:

```sh
cd framework
php artisan chrono:rehearse-v2-adoption
```

Der Befehl hat **keinen** Pfad-/DSN-/DB-/APP_DATA_DIR-Parameter. Drei ausschließlich intern konfigurierte Verbindungen benutzen den eigenen benannten `chrono_fixture`-Treiber, exakt `database=:memory:`, keinen Read-/Write-Split und keine Umgebungs-Fallbacks. Datei-/URI-/angehängte Datenbanken sind gesperrt. Der Adoptionsdienst prüft Konfiguration/Treiber **vor** PDO-Auflösung; der Treiber prüft vor Connect erneut. Der Standard-Foundationtreiber bleibt unverändert, wird nicht geöffnet und nicht ersetzt. Providerregistrierung allein öffnet keine DB.

Kein `config.php`/`security.php`-Include, keine Schlüssel-/Sessionanlage, kein HTTP-Aufruf, keine Änderung der lokalen App/Port-18083-Fixtures, kein realer DB-/Schlüssel-/Personalbestand. PHP-Bibliotheken werden nur als Definitionen geladen; ausschließlich die expliziten Migratoraufrufe auf den synthetischen Memoryhandles führen SQL aus. Keine neuen Composerpakete, Dienste, Webrouten, Defaultmigrationen oder Seeds.

## Kleine Zuständigkeiten, kein neues Framework

| Datei/Bereich | Aufgabe |
|---|---|
| `app/LegacyDatabase/AuditContext.php` | Expliziter Akteur/Grund für eine Operation, auch bei Exception in `finally` zurückgesetzt; kein globaler stiller Fallback. |
| `app/LegacyDatabase/AuditFunctions.php` | `Pdo\Sqlite::createFunction`, nullargumentig, registrierte Rückgabewerte geprüft; bei jedem SQL-Aufruf dynamische Auflösung des aktuellen Laravel-Scoped-Kontexts. |
| `app/Providers/LegacyFixtureServiceProvider.php` | Benannter Memorytreiber delegiert dem **Standard-`SQLiteConnector`**; kein globaler PDO-/SQLiteconnectorersatz. Auditregistrierung nach jeder tatsächlichen Erzeugung, auch Laravel-Reconnect. Korrekt benannte Connections für Framework-Lebenszyklus. |
| `database/fixtures/legacy_v2.php` | Sechs Basistabellen/Authquelle aus Mapping, ausschließlich synthetische Ausgangszeilen; dann echte `attendance_migrate()` und `practice_migrate()` aus den bestehenden Bibliotheken. Keine kopierte Buchungs-/Migrationslogik und keine nutzbaren Testpasswörter. |
| `app/LegacyDatabase/V2Preflight.php` | Native SQLite-Metadaten/Definitionen, Versions-/Beziehungs-/Tombstoneprüfung ohne DDL; unbekannter Drift wird abgelehnt, nicht repariert. |
| `app/LegacyDatabase/V2Adoption.php` | Vorprüfung vor jeder Ledgeranlage; erneute Prüfung unter eigener `BEGIN IMMEDIATE`-Grenze, wirkliche Laravel-`DatabaseMigrationRepository`, Commit auf identischem PDO. Kein `down()`/`fresh`/Reseed. |
| `app/LegacyDatabase/SourceSnapshot.php` | In-Memory-Vor-/Nachvergleich aller Quellzeilen und Quellsequenzen; ausgegeben werden nur Aggregate, keine Zeilen. |
| `app/Console/Commands/RehearseLegacyAdoption.php` | Ein grober gemeinsamer Rehearsalablauf mit begrenzten Ablehnungs-/Lebenszyklusfällen, keine Testframework-/HTTP-/Microtestsuite. |

Die eigentlichen SQLite-/Schema-/Repositorydienste sind Laravel und PDO; der kleine Adapter ist auf den bestehenden Quodara-Vertrag begrenzt. Die für spätere Dateiübernahme nutzbaren Komponenten öffnen selbst keinen Pfad. Eine Lockerung der derzeitigen Memorygrenze benötigt ein gesondert geprüftes Offline-Adoptionspaket.

## Was geprüft und geschrieben wird

Die Quellfixture enthält ursprüngliche offsetlose Zeittexte, bestehende IDs, Tages-Soll und historische Baselines/Modelle aus dem tatsächlichen Legacy-v2-Migrator. Ein synthetischer genehmigter Storno mit zweiter Person erzeugt einen echten Audit-Löschtombstone und behält das geschlossene Originalintervall sowie das Ereignis. Diese Fixtureerzeugung ist eine explizite Probe, **kein** Beleg, dass eine echte Neuinstallation/vor-v2-Datenmigration bereits durch Laravel unterstützt wird.

Vor der ersten Adoptionsänderung prüft der Kern:

- erlaubte Tabellen (20 notwendige plus lazy `login_attempts`, optional die bestätigte Frameworkledger), vollständige Appmarker `[1,2]`, SQLite-Integrität;
- native `table_xinfo`, `foreign_key_list`, `index_list/index_xinfo` sowie Tabellen-/Index-/Triggerdefinitionen. Die schmale Token-Normalisierung ignoriert Leerraum/Großschreibung **außerhalb** von SQL-Literalen, nicht Trigger-/CHECKausdrücke;
- deklarierte und im Mapping benannte logische Beziehungen, Event-/Sessioninhaberschaft; fehlende Projektion nur mit geschlossenem Original und übereinstimmendem originalem Snapshot, genehmigtem `cancel=1` mit anderer Reviewperson sowie Audit-DELETE-Beleg;
- kein unbekanntes Tabellen-/Index-/Triggerschema und keine stille Rekonstruktion/Reparatur. Nicht das ganze Assessment #55 ist dadurch erledigt.

Die native Referenz wird mit denselben tatsächlichen Quellmigratoren auf einem anderen Memoryhandle aufgebaut. `login_attempts` ist dort explizit als bekannte optionale Struktur vorhanden; die kanonische Schemasignatur bleibt bei erlaubtem späterem Anlegen dieser lazy Tabelle stabil. Zeileninhalte werden **nicht** Bestandteil der Schemasignatur.

Diese Aufnahme vergleicht Haupttabellen und ihre zugehörigen Schemaobjekte. Sie zertifiziert **keine beliebige fremde SQLite-Datei** einschließlich zusätzlicher Views/temporärer Schemas. Ein späterer privater Datei-Admissionspfad muss sämtliche erlaubten Schemaobjekte explizit begrenzen; diese Quellfixture erzeugt solche zusätzlichen Objekte nicht.

Die einzige Adoptionsänderung an der ZielDB ist die neue getrennte `framework_migrations`-Tabelle mit einem Laravel-Trackingeintrag, dessen Name Version + Schemasignatur enthält. Die ursprünglichen Marker, IDs/Sequenzen, Zeitwerte, Audit-/Event-/Modellzeilen und Schutzmechanismen bleiben unangetastet. Wiederholung erlaubt genau diesen Schritt in Batch 1; andere/mehrfache Ledgerstände stoppen. Ein vorhandener V2-Marker löst keine zweite Legacybaseline aus.

Die gemeinsame Adoptionsfunktion besitzt inzwischen die Laravel-Transaktionsverwaltung mit ausdrücklich `transaction_mode=IMMEDIATE`; sie wird nicht mit einer konkurrierenden Transaktion verschachtelt. Frameworkbuchungsdienste und generische Retrymechanik sind hier bewusst noch nicht angeschlossen. Bei Reconnect im laufenden Adoptionsschritt darf nicht auf einem anderen PDO committet werden.

## Tatsächlicher grober Nachweis

Lokaler Durchlauf auf **PHP 8.5.12, Laravel 13.38.0, SQLite 3.46.1**, tatsächlicher Handle `Pdo\Sqlite`:

1. **Adoption + Wiederholung:** 20 vorhandene Quelltabellen ohne lazy Loginzähler, 2 Nutzer, 1 erhaltene Projektion, 1 geschlossenes Original, 1 Ereignis, 1 freigegebener Storno, 2 Modelle und 10 Auditzeilen bleiben zeilen-/ID-/sequenzgleich. Genau ein neuer Framework-Trackingeintrag nach zweimaliger Adoption. Gültiger historischer Tombstone wird akzeptiert.
2. **Fehler vor Tracking:** unbekannte Tabelle und unbelegter historischer Projektionsverweis werden abgelehnt; keine Frameworkledger wird angelegt. Es werden keine falschen Daten korrigiert.
3. **Echter Writer-PDO/Auditkontext:** Probeupdate und DB-Trigger benutzen denselben Handle/Akteur/Grund; die Probe wird zurückgerollt. Ein Update ohne Kontext scheitert ohne Zeilenänderung. Kontext ist nach Operationsende nicht weiter verwendbar.
4. **Reconnect/Isolation:** Laravel-Reconnect liefert einen anderen `Pdo\Sqlite` mit erneut registrierten UDFs, aktuellem Kontext und anschließend wieder gesperrtem fehlendem Kontext. Dateikonfiguration wird abgelehnt; Standardverbindung bleibt ungeöffnet.

Der Reconnect erzeugt bei `:memory:` absichtlich eine **neue leere Datenbank**. Dieser Nachweis betrifft den Connection-/UDF-Lebenszyklus, **nicht** den Erhalt persistierter Daten nach einem Produktionsreconnect. Keine Konkurrenz mit zwei echten Dateiwritern, keine vollständige Lost-response-/Crash-/Restoreprobe und keine #75-Freigabe daraus ableiten.

Notwendige PHP-Syntax-/Plattform-/Diffprüfung wird getrennt von diesem Rehearsalergebnis berichtet. Keine neue CI ausgelöst; keine produktiven Daten, APIs, LDAPverzeichnisse oder Hardware verwendet.

## Damals nächster Schritt – inzwischen separat als Offlinepfad geliefert

Nach Review dieses Inkrements: explizit unterstützte **Offline-Adoption eines vorgeprüften privaten V2-Dateibestands** abgrenzen. Dazu gehören echtes Read-only-Open ohne Dateierzeugung, unverwechselbarer Zielpfad/Write-Pause, #55-Datenqualität, effektive FK-/Journal-/Synchronous-Einstellungen, konsistentes Backup mit #65 und kontrollierte Freigabe des tatsächlich schreibenden Connectors. Sichere Referenznachweise müssen auf den tatsächlich unterstützten Speicher-/Dateifall erweitert werden, nicht durch immer mehr Memorymikrotests ersetzt werden.

Neuinstallation, vor-v2-Upgrades, vollständige #74-Integritäts-/Rücknahmeabnahme und anschließend #75 (konkurrierende Writer, Retry/Lock/Commit/Auditfinalität) bleiben offen. Produktive Route-/DB-/Authumschaltung folgt erst #82 mit eigener Freigabe. Bestehende UI bleibt unverändert.
