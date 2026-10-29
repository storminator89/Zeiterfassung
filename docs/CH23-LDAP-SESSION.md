# CH-23 / #29 – stabile native LDAP-Sitzungen

## Beschlossenes Verhalten

Mehrere LDAP-Anmeldungen desselben Kontos dürfen parallel bestehen. Ein erneuter
erfolgreicher Directory-Login ist **kein** Berechtigungsentzug. Die bestehende
Auth-Version `sha256(users.password)` bleibt kompatibel; der zufällige, nicht
bekannte LDAP-Platzhalter wird nicht mehr bei jedem Login neu erzeugt. Er dient
als stabile lokale Widerrufsgeneration, nicht als wiederverwendbares LDAP-Passwort.

`native_ldap_login_account()` wird erst nach dem bisherigen erfolgreichen
TLS-/Bind-/Search-Pfad aufgerufen. Unter einer eigenen `BEGIN IMMEDIATE`-Transaktion
liest es den aktuellen Kontostand: neue Konten erhalten einmalig einen zufälligen
Hash und `auth_source=ldap`; beim tatsächlichen Wechsel eines lokalen Kontos auf
LDAP wird dessen bisheriges Passwort einmalig ersetzt. Wiederholte LDAP-Logins
führen überhaupt keinen Konto-Write aus. Die Transaktion verhindert, dass zwei
Erstanmeldungen beziehungsweise Konvertierungen gegenseitig ihre Version ersetzen.
Rolle, Identität und bestehende Stammdaten werden nicht aus einem alten Snapshot
zurückgeschrieben. Fehler rollen eigene Änderungen zurück; eine bereits vom
Aufrufer gehaltene Transaktion wird nicht übernommen oder zurückgerollt.

Auch der **native LDAP-Sync** rotiert beim tatsächlichen Local→LDAP-Wechsel den
Hash. Sonst könnten zuvor ausgestellte lokale Sitzungen/JWTs die Umstellung
überleben. Wiederholter LDAP-Sync behält den bestehenden Hash ohne neue teure
Passworthashberechnung. Der gesonderte V5-Sync-Zweig und seine Zulassungsgrenzen
bleiben unverändert. Der gemeinsame Nicht-V5-Importhelper wird auch von den
vorbereiteten Laravel-Writerpfaden verwendet; deren vorgeschaltete
`refuseLocalAccountCollisions()`-Prüfung verbietet lokale/andere Kontokollisionen
weiterhin, bevor dieser Helper erreicht wird. Keine neue Spalte, Migration, Route
oder Runtime-Aktivierung.

## Bewahrter Berechtigungsentzug

Die produktiven Prüfer in `config.php`, `security.php` und `api.php` bleiben
unverändert:

- Eine echte Änderung von `users.password` (auch der vorhandene Admin-Passwort-
  Write) widerruft alte Sessions und JWTs. Ein später erfolgreich authentifizierter
  Directory-Login benutzt die **neue** Version, nicht die alte.
- Rollen werden bei jeder Webanfrage neu geladen; ein zum Benutzer zurückgestuftes
  Administratorkonto verliert Adminzugriff in allen Sitzungen.
- Ein nicht mehr vorhandenes Konto wird vom Session-/JWT-Prüfer abgelehnt.
- LDAP-Konten bleiben von lokaler Passwortanmeldung in Web und API ausgeschlossen.

**Nicht verwechselt mit einer zentralen Kontosperre:** Eine reine Änderung oder
Deaktivierung im Directory wird nicht automatisch an bestehende lokale Sessions
gemeldet. Ebenso ist lokales Löschen kein dauerhafter Wiederanlegeschutz gegen
einen später gültigen Directory-Login/Sync. Der bestehende vollständige
Konten-Lifecycle ist #30, nicht Gegenstand dieser Sessionstabilitätskorrektur.
Bereits vorhandene LDAP-Hashes werden absichtlich nicht pauschal migriert oder
rotiert; ein gewünschter Widerruf erfordert eine tatsächliche Credentialänderung.

## Grobe Prüfung und Abnahmegrenzen

`php tests/ldap_session.php` verwendet ausschließlich erfundene SQLite-
Speicherzeilen. Es führt den tatsächlichen neuen Loginhelper und den tatsächlichen
nativen Import aus. Der Session-Prüfblock aus `config.php`, `require_admin()` und
die JWT-Funktionen aus `api.php` werden quellgetreu isoliert ausgeführt; nur der
CLI-Transportausschluss sowie Header-/Cookie-/Sessionzerstörungs-I/O werden im
Harness ersetzt. Kein Bootstrap und keine vorhandene Datenbank oder Schlüsseldatei.

Geprüft werden zwei unabhängige Sitzungszustände mit stabiler Version und ohne
Repeat-Write, fortbestehender JWT, echter Rollenentzug, explizite Hashrotation mit
Ablehnung beider alten Sitzungen/JWTs, Neuanmeldung ohne Wiederbelebung alter
Versionen, Kontolöschung, Local→LDAP per Login **und** Sync, identische Wiederholung,
Constraint-Rollback und Ablehnung verschachtelter Transaktionsübernahme. Der
Receipt nennt PHP-/SQLite-Version und SHA-256 der tatsächlich verwendeten Quellen.

Dies ist ein begrenzter Komponenten-/Quelltest, kein ausgeführter Zwei-Browser-,
HTTP-, Directory-/TLS-, Mehrprozess-Race- oder Produktionsnachweis. Die reale
Browserabnahme übernimmt der Nutzer; sie wurde nicht als vom Assistenten gelaufen
ausgegeben. LDAP-Transport und echte externe Directory-Entzüge bleiben ausdrücklich
ungetestet. Syntax-, unveränderliche Diff-/Pfad- und unabhängige Reviewbelege werden
im PR dokumentiert. Keine neuen Zugangsdaten, Dienste oder Sicherheitseinstellungen.
