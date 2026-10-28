# Laravel V4: wirksame administrative Auswahl der Aufnahmewege

## Unveränderliche Voraussetzungen und Geltung

Dieses Paket baut **nicht direkt auf einem behaupteten neuen Main** auf. Seine
lokale Basis `c1c545cd0671897ee02843dfb41b74c9a97179a4`, Baum
`5d871fadfc3522d2d51bd5c0ec6608c610045441`, ist die explizite Komposition aus:

- tatsächlichem Main `e372faab83fb5eb0703af6cbece37858c719b09f`;
- korrigiertem Policykern `3e813d6e6e13471bcb3a0098bfda815be0aaa526`;
- eingefrorenem V4-Admission-Slice `bd2f38d181b29fee72a75e2626379dbdc5e43bb9`;
- LDAP-Orchestrierung `ad24a3ece1f94ecf8c92f644bd6ac85a21d07882`.

Die zuletzt genannten Pakete bleiben eigene Prüf-/Integrationsgegenstände;
sauberes Anwenden ist keine gemeinsame Sicherheitsfreigabe. Keine Datei oder
laufende Installation wird durch dieses Quellpaket erzeugt, umgestellt oder
zugelassen. Native Bestandswriter, echte Verzeichnisse und Produktivdaten werden
nicht als abgenommen bezeichnet. Das hier beschriebene V4-Profil ist ein
explizites **frisches Laravel-Profil**, keine automatische V2/V3-Datenmigration.

**V4 darf nicht allein aufgrund der Admission als betriebsbereit aktiviert
werden.** Der gesonderte native Bootstrap-/Cutover-Schutz bleibt ein Gate:
Stand 28.10.2026 ist der erste URI-basierte NativeGuard wegen einer bestätigten
`open_basedir`-Regression ausdrücklich HOLD. Dieses Paket verwendet ihn nicht
als bestandenen Schutz und beseitigt keine unzulässige parallele native
V4-Dateinutzung. Kein Dual Write, automatischer Merge/Deploy oder Hostwechsel.

## Ein tatsächliches Profil, kein dekorativer Konfigurationsschalter

`RuntimeDatabase::isIntakePolicyV4()` gibt nur die unveränderliche, bereits
validierte `RuntimeAdmission.schemaProfile` wieder. Derselbe RuntimeDatabase
liefert sowohl die exakte benannte Readverbindung als auch jeden frischen
Operation-Writer. Seine Writerfactory übergibt den V4-Mode ausdrücklich; es gibt
keine Auswahl aus HTTPfeldern, Query, Session oder einem fehlenden Policydatensatz.

Der Writer prüft unabhängig davon die **tatsächliche** Datenbank bei Konstruktion
und erneut innerhalb seiner bestehenden IMMEDIATE-Sperre vor einer Aufnahme:
V4 verlangt exakt Anwendungsstände `[1,2,3,4]` und eine Policytabelle. Der
Legacy-Mode verlangt `[1,2]` oder `[1,2,3]` und keine entsprechende Tabelle/View
(auch Schreibvarianten des Namens werden erkannt). Ein unmarkierter Writer auf
V4, ein V4-Writer auf Legacy, zukünftige/partielle Versionen oder ein Wechsel
zwischen Konstruktion und Sperre werden verweigert. Keine Reparatur oder
Profilherabstufung. Ein bereits fehlkonfiguriert zusammengesetzter Service kann
somit keinen V4-Write über die alten Methoden freischalten.

Für bisherige V2/V3-Anwendungsprofile bleiben die bestehenden manuellen,
Registrierungs- und Importverträge erhalten. Dort wird keine fiktive V4-Policy
angelegt oder als erlaubend zurückgegeben. Die optional erweiterten
Service-/Writerargumente bewahren alte Aufrufer; fehlen die neuen serverseitigen
Kontexte tatsächlich auf V4, wird **verweigert**, nicht auf Legacy ausgewichen.
Eine V4-Entscheidung darf umgekehrt auch keinen Legacy-Writer adressieren.

## Drei Aufnahmewege, derselbe persistierte Policykern

`IntakePolicyAccess` ist die lazy Anwendungsschicht über dem unveränderten
`IntakePolicyStore`. Ohne V4-Komposition erzeugt sie weder Policykarte noch
Policyread. Mit V4 nutzt sie nur den zugelassenen `chrono`-Snapshot; keine
Pfade, zweite Verbindung, Defaultpolicy oder Transaktion über langsame Arbeit.

- **Manuell:** Der aktuelle lokale Admin und sein Standard-Passwort-Hashkontext
  werden vor Writerauflösung in einem geschlossenen Readsnapshot geprüft.
  Rolle, Quelle, Hash und erlaubte Manual-Policy ergeben ausschließlich einen
  internen serverseitigen Kontext. Im Writer werden Profil, derselbe Admin-
  Hashkontext und exakt diese Policyrevision erneut unter Lock geprüft, bevor
  bestehende Zuordnungs-/Passwort-/INSERTregeln laufen. Keine Actor-/Revision-
  oder Hashfelder aus dem Createformular werden akzeptiert.
- **Selbstregistrierung:** Der bestehende technische Route-/Profil-Opt-in bleibt
  erforderlich. In V4 entscheidet zusätzlich die aktuelle persistierte
  Registrierungspolicy, bereits **vor** der Passworthashberechnung. Die daraus
  stammende Revision wird unter Lock erneut geprüft. Rolle bleibt fest `user`,
  Quelle fest `local`, kein Autologin und keine neue Existenz-Auskunft.
  Loginlink und Registrierungs-GET folgen der echten Verfügbarkeit; deaktiviert
  ergibt keine benutzbare Registrierungsform. Ein Verfügbarkeitsfehler blendet
  den Link aus, statt bestehende Kontoanmeldung zu sperren.
- **LDAP-Sync:** Die separate Orchestrierung behält ihren frischen Admin-/Hash-/
  Policycheck vor jeder Netzwerk-/Schlüssel-/Writerfactory. Ihre bestehende
  revisiongebundene Importmethode erhält nur den zusätzlichen expliziten
  V4-Modecheck vor Principal und Store. Der alte nicht policygebundene Import
  ist auf V4 geschlossen, auf V2/V3 unverändert. Transport, Cipher,
  Kollisionsschutz und Importregeln ändern sich nicht; dieses Paket mountet
  keine LDAP-Credentialkarte und startet keinen Verzeichnislauf.

Alle drei Schreibentscheidungen verwenden atomar denselben persistierten
Policykern auf dem jeweiligen erfassten Writer-PDO. Eine zwischenzeitlich
geänderte Revision verweigert auch einen inzwischen wieder eingeschalteten Weg.
Die vorhandene einmalige Transaction-/Rollback-/Same-PDO-Fehlergrenze bleibt;
keine zweite Transaktionsverwaltung oder automatische Wiederholung.

Bestehende Konten und deren lokale/LDAP-Anmeldung werden nicht durch die
Aufnahmeflags geändert. Die Erst-Admin-Einrichtung ist ausdrücklich kein
Aufnahmeweg. Alle drei Wege dürfen ausgeschaltet sein; ein aktueller lokaler
Admin kann die Policy trotzdem wieder ändern.

## Vorhandene Adminansicht, wirklicher CAS-POST

Die zusätzliche Karte nutzt die bestehenden Adminkarten, Selects und Buttons.
Nur ein ausdrücklich zusammengesetztes V4-Profil zeigt die drei unabhängigen
Optionen Manuell/LDAP/Selbstregistrierung mit aktuellem Revisionswert. An die
Bladeansicht gehen nur drei Booleans und Revision, keine Authkontexte oder
Audit-/Accountmodelle. Ohne manuelle Freigabe ist die vorhandene Createkarte
sichtbar deaktiviert; das ersetzt nicht die unabhängigen Servergates.

`POST admin.php` erkennt genau `update_intake_policy=1`, `revision` und die drei
`*_enabled`-Felder. Die bestehenden Raw-Form-/CSRF-/Session-/Refresh-/Authgrenzen
bleiben unverändert; Query-/JSON-/Actor-/Auditfelder werden nicht in den Befehl
gemischt. Gemischte Adminaktionen werden vor Dienst-/Writeraufruf abgewiesen.
Auf Legacyprofilen bleibt der neue Aktionsname weiterhin unbekannt/400.

`IntakePolicyChange` verlangt positive kanonische Revisionsstrings und exakt
`'0'`/`'1'`. Der Service prüft frischen lokalen Admin/Hash, die vorhandene
Revision und geschlossenen Snapshot vor Writerauflösung. `changeIntakePolicy`
prüft unter der vorhandenen Sperre V4-Mode, aktuellen Principal und Store-CAS.
Veraltete Formulare überschreiben keine aktuelle Auswahl. Unverändertes
Speichern erhöht Revision/Audit nicht; Änderungen protokolliert der vorhandene
V4-Trigger mit festem `web:{Admin}` / `Aufnahmewege ändern`, globalem `user_id=0`
und ausschließlich den freigegebenen Policyfeldern.

Erst bestätigter Erfolg führt zu `admin.php#user-intake-policy`. Fehler behaupten
keinen Erfolg, sicheren Rollback oder Auto-Retry. Interne SQL-/Pfad-/Hashdetails
werden nicht an Benutzer weitergegeben. Die Fehleransicht lädt den aktuellen
Policyzustand erneut, statt einen alten Formstand heimlich durchzuschreiben.

## Grober synthetischer Nachweis und offene Grenzen

PHP **8.5.12** / Laravel **13.38.0**: Ein einziger wegwerfbarer V2/V3/V4-
Dienst-/Writer-/CAS-/Controller-/Renderablauf mit ausschließlich erfundenen
Konten und einem leeren synthetischen materialisierten LDAPstage bestand:

- bestehende manuelle Anlage, Registrierung und alter Import auf V2 **und** V3;
- explizites tatsächliches Profil, falsche Writer-/DBkombinationen und alle drei
  alten V4-Aufnahmeaufrufe ohne erforderliche neue Kontexte;
- frische Adminrolle/Hash und Entzug nach dem Readsnapshot vor Writerauflösung;
- Manual-Off vor Writer und Reg-Off vor Hasher;
- Policy-CAS/noop, alle Wege aus und unabhängige Adminwiederfreigabe, auch
  tatsächlich aktivierbare Policy-Selects bei deaktivierter manueller Createkarte;
- drei Locked-Gates, veraltete serverseitige Revisionen, Profilwechsel zwischen
  Konstruktion und Operation sowie unveränderte abgewiesene Zustände;
- echter Controller-Mixed-POST/committed Redirect, Policykarte,
  ausgeblendeter Registrierungslink/geschlossenes GET und drei feste Auditzeilen.

Der erste Nachweis setzte den rein prozesslokalen Test-Appkey zu spät vor die
synthetische SessionGuard-Konstruktion. Nur die Fixture-Reihenfolge wurde
korrigiert; Anwendungscode/Assertions wurden dafür nicht abgeschwächt. Temporäre
Dateien werden anschließend entfernt. Notwendige PHP-/kompilierte Blade-
Syntax und Diffhygiene sind separat geprüft. Keine neue Testsuite oder CI.

Das ist **kein** echter Netzwerk-/LDAP-/TLS-/Cipher-/Schlüssellauf, keine
Browser-/HTTP-/CSRF-/Login-/Middleware- oder Produktivabnahme. Die Identitäten
wurden synthetisch gesetzt, keine wirklichen Konten oder Zugangsdaten erzeugt.
Die vorhandene reine Profil-/Admissionprüfung ersetzt weiterhin keine sichere
Dateierstellung, Erstsetup-/CSP-Komposition oder native Cutoverfreigabe. Finale
serielle Integration mit Setup, Aufnahmeauswahl, LDAP-Form/CSP und Runtime
benötigt einen eigenen gemeinsamen Sicherheitsreview am tatsächlichen Baum.
